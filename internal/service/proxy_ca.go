package service

import (
	"github.com/christhomas/docker-dev-tools/internal/ca"
)

// ensureCA makes sure caDir holds a CA restricted to exactly tlds, creating it, or
// replacing one that is unreadable or covers other TLDs, and reports whether it did.
// With no TLDs there is nothing to restrict a CA to, so there is none and every host
// stays HTTP-only.
//
// Nothing here makes this machine trust the CA, deliberately: a development tool should
// not change which certificates the system accepts. Software that wants to verify the
// proxy's certificates trusts the CA itself (`ddt ca path`).
func ensureCA(caDir string, tlds []string) (created bool, err error) {
	if len(tlds) == 0 {
		return false, nil
	}
	if authority, err := ca.Load(caDir); err == nil && authority.Covers(tlds) {
		return false, nil
	}
	if _, err := ca.Create(caDir, tlds); err != nil {
		return false, err
	}
	return true, nil
}
