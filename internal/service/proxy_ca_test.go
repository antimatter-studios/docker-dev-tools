package service

import (
	"errors"
	"io/fs"
	"os"
	"path/filepath"
	"testing"

	"github.com/christhomas/docker-dev-tools/internal/ca"
)

func loadCA(t *testing.T, dir string) *ca.Authority {
	t.Helper()
	authority, err := ca.Load(dir)
	if err != nil {
		t.Fatalf("no usable CA: %v", err)
	}
	return authority
}

func TestEnsureCACreatesOneForTheTLDs(t *testing.T) {
	dir := filepath.Join(t.TempDir(), "ca")
	created, err := ensureCA(dir, []string{"localhost"})
	if err != nil || !created {
		t.Fatalf("created=%v err=%v, want a new CA", created, err)
	}
	if !loadCA(t, dir).Covers([]string{"localhost"}) {
		t.Error("the CA does not cover the configured TLD")
	}
}

// Apps and scripts pin this CA, so replacing it without cause breaks them.
func TestEnsureCAKeepsACurrentCA(t *testing.T) {
	dir := filepath.Join(t.TempDir(), "ca")
	if _, err := ensureCA(dir, []string{"localhost"}); err != nil {
		t.Fatal(err)
	}
	first := loadCA(t, dir).Cert.SerialNumber

	created, err := ensureCA(dir, []string{"localhost"})
	if err != nil || created {
		t.Fatalf("created=%v err=%v, want the CA kept", created, err)
	}
	if loadCA(t, dir).Cert.SerialNumber.Cmp(first) != 0 {
		t.Error("a current CA was replaced")
	}
}

// The name constraints are fixed when the CA is made, so for a new TLD it would sign
// certificates that every client rejects.
func TestEnsureCAReplacesItWhenTheTLDsChange(t *testing.T) {
	dir := filepath.Join(t.TempDir(), "ca")
	if _, err := ensureCA(dir, []string{"localhost"}); err != nil {
		t.Fatal(err)
	}
	created, err := ensureCA(dir, []string{"localhost", "test"})
	if err != nil || !created {
		t.Fatalf("created=%v err=%v, want a replacement", created, err)
	}
	if !loadCA(t, dir).Covers([]string{"localhost", "test"}) {
		t.Error("the replacement does not cover the new TLDs")
	}
}

func TestEnsureCAReplacesAnUnreadableCA(t *testing.T) {
	dir := filepath.Join(t.TempDir(), "ca")
	if _, err := ensureCA(dir, []string{"localhost"}); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(dir, ca.CertFile), []byte("not a certificate"), 0o644); err != nil {
		t.Fatal(err)
	}
	created, err := ensureCA(dir, []string{"localhost"})
	if err != nil || !created {
		t.Fatalf("created=%v err=%v, want a replacement", created, err)
	}
	loadCA(t, dir)
}

// An unrestricted CA could vouch for any site, so with no TLDs there is no CA at all.
func TestEnsureCAMakesNoneWithoutTLDs(t *testing.T) {
	dir := filepath.Join(t.TempDir(), "ca")
	created, err := ensureCA(dir, nil)
	if err != nil || created {
		t.Fatalf("created=%v err=%v, want nothing", created, err)
	}
	if _, err := os.Stat(dir); !errors.Is(err, fs.ErrNotExist) {
		t.Errorf("files were written: %v", err)
	}
}
