package cli

import (
	"errors"
	"io/fs"
	"os"
	"slices"
	"testing"

	"github.com/christhomas/docker-dev-tools/internal/app"
	"github.com/christhomas/docker-dev-tools/internal/ca"
	"github.com/christhomas/docker-dev-tools/internal/config"
	"github.com/christhomas/docker-dev-tools/internal/platform"
)

func caTestApp(t *testing.T, tlds ...string) (*app.App, *platform.MockPlatform) {
	t.Helper()
	t.Setenv("XDG_CONFIG_HOME", t.TempDir())
	m := platform.NewMockPlatform("test")
	cfg := &config.SystemConfig{}
	cfg.DNS.TLDs = tlds
	return &app.App{Config: cfg, Platform: m}, m
}

func loadTestCA(t *testing.T) *ca.Authority {
	t.Helper()
	authority, err := ca.Load(config.CADir())
	if err != nil {
		t.Fatalf("no CA: %v", err)
	}
	return authority
}

func TestInstallCACreatesAndTrustsIt(t *testing.T) {
	a, m := caTestApp(t, "localhost")
	if err := installCA(a); err != nil {
		t.Fatal(err)
	}
	authority := loadTestCA(t)
	if !authority.Covers([]string{"localhost"}) {
		t.Errorf("CA covers %v, want [localhost]", authority.Cert.PermittedDNSDomains)
	}
	if !m.TrustedCAs[authority.CertPath()] {
		t.Error("the CA was not trusted")
	}
}

func TestInstallCAKeepsATrustedCA(t *testing.T) {
	a, m := caTestApp(t, "localhost")
	if err := installCA(a); err != nil {
		t.Fatal(err)
	}
	first := loadTestCA(t).Cert.SerialNumber

	if err := installCA(a); err != nil {
		t.Fatal(err)
	}
	if loadTestCA(t).Cert.SerialNumber.Cmp(first) != 0 {
		t.Error("a current, trusted CA was replaced")
	}
	if len(m.CATrustLog) != 1 {
		t.Errorf("trusted again: %v", m.CATrustLog)
	}
}

// With the TLDs changed, the old CA's name constraints would sign certificates for the
// new TLD that no client accepts, so it is replaced, and stops being trusted first.
func TestInstallCAReplacesTheCAWhenTheTLDsChange(t *testing.T) {
	a, m := caTestApp(t, "localhost")
	if err := installCA(a); err != nil {
		t.Fatal(err)
	}
	first := loadTestCA(t).Cert.SerialNumber

	a.Config.DNS.TLDs = []string{"localhost", "test"}
	if err := installCA(a); err != nil {
		t.Fatal(err)
	}
	authority := loadTestCA(t)
	if authority.Cert.SerialNumber.Cmp(first) == 0 || !authority.Covers([]string{"localhost", "test"}) {
		t.Errorf("the CA was not replaced for the new TLDs: covers %v", authority.Cert.PermittedDNSDomains)
	}
	path := authority.CertPath()
	if want := []string{"trust:" + path, "untrust:" + path, "trust:" + path}; !slices.Equal(m.CATrustLog, want) {
		t.Errorf("CATrustLog = %v, want %v", m.CATrustLog, want)
	}
}

// An unrestricted CA could vouch for any site, so with nothing to restrict it to there
// is no CA at all.
func TestInstallCASkipsWithoutTLDs(t *testing.T) {
	a, m := caTestApp(t)
	if err := installCA(a); err != nil {
		t.Fatal(err)
	}
	if _, err := ca.Load(config.CADir()); !errors.Is(err, fs.ErrNotExist) {
		t.Errorf("a CA was created with no TLDs: %v", err)
	}
	if len(m.CATrustLog) != 0 {
		t.Errorf("something was trusted: %v", m.CATrustLog)
	}
}

func TestUninstallCAStopsTrustingButKeepsTheFiles(t *testing.T) {
	a, m := caTestApp(t, "localhost")
	if err := installCA(a); err != nil {
		t.Fatal(err)
	}
	path := loadTestCA(t).CertPath()

	if err := uninstallCA(a); err != nil {
		t.Fatal(err)
	}
	if m.TrustedCAs[path] {
		t.Error("the CA is still trusted")
	}
	if _, err := os.Stat(path); err != nil {
		t.Errorf("the CA files were removed: %v", err)
	}
}
