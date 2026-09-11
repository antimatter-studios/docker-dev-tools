package ca

import (
	"crypto"
	"crypto/ecdsa"
	"crypto/elliptic"
	"crypto/rand"
	"crypto/sha1" //nolint:gosec // matching the fingerprint format
	"crypto/tls"
	"crypto/x509"
	"crypto/x509/pkix"
	"encoding/hex"
	"errors"
	"io/fs"
	"math/big"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"
)

func create(t *testing.T, domains ...string) (*Authority, string) {
	t.Helper()
	dir := filepath.Join(t.TempDir(), "ca")
	a, err := Create(dir, domains)
	if err != nil {
		t.Fatalf("Create: %v", err)
	}
	return a, dir
}

// signLeaf issues a host certificate with the CA in dir, the way docker-config-gen does.
func signLeaf(t *testing.T, a *Authority, dir, name string) *x509.Certificate {
	t.Helper()
	pair, err := tls.LoadX509KeyPair(filepath.Join(dir, CertFile), filepath.Join(dir, KeyFile))
	if err != nil {
		t.Fatal(err)
	}
	key, err := ecdsa.GenerateKey(elliptic.P256(), rand.Reader)
	if err != nil {
		t.Fatal(err)
	}
	tmpl := &x509.Certificate{
		SerialNumber: big.NewInt(2),
		Subject:      pkix.Name{CommonName: name},
		DNSNames:     []string{name},
		NotBefore:    time.Now().Add(-time.Hour),
		NotAfter:     time.Now().Add(24 * time.Hour),
		KeyUsage:     x509.KeyUsageDigitalSignature,
		ExtKeyUsage:  []x509.ExtKeyUsage{x509.ExtKeyUsageServerAuth},
	}
	der, err := x509.CreateCertificate(rand.Reader, tmpl, a.Cert, &key.PublicKey, pair.PrivateKey.(crypto.Signer))
	if err != nil {
		t.Fatal(err)
	}
	leaf, err := x509.ParseCertificate(der)
	if err != nil {
		t.Fatal(err)
	}
	return leaf
}

func verify(a *Authority, leaf *x509.Certificate, name string) error {
	roots := x509.NewCertPool()
	roots.AddCert(a.Cert)
	_, err := leaf.Verify(x509.VerifyOptions{Roots: roots, DNSName: name})
	return err
}

func TestCreateWritesACA(t *testing.T) {
	a, dir := create(t, "localhost")

	if !a.Cert.IsCA || !a.Cert.MaxPathLenZero || a.Cert.MaxPathLen != 0 {
		t.Errorf("IsCA=%v MaxPathLen=%d MaxPathLenZero=%v, want a CA that cannot sign other CAs",
			a.Cert.IsCA, a.Cert.MaxPathLen, a.Cert.MaxPathLenZero)
	}
	if a.Cert.KeyUsage&x509.KeyUsageCertSign == 0 {
		t.Error("the CA may not sign certificates")
	}
	if a.CertPath() != filepath.Join(dir, CertFile) {
		t.Errorf("CertPath = %s", a.CertPath())
	}

	for path, want := range map[string]os.FileMode{
		dir:                          0o700,
		filepath.Join(dir, KeyFile):  0o600,
		filepath.Join(dir, CertFile): 0o644,
	} {
		fi, err := os.Stat(path)
		if err != nil {
			t.Fatal(err)
		}
		if got := fi.Mode().Perm(); got != want {
			t.Errorf("%s is mode %o, want %o", filepath.Base(path), got, want)
		}
	}

	loaded, err := Load(dir)
	if err != nil {
		t.Fatalf("Load: %v", err)
	}
	if loaded.Cert.SerialNumber.Cmp(a.Cert.SerialNumber) != 0 {
		t.Error("Load returned a different CA")
	}
}

// The name constraint is what keeps the key from vouching for real sites, so a CA
// without one must never be made.
func TestCreateRefusesAnUnrestrictedCA(t *testing.T) {
	for _, domains := range [][]string{nil, {}, {" ", "."}} {
		dir := filepath.Join(t.TempDir(), "ca")
		if _, err := Create(dir, domains); err == nil {
			t.Errorf("created a CA restricted to %q, which is no restriction", domains)
		}
		if _, err := os.Stat(dir); !errors.Is(err, fs.ErrNotExist) {
			t.Errorf("files were written for %q", domains)
		}
	}
}

func TestSignsOnlyForItsDomains(t *testing.T) {
	a, dir := create(t, "localhost")

	if err := verify(a, signLeaf(t, a, dir, "app.localhost"), "app.localhost"); err != nil {
		t.Errorf("a development host was rejected: %v", err)
	}
	err := verify(a, signLeaf(t, a, dir, "www.example.com"), "www.example.com")
	var invalid x509.CertificateInvalidError
	if !errors.As(err, &invalid) || invalid.Reason != x509.CANotAuthorizedForThisName {
		t.Errorf("a real site's certificate was not rejected by the name constraint: %v", err)
	}
}

func TestCoversExactlyItsDomains(t *testing.T) {
	a, _ := create(t, "localhost", "test")
	for _, c := range []struct {
		domains []string
		want    bool
	}{
		{[]string{"test", "localhost"}, true},
		{[]string{"LOCALHOST", ".test."}, true},
		{[]string{"localhost"}, false},
		{[]string{"localhost", "test", "dev"}, false},
		{nil, false},
	} {
		if got := a.Covers(c.domains); got != c.want {
			t.Errorf("Covers(%q) = %v, want %v", c.domains, got, c.want)
		}
	}
}

func TestLoadWithoutACA(t *testing.T) {
	if _, err := Load(t.TempDir()); !errors.Is(err, fs.ErrNotExist) {
		t.Errorf("err = %v, want fs.ErrNotExist", err)
	}
}

func TestFingerprintIsTheCertificatesSHA1(t *testing.T) {
	a, _ := create(t, "localhost")
	sum := sha1.Sum(a.Cert.Raw) //nolint:gosec // matching the fingerprint format
	if want := strings.ToUpper(hex.EncodeToString(sum[:])); a.Fingerprint() != want {
		t.Errorf("Fingerprint = %s, want %s", a.Fingerprint(), want)
	}
}

// A CA outside its validity period makes every certificate it signed fail, so it must
// be replaced rather than reused.
func TestCurrent(t *testing.T) {
	now := time.Now()
	for _, c := range []struct {
		name                string
		notBefore, notAfter time.Time
		want                bool
	}{
		{"valid for years", now.Add(-time.Hour), now.Add(5 * 365 * 24 * time.Hour), true},
		{"expired", now.Add(-48 * time.Hour), now.Add(-time.Hour), false},
		{"not yet valid", now.Add(time.Hour), now.Add(48 * time.Hour), false},
		// docker-config-gen renews host certificates with 30 days to spare, and it cannot
		// give them longer than the CA has left.
		{"about to expire", now.Add(-time.Hour), now.Add(10 * 24 * time.Hour), false},
	} {
		a := &Authority{Cert: &x509.Certificate{NotBefore: c.notBefore, NotAfter: c.notAfter}}
		if got := a.Current(now); got != c.want {
			t.Errorf("%s: Current = %v, want %v", c.name, got, c.want)
		}
	}
}
