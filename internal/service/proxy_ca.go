package service

import (
	"time"

	"github.com/christhomas/docker-dev-tools/internal/ca"
)

// ensureCA makes sure caDir holds a current CA restricted to exactly tlds, creating it,
// or replacing one that is unreadable, expired or about to expire, or covers other TLDs,
// and reports whether it did. With no TLDs there is nothing to restrict a CA to, so none
// is made and every host stays HTTP-only.
//
// Nothing here makes this machine trust the CA, deliberately: a development tool should
// not change which certificates the system accepts. Software that wants to verify the
// proxy's certificates trusts the CA itself (`ddt ca path`).
func ensureCA(caDir string, tlds []string) (created bool, err error) {
	if len(tlds) == 0 {
		return false, nil
	}
	if usableCA(caDir, tlds) {
		return false, nil
	}
	if _, err := ca.Create(caDir, tlds); err != nil {
		return false, err
	}
	return true, nil
}

// usableCA reports whether caDir holds a CA that can serve tlds now: readable, current,
// and restricted to exactly those TLDs. Only then is config-gen given it, so a CA left
// over from a configuration with other TLDs, or none, cannot keep HTTPS on.
func usableCA(caDir string, tlds []string) bool {
	if len(tlds) == 0 {
		return false
	}
	authority, err := ca.Load(caDir)
	return err == nil && authority.Covers(tlds) && authority.Current(time.Now())
}
