//go:build darwin

package platform

import (
	"strings"
	"testing"
)

func TestLaunchDaemonLabel(t *testing.T) {
	tests := []struct {
		ip   string
		want string
	}{
		{"10.254.254.254", "com.ddt.ip-alias.10-254-254-254"},
		{"192.168.1.1", "com.ddt.ip-alias.192-168-1-1"},
		{"127.0.0.2", "com.ddt.ip-alias.127-0-0-2"},
	}
	for _, tt := range tests {
		got := launchDaemonLabel(tt.ip)
		if got != tt.want {
			t.Errorf("launchDaemonLabel(%q) = %q, want %q", tt.ip, got, tt.want)
		}
	}
}

func TestLaunchDaemonPath(t *testing.T) {
	got := launchDaemonPath("10.254.254.254")
	want := "/Library/LaunchDaemons/com.ddt.ip-alias.10-254-254-254.plist"
	if got != want {
		t.Errorf("launchDaemonPath() = %q, want %q", got, want)
	}
}

func TestLaunchDaemonPlistContent(t *testing.T) {
	ip := "10.254.254.254"
	plist := launchDaemonPlist(ip)

	// Verify it's valid XML-ish plist.
	if !strings.HasPrefix(plist, "<?xml version=") {
		t.Error("plist should start with XML declaration")
	}
	if !strings.Contains(plist, "<string>com.ddt.ip-alias.10-254-254-254</string>") {
		t.Error("plist should contain the label")
	}
	if !strings.Contains(plist, "<string>/sbin/ifconfig</string>") {
		t.Error("plist should use /sbin/ifconfig")
	}
	if !strings.Contains(plist, "<string>lo0</string>") {
		t.Error("plist should target lo0")
	}
	if !strings.Contains(plist, "<string>alias</string>") {
		t.Error("plist should use alias subcommand")
	}
	if !strings.Contains(plist, "<string>10.254.254.254</string>") {
		t.Error("plist should contain the IP address")
	}
	if !strings.Contains(plist, "<string>255.255.255.255</string>") {
		t.Error("plist should contain the netmask")
	}
	if !strings.Contains(plist, "<key>RunAtLoad</key>") {
		t.Error("plist should have RunAtLoad key")
	}
	if !strings.Contains(plist, "<true/>") {
		t.Error("plist should set RunAtLoad to true")
	}
}

func TestLaunchDaemonPlistDifferentIPs(t *testing.T) {
	// Ensure different IPs produce different plists with correct content.
	ips := []string{"10.254.254.254", "192.168.1.100", "172.16.0.1"}
	for _, ip := range ips {
		plist := launchDaemonPlist(ip)
		if !strings.Contains(plist, "<string>"+ip+"</string>") {
			t.Errorf("plist for %s should contain the IP", ip)
		}
		label := launchDaemonLabel(ip)
		if !strings.Contains(plist, "<string>"+label+"</string>") {
			t.Errorf("plist for %s should contain label %s", ip, label)
		}
	}
}

func TestIsIPAliasInstalledNotPresent(t *testing.T) {
	// Use a random IP that won't have a LaunchDaemon installed.
	d := &darwinPlatform{}
	installed, err := d.IsIPAliasInstalled("99.99.99.99")
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if installed {
		t.Error("expected IP alias 99.99.99.99 to not be installed")
	}
}
