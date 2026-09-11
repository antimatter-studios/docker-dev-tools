// Package ca manages ddt's local certificate authority: the one docker-config-gen
// issues the proxy's host certificates with, trusted on this machine so browsers and
// the iOS simulator accept HTTPS on development hosts.
package ca

import (
	"crypto/ecdsa"
	"crypto/elliptic"
	"crypto/rand"
	"crypto/sha1" //nolint:gosec // the keychain tools identify certificates by SHA-1
	"crypto/tls"
	"crypto/x509"
	"crypto/x509/pkix"
	"encoding/hex"
	"encoding/pem"
	"errors"
	"fmt"
	"math/big"
	"os"
	"os/user"
	"path/filepath"
	"slices"
	"strings"
	"time"
)

const (
	// CertFile and KeyFile are the CA certificate and private key, as PEM, in the CA
	// directory. docker-config-gen reads both under the same names.
	CertFile = "ca.crt"
	KeyFile  = "ca.key"

	// lifetime is the CA's own validity. The host certificates it signs are renewed by
	// docker-config-gen; the CA only has to outlast them.
	lifetime = 10 * 365 * 24 * time.Hour
)

// Authority is a CA on disk.
type Authority struct {
	Cert *x509.Certificate
	dir  string
}

// CertPath is the CA certificate's file: the one to trust.
func (a *Authority) CertPath() string { return filepath.Join(a.dir, CertFile) }

// Fingerprint is the certificate's SHA-1 in upper-case hex, which is how macOS's
// keychain tools identify a certificate.
func (a *Authority) Fingerprint() string {
	sum := sha1.Sum(a.Cert.Raw) //nolint:gosec // an identifier, not a security check
	return strings.ToUpper(hex.EncodeToString(sum[:]))
}

// Covers reports whether the CA is restricted to exactly these domains. Anything else
// means the configured domains changed and the CA must be replaced: one missing a
// domain signs certificates that clients reject, and one with an extra domain vouches
// for more than it should.
func (a *Authority) Covers(domains []string) bool {
	return slices.Equal(normalise(a.Cert.PermittedDNSDomains), normalise(domains))
}

// Load reads the CA in dir. The error wraps fs.ErrNotExist when there is none.
func Load(dir string) (*Authority, error) {
	certPEM, err := os.ReadFile(filepath.Join(dir, CertFile))
	if err != nil {
		return nil, err
	}
	keyPEM, err := os.ReadFile(filepath.Join(dir, KeyFile))
	if err != nil {
		return nil, err
	}
	// X509KeyPair also checks that the key belongs to the certificate.
	pair, err := tls.X509KeyPair(certPEM, keyPEM)
	if err != nil {
		return nil, fmt.Errorf("the CA in %s: %w", dir, err)
	}
	cert, err := x509.ParseCertificate(pair.Certificate[0])
	if err != nil {
		return nil, fmt.Errorf("the CA in %s: %w", dir, err)
	}
	return &Authority{Cert: cert, dir: dir}, nil
}

// Create writes a new CA into dir, replacing any there. It is restricted to domains by
// X.509 name constraints, so it can only ever vouch for development names: even if its
// key were copied off this machine, it could not impersonate a real site to it.
func Create(dir string, domains []string) (*Authority, error) {
	names := normalise(domains)
	if len(names) == 0 {
		return nil, errors.New("no domains to restrict the CA to, and an unrestricted CA could vouch for any site")
	}

	key, err := ecdsa.GenerateKey(elliptic.P256(), rand.Reader)
	if err != nil {
		return nil, fmt.Errorf("generating the CA key: %w", err)
	}
	serial, err := rand.Int(rand.Reader, new(big.Int).Lsh(big.NewInt(1), 128))
	if err != nil {
		return nil, fmt.Errorf("generating a serial number: %w", err)
	}

	now := time.Now()
	tmpl := &x509.Certificate{
		SerialNumber: serial,
		Subject: pkix.Name{
			Organization: []string{"docker-dev-tools"},
			// Named for its owner, as mkcert does, so it can be told apart in a trust
			// store that holds several.
			CommonName: "ddt development CA " + owner(),
		},
		NotBefore:             now.Add(-time.Hour),
		NotAfter:              now.Add(lifetime),
		IsCA:                  true,
		BasicConstraintsValid: true,
		// Host certificates only: it may not sign another CA.
		MaxPathLenZero:              true,
		KeyUsage:                    x509.KeyUsageCertSign | x509.KeyUsageCRLSign,
		PermittedDNSDomains:         names,
		PermittedDNSDomainsCritical: true,
	}
	der, err := x509.CreateCertificate(rand.Reader, tmpl, tmpl, &key.PublicKey, key)
	if err != nil {
		return nil, fmt.Errorf("signing the CA certificate: %w", err)
	}
	keyDER, err := x509.MarshalPKCS8PrivateKey(key)
	if err != nil {
		return nil, fmt.Errorf("encoding the CA key: %w", err)
	}

	if err := os.MkdirAll(dir, 0o700); err != nil {
		return nil, fmt.Errorf("creating %s: %w", dir, err)
	}
	if err := writeFile(filepath.Join(dir, KeyFile), pem.EncodeToMemory(&pem.Block{Type: "PRIVATE KEY", Bytes: keyDER}), 0o600); err != nil {
		return nil, fmt.Errorf("writing the CA key: %w", err)
	}
	if err := writeFile(filepath.Join(dir, CertFile), pem.EncodeToMemory(&pem.Block{Type: "CERTIFICATE", Bytes: der}), 0o644); err != nil {
		return nil, fmt.Errorf("writing the CA certificate: %w", err)
	}

	cert, err := x509.ParseCertificate(der)
	if err != nil {
		return nil, fmt.Errorf("parsing the CA certificate: %w", err)
	}
	return &Authority{Cert: cert, dir: dir}, nil
}

func owner() string {
	name := "unknown"
	if u, err := user.Current(); err == nil {
		name = u.Username
	}
	host, err := os.Hostname()
	if err != nil {
		host = "unknown"
	}
	return "(" + name + "@" + host + ")"
}

// normalise lower-cases domains, drops surrounding dots and blanks, and sorts them, so
// the same set compares equal however it was written.
func normalise(domains []string) []string {
	var out []string
	for _, d := range domains {
		d = strings.Trim(strings.ToLower(strings.TrimSpace(d)), ".")
		if d != "" && !slices.Contains(out, d) {
			out = append(out, d)
		}
	}
	slices.Sort(out)
	return out
}

// writeFile replaces path atomically, so a reader never sees half a file.
func writeFile(path string, data []byte, mode os.FileMode) error {
	tmp, err := os.CreateTemp(filepath.Dir(path), ".tmp-*")
	if err != nil {
		return err
	}
	defer func() { _ = os.Remove(tmp.Name()) }() // a no-op once renamed
	if _, err := tmp.Write(data); err != nil {
		_ = tmp.Close()
		return err
	}
	if err := tmp.Chmod(mode); err != nil {
		_ = tmp.Close()
		return err
	}
	if err := tmp.Close(); err != nil {
		return err
	}
	return os.Rename(tmp.Name(), path)
}
