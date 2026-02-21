//go:build darwin

package platform

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"

	"github.com/christhomas/docker-dev-tools/internal/tui/styles"
)

type darwinPlatform struct{}

func newPlatform() Platform {
	return &darwinPlatform{}
}

func (d *darwinPlatform) Name() string {
	return "darwin"
}

func (d *darwinPlatform) AddIPAlias(ip string) error {
	fmt.Fprintln(os.Stderr, styles.SudoNotice("Adding IP alias to loopback interface",
		fmt.Sprintf("Command: ifconfig lo0 alias %s", ip)))
	cmd := exec.Command("sudo", "ifconfig", "lo0", "alias", ip, "netmask", "255.255.255.255")
	if out, err := cmd.CombinedOutput(); err != nil {
		return fmt.Errorf("adding IP alias: %s: %w", strings.TrimSpace(string(out)), err)
	}
	return nil
}

func (d *darwinPlatform) RemoveIPAlias(ip string) error {
	fmt.Fprintln(os.Stderr, styles.SudoNotice("Removing IP alias from loopback interface",
		fmt.Sprintf("Command: ifconfig lo0 -alias %s", ip)))
	cmd := exec.Command("sudo", "ifconfig", "lo0", "-alias", ip)
	if out, err := cmd.CombinedOutput(); err != nil {
		return fmt.Errorf("removing IP alias: %s: %w", strings.TrimSpace(string(out)), err)
	}
	return nil
}

func (d *darwinPlatform) HasIPAlias(ip string) (bool, error) {
	cmd := exec.Command("ifconfig", "lo0")
	out, err := cmd.Output()
	if err != nil {
		return false, fmt.Errorf("checking IP alias: %w", err)
	}
	return strings.Contains(string(out), ip), nil
}

// EnableDNS creates a /etc/resolver/<domain> file pointing to the given IP and port.
// macOS automatically reads /etc/resolver/ for per-domain DNS configuration.
// Returns true if the file was written, false if it already had the correct content.
func (d *darwinPlatform) EnableDNS(domain, ip string, port int) (bool, error) {
	dir := "/etc/resolver"
	content := fmt.Sprintf("nameserver %s\nport %d\n", ip, port)
	file := filepath.Join(dir, domain)

	// Skip write if the file already has the exact content we want.
	if existing, err := os.ReadFile(file); err == nil && string(existing) == content {
		return false, nil
	}

	fmt.Fprintln(os.Stderr, styles.SudoNotice("Writing DNS resolver file",
		fmt.Sprintf("File: %s", file),
		fmt.Sprintf("Content: nameserver %s / port %d", ip, port)))
	if err := exec.Command("sudo", "mkdir", "-p", dir).Run(); err != nil {
		return false, fmt.Errorf("creating resolver directory: %w", err)
	}

	cmd := exec.Command("sudo", "tee", file)
	cmd.Stdin = strings.NewReader(content)
	cmd.Stdout = nil
	if out, err := cmd.CombinedOutput(); err != nil {
		return false, fmt.Errorf("writing resolver file %s: %s: %w", file, strings.TrimSpace(string(out)), err)
	}

	// Ensure the file is world-readable so the content check works next time
	// without sudo.
	_ = exec.Command("sudo", "chmod", "644", file).Run()

	return true, nil
}

// DisableDNS removes the /etc/resolver/<domain> file.
func (d *darwinPlatform) DisableDNS(domain string) error {
	file := filepath.Join("/etc/resolver", domain)

	// Skip if the file doesn't exist.
	if _, err := os.Stat(file); os.IsNotExist(err) {
		return nil
	}

	fmt.Fprintln(os.Stderr, styles.SudoNotice("Removing DNS resolver file",
		fmt.Sprintf("File: %s", file)))
	cmd := exec.Command("sudo", "rm", "-f", file)
	if out, err := cmd.CombinedOutput(); err != nil {
		return fmt.Errorf("removing resolver file %s: %s: %w", file, strings.TrimSpace(string(out)), err)
	}
	return nil
}

// FlushDNS clears the macOS DNS cache.
func (d *darwinPlatform) FlushDNS() error {
	fmt.Fprintln(os.Stderr, styles.SudoNotice("Flushing DNS cache",
		"Command: dscacheutil -flushcache",
		"Command: killall -HUP mDNSResponder"))
	if err := exec.Command("dscacheutil", "-flushcache").Run(); err != nil {
		return fmt.Errorf("flushing DNS cache: %w", err)
	}
	_ = exec.Command("sudo", "killall", "-HUP", "mDNSResponder").Run()
	return nil
}

// GetSystemUpstreams reads the currently configured DNS servers from scutil.
func (d *darwinPlatform) GetSystemUpstreams() ([]string, error) {
	out, err := exec.Command("scutil", "--dns").Output()
	if err != nil {
		return nil, fmt.Errorf("reading DNS config: %w", err)
	}

	var servers []string
	seen := make(map[string]bool)
	for _, line := range strings.Split(string(out), "\n") {
		line = strings.TrimSpace(line)
		if strings.HasPrefix(line, "nameserver[") {
			parts := strings.SplitN(line, ":", 2)
			if len(parts) == 2 {
				ip := strings.TrimSpace(parts[1])
				if !seen[ip] {
					servers = append(servers, ip)
					seen[ip] = true
				}
			}
		}
	}

	if len(servers) == 0 {
		servers = readResolvConf()
	}
	return servers, nil
}

func readResolvConf() []string {
	data, err := os.ReadFile("/etc/resolv.conf")
	if err != nil {
		return nil
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
	return servers
}
