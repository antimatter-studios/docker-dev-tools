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

const launchDaemonDir = "/Library/LaunchDaemons"

func launchDaemonLabel(ip string) string {
	safe := strings.ReplaceAll(ip, ".", "-")
	return fmt.Sprintf("com.ddt.ip-alias.%s", safe)
}

func launchDaemonPath(ip string) string {
	return filepath.Join(launchDaemonDir, launchDaemonLabel(ip)+".plist")
}

func launchDaemonPlist(ip string) string {
	label := launchDaemonLabel(ip)
	return fmt.Sprintf(`<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>Label</key>
	<string>%s</string>
	<key>ProgramArguments</key>
	<array>
		<string>/sbin/ifconfig</string>
		<string>lo0</string>
		<string>alias</string>
		<string>%s</string>
		<string>netmask</string>
		<string>255.255.255.255</string>
	</array>
	<key>RunAtLoad</key>
	<true/>
</dict>
</plist>
`, label, ip)
}

func (d *darwinPlatform) InstallIPAlias(ip string) error {
	plistPath := launchDaemonPath(ip)
	content := launchDaemonPlist(ip)

	// Check if already installed with correct content.
	if existing, err := os.ReadFile(plistPath); err == nil && string(existing) == content {
		// Ensure the daemon is loaded.
		_ = exec.Command("sudo", "launchctl", "load", "-w", plistPath).Run()
		return nil
	}

	fmt.Fprintln(os.Stderr, styles.SudoNotice("Installing IP alias LaunchDaemon",
		fmt.Sprintf("File: %s", plistPath),
		fmt.Sprintf("IP: %s on lo0", ip)))

	// Write the plist file.
	cmd := exec.Command("sudo", "tee", plistPath)
	cmd.Stdin = strings.NewReader(content)
	cmd.Stdout = nil
	if out, err := cmd.CombinedOutput(); err != nil {
		return fmt.Errorf("writing LaunchDaemon plist: %s: %w", strings.TrimSpace(string(out)), err)
	}

	// Set ownership and permissions.
	if err := exec.Command("sudo", "chmod", "644", plistPath).Run(); err != nil {
		return fmt.Errorf("setting plist permissions: %w", err)
	}
	if err := exec.Command("sudo", "chown", "root:wheel", plistPath).Run(); err != nil {
		return fmt.Errorf("setting plist ownership: %w", err)
	}

	// Load the daemon (this also runs it immediately due to RunAtLoad).
	if out, err := exec.Command("sudo", "launchctl", "load", "-w", plistPath).CombinedOutput(); err != nil {
		return fmt.Errorf("loading LaunchDaemon: %s: %w", strings.TrimSpace(string(out)), err)
	}

	return nil
}

func (d *darwinPlatform) UninstallIPAlias(ip string) error {
	plistPath := launchDaemonPath(ip)

	// Check if installed.
	if _, err := os.Stat(plistPath); os.IsNotExist(err) {
		return nil
	}

	fmt.Fprintln(os.Stderr, styles.SudoNotice("Removing IP alias LaunchDaemon",
		fmt.Sprintf("File: %s", plistPath)))

	// Unload the daemon.
	_ = exec.Command("sudo", "launchctl", "unload", "-w", plistPath).Run()

	// Remove the plist file.
	if out, err := exec.Command("sudo", "rm", "-f", plistPath).CombinedOutput(); err != nil {
		return fmt.Errorf("removing LaunchDaemon plist: %s: %w", strings.TrimSpace(string(out)), err)
	}

	// Remove the alias itself.
	_ = d.RemoveIPAlias(ip)

	return nil
}

func (d *darwinPlatform) IsIPAliasInstalled(ip string) (bool, error) {
	plistPath := launchDaemonPath(ip)
	_, err := os.Stat(plistPath)
	if os.IsNotExist(err) {
		return false, nil
	}
	if err != nil {
		return false, err
	}
	return true, nil
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

// ListResolverDomains returns the domains that have resolver files in /etc/resolver/.
func (d *darwinPlatform) ListResolverDomains() ([]string, error) {
	entries, err := os.ReadDir("/etc/resolver")
	if err != nil {
		if os.IsNotExist(err) {
			return nil, nil
		}
		return nil, fmt.Errorf("reading /etc/resolver: %w", err)
	}
	var domains []string
	for _, e := range entries {
		if !e.IsDir() {
			domains = append(domains, e.Name())
		}
	}
	return domains, nil
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
