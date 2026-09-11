package service

import (
	"crypto/ecdsa"
	"crypto/elliptic"
	"crypto/rand"
	"crypto/x509"
	"crypto/x509/pkix"
	"encoding/pem"
	"math/big"
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/christhomas/docker-dev-tools/internal/ca"
)

// writeCA puts a CA in dir that is restricted to domains and valid from notBefore to
// notAfter, which ca.Create cannot be asked for.
func writeCA(t *testing.T, dir string, notBefore, notAfter time.Time, domains ...string) {
	t.Helper()
	key, err := ecdsa.GenerateKey(elliptic.P256(), rand.Reader)
	if err != nil {
		t.Fatal(err)
	}
	tmpl := &x509.Certificate{
		SerialNumber:                big.NewInt(7),
		Subject:                     pkix.Name{CommonName: "test CA"},
		NotBefore:                   notBefore,
		NotAfter:                    notAfter,
		IsCA:                        true,
		BasicConstraintsValid:       true,
		KeyUsage:                    x509.KeyUsageCertSign,
		PermittedDNSDomains:         domains,
		PermittedDNSDomainsCritical: true,
	}
	der, err := x509.CreateCertificate(rand.Reader, tmpl, tmpl, &key.PublicKey, key)
	if err != nil {
		t.Fatal(err)
	}
	keyDER, err := x509.MarshalPKCS8PrivateKey(key)
	if err != nil {
		t.Fatal(err)
	}
	if err := os.MkdirAll(dir, 0o700); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(dir, ca.CertFile), pem.EncodeToMemory(&pem.Block{Type: "CERTIFICATE", Bytes: der}), 0o644); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(dir, ca.KeyFile), pem.EncodeToMemory(&pem.Block{Type: "PRIVATE KEY", Bytes: keyDER}), 0o600); err != nil {
		t.Fatal(err)
	}
}

// An expired CA makes every certificate it signed fail, so a restart must replace it.
func TestEnsureCAReplacesAnExpiredCA(t *testing.T) {
	dir := filepath.Join(t.TempDir(), "ca")
	writeCA(t, dir, time.Now().Add(-48*time.Hour), time.Now().Add(-time.Hour), "localhost")

	created, err := ensureCA(dir, []string{"localhost"})
	if err != nil || !created {
		t.Fatalf("created=%v err=%v, want the expired CA replaced", created, err)
	}
	if !loadCA(t, dir).Current(time.Now()) {
		t.Error("the replacement is not current")
	}
}

// With every TLD removed, the configuration says HTTP only. A CA left on disk from
// before must not keep HTTPS on by being mounted.
func TestConfigGenGetsNoCAWithoutTLDs(t *testing.T) {
	caDir := filepath.Join(t.TempDir(), "ca")
	if _, err := ca.Create(caDir, []string{"localhost"}); err != nil {
		t.Fatal(err)
	}
	_, genHost := configGenSpec(testConfig(), caDir) // no TLDs configured
	if m, ok := mountAt(genHost.Mounts, configGenCADir); ok {
		t.Errorf("mounted a CA with no TLDs configured: %+v", m)
	}
}

// A CA for other TLDs signs certificates every client rejects for the configured ones.
func TestConfigGenGetsNoCAForOtherTLDs(t *testing.T) {
	caDir := filepath.Join(t.TempDir(), "ca")
	if _, err := ca.Create(caDir, []string{"localhost"}); err != nil {
		t.Fatal(err)
	}
	cfg := testConfig()
	cfg.DNS.TLDs = []string{"test"}
	_, genHost := configGenSpec(cfg, caDir)
	if m, ok := mountAt(genHost.Mounts, configGenCADir); ok {
		t.Errorf("mounted a CA that does not cover the configured TLDs: %+v", m)
	}
}

func TestConfigGenGetsNoExpiredCA(t *testing.T) {
	caDir := filepath.Join(t.TempDir(), "ca")
	writeCA(t, caDir, time.Now().Add(-48*time.Hour), time.Now().Add(-time.Hour), "localhost")
	cfg := testConfig()
	cfg.DNS.TLDs = []string{"localhost"}
	_, genHost := configGenSpec(cfg, caDir)
	if m, ok := mountAt(genHost.Mounts, configGenCADir); ok {
		t.Errorf("mounted an expired CA: %+v", m)
	}
}
