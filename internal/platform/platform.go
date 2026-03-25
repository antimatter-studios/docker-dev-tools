package platform

import (
	"fmt"
	"net"
)

// Platform abstracts OS-specific operations for IP aliasing and DNS configuration.
type Platform interface {
	// AddIPAlias adds an IP alias to a network interface (ephemeral, lost on reboot).
	AddIPAlias(ip string) error
	// RemoveIPAlias removes an IP alias from a network interface.
	RemoveIPAlias(ip string) error
	// HasIPAlias checks if an IP alias is currently active.
	HasIPAlias(ip string) (bool, error)

	// InstallIPAlias creates a persistent system service that sets the IP alias
	// at boot (e.g. LaunchDaemon on macOS, systemd unit on Linux).
	InstallIPAlias(ip string) error
	// UninstallIPAlias removes the persistent IP alias service.
	UninstallIPAlias(ip string) error
	// IsIPAliasInstalled checks if the persistent IP alias service is installed.
	IsIPAliasInstalled(ip string) (bool, error)

	// EnableDNS configures the system to resolve a domain via the given IP and port.
	// Returns true if a change was made (file written), false if already up to date.
	EnableDNS(domain, ip string, port int) (changed bool, err error)
	// DisableDNS removes system DNS resolver configuration for a domain.
	DisableDNS(domain string) error
	// FlushDNS clears the system DNS cache.
	FlushDNS() error
	// GetSystemUpstreams returns the currently configured system DNS servers.
	GetSystemUpstreams() ([]string, error)
	// ListResolverDomains returns the domains that have system-level DNS
	// resolver entries (e.g. files in /etc/resolver/ on macOS).
	ListResolverDomains() ([]string, error)

	// Name returns the platform name (e.g., "darwin", "linux").
	Name() string
}

// CheckPortAvailable tests if a UDP and TCP port is free on the given IP.
// Returns nil if both are available, or an error describing what's in use.
func CheckPortAvailable(ip string, port int) error {
	addr := fmt.Sprintf("%s:%d", ip, port)

	// Check TCP.
	ln, err := net.Listen("tcp", addr)
	if err != nil {
		return fmt.Errorf("TCP port %d on %s is already in use", port, ip)
	}
	ln.Close()

	// Check UDP.
	ua, err := net.ResolveUDPAddr("udp", addr)
	if err != nil {
		return fmt.Errorf("resolving UDP address %s: %w", addr, err)
	}
	conn, err := net.ListenUDP("udp", ua)
	if err != nil {
		return fmt.Errorf("UDP port %d on %s is already in use", port, ip)
	}
	conn.Close()

	return nil
}
