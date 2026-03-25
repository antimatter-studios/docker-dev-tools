//go:build linux

package platform

import (
	"fmt"
	"os"
	"os/exec"
	"strings"

	"github.com/christhomas/docker-dev-tools/internal/tui/styles"
)

type linuxPlatform struct{}

func newPlatform() Platform {
	return &linuxPlatform{}
}

func (l *linuxPlatform) Name() string {
	return "linux"
}

func (l *linuxPlatform) AddIPAlias(ip string) error {
	fmt.Fprintln(os.Stderr, styles.SudoNotice("Adding IP alias to loopback interface",
		fmt.Sprintf("Command: ip addr add %s/32 dev lo", ip)))
	cmd := exec.Command("sudo", "ip", "addr", "add", ip+"/32", "dev", "lo")
	if out, err := cmd.CombinedOutput(); err != nil {
		// Fallback to ifconfig.
		cmd = exec.Command("sudo", "ifconfig", "lo:0", ip, "netmask", "255.255.255.255", "up")
		if out2, err2 := cmd.CombinedOutput(); err2 != nil {
			return fmt.Errorf("adding IP alias: %s / %s: %w", strings.TrimSpace(string(out)), strings.TrimSpace(string(out2)), err2)
		}
	}
	return nil
}

func (l *linuxPlatform) RemoveIPAlias(ip string) error {
	fmt.Fprintln(os.Stderr, styles.SudoNotice("Removing IP alias from loopback interface",
		fmt.Sprintf("Command: ip addr del %s/32 dev lo", ip)))
	cmd := exec.Command("sudo", "ip", "addr", "del", ip+"/32", "dev", "lo")
	if out, err := cmd.CombinedOutput(); err != nil {
		return fmt.Errorf("removing IP alias: %s: %w", strings.TrimSpace(string(out)), err)
	}
	return nil
}

func (l *linuxPlatform) HasIPAlias(ip string) (bool, error) {
	cmd := exec.Command("ip", "addr", "show", "lo")
	out, err := cmd.Output()
	if err != nil {
		return false, fmt.Errorf("checking IP alias: %w", err)
	}
	return strings.Contains(string(out), ip), nil
}

func (l *linuxPlatform) InstallIPAlias(ip string) error {
	// TODO: implement persistent IP alias for Linux (e.g. systemd-networkd .netdev unit).
	// For now, fall back to the ephemeral alias.
	return l.AddIPAlias(ip)
}

func (l *linuxPlatform) UninstallIPAlias(ip string) error {
	// TODO: implement persistent IP alias removal for Linux.
	return l.RemoveIPAlias(ip)
}

func (l *linuxPlatform) IsIPAliasInstalled(ip string) (bool, error) {
	// TODO: check for persistent systemd unit.
	// For now, just check if the alias is currently active.
	return l.HasIPAlias(ip)
}

// hasSystemdResolved checks if systemd-resolved is available.
func hasSystemdResolved() bool {
	_, err := exec.LookPath("systemd-resolve")
	return err == nil
}

// EnableDNS adds a nameserver entry to /etc/resolv.conf or systemd-resolved config.
// Returns true if a change was made, false if already configured correctly.
func (l *linuxPlatform) EnableDNS(domain, ip string, port int) (bool, error) {
	if hasSystemdResolved() {
		return l.enableSystemdDNS(ip, port)
	}
	return l.enableResolvConf(ip, port)
}

func (l *linuxPlatform) enableSystemdDNS(ip string, port int) (bool, error) {
	confPath := "/etc/systemd/resolved.conf"
	dnsValue := ip
	if port != 53 {
		dnsValue = fmt.Sprintf("%s#%d", ip, port)
	}

	// Check if already configured correctly.
	data, err := os.ReadFile(confPath)
	if err != nil {
		return false, fmt.Errorf("reading resolved.conf: %w", err)
	}

	content := string(data)
	target := fmt.Sprintf("DNS=%s", dnsValue)
	for _, line := range strings.Split(content, "\n") {
		if strings.TrimSpace(line) == target {
			return false, nil // already configured
		}
	}

	fmt.Fprintln(os.Stderr, styles.SudoNotice("Configuring systemd-resolved DNS",
		fmt.Sprintf("File: %s", confPath),
		fmt.Sprintf("Setting: %s", target),
		"Command: systemctl restart systemd-resolved"))

	// Update the DNS= line.
	lines := strings.Split(content, "\n")
	found := false
	for i, line := range lines {
		trimmed := strings.TrimSpace(line)
		if strings.HasPrefix(trimmed, "DNS=") || strings.HasPrefix(trimmed, "#DNS=") {
			lines[i] = target
			found = true
			break
		}
	}
	if !found {
		lines = append(lines, target)
	}

	newContent := strings.Join(lines, "\n")
	cmd := exec.Command("sudo", "tee", confPath)
	cmd.Stdin = strings.NewReader(newContent)
	cmd.Stdout = nil
	if err := cmd.Run(); err != nil {
		return false, fmt.Errorf("writing resolved.conf: %w", err)
	}

	return true, exec.Command("sudo", "systemctl", "restart", "systemd-resolved").Run()
}

func (l *linuxPlatform) enableResolvConf(ip string, port int) (bool, error) {
	entry := fmt.Sprintf("nameserver %s", ip)
	data, err := os.ReadFile("/etc/resolv.conf")
	if err != nil {
		data = []byte{}
	}

	if strings.Contains(string(data), entry) {
		return false, nil // already configured
	}

	if port != 53 {
		fmt.Fprintln(os.Stderr, styles.SudoNotice("Writing DNS nameserver to resolv.conf",
			"File: /etc/resolv.conf",
			fmt.Sprintf("Warning: resolv.conf does not support custom ports (port %d)", port),
			fmt.Sprintf("Content: nameserver %s (queries will use standard port 53)", ip)))
	} else {
		fmt.Fprintln(os.Stderr, styles.SudoNotice("Writing DNS nameserver to resolv.conf",
			"File: /etc/resolv.conf",
			fmt.Sprintf("Content: nameserver %s", ip)))
	}

	newContent := entry + "\n" + string(data)
	cmd := exec.Command("sudo", "tee", "/etc/resolv.conf")
	cmd.Stdin = strings.NewReader(newContent)
	cmd.Stdout = nil
	return true, cmd.Run()
}

// DisableDNS removes our nameserver from the system DNS config.
func (l *linuxPlatform) DisableDNS(domain string) error {
	// On Linux we don't use per-domain resolver files like macOS,
	// so this is handled at the service level when removing domains.
	return nil
}

// FlushDNS clears the Linux DNS cache.
func (l *linuxPlatform) FlushDNS() error {
	if hasSystemdResolved() {
		fmt.Fprintln(os.Stderr, styles.SudoNotice("Flushing DNS cache",
			"Command: systemd-resolve --flush-caches"))
		return exec.Command("sudo", "systemd-resolve", "--flush-caches").Run()
	}
	// No standard cache to flush on basic Linux setups.
	return nil
}

// GetSystemUpstreams returns current system DNS servers from /etc/resolv.conf.
func (l *linuxPlatform) GetSystemUpstreams() ([]string, error) {
	data, err := os.ReadFile("/etc/resolv.conf")
	if err != nil {
		return nil, fmt.Errorf("reading /etc/resolv.conf: %w", err)
	}

	var servers []string
	for _, line := range strings.Split(string(data), "\n") {
		line = strings.TrimSpace(line)
		if strings.HasPrefix(line, "nameserver ") {
			ip := strings.TrimSpace(strings.TrimPrefix(line, "nameserver "))
			if ip != "" {
				servers = append(servers, ip)
			}
		}
	}
	return servers, nil
}
