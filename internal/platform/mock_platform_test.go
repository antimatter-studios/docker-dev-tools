package platform

import (
	"errors"
	"testing"
)

func TestMockPlatformImplementsInterface(t *testing.T) {
	// Compile-time check that MockPlatform satisfies Platform.
	var _ Platform = (*MockPlatform)(nil)
}

func TestMockIPAliasLifecycle(t *testing.T) {
	m := NewMockPlatform("test")

	// Initially no alias.
	has, err := m.HasIPAlias("10.254.254.254")
	if err != nil {
		t.Fatal(err)
	}
	if has {
		t.Error("expected no alias initially")
	}

	// Add ephemeral alias.
	if err := m.AddIPAlias("10.254.254.254"); err != nil {
		t.Fatal(err)
	}
	has, _ = m.HasIPAlias("10.254.254.254")
	if !has {
		t.Error("expected alias after Add")
	}

	// Not persistently installed.
	installed, _ := m.IsIPAliasInstalled("10.254.254.254")
	if installed {
		t.Error("ephemeral alias should not count as installed")
	}

	// Remove.
	if err := m.RemoveIPAlias("10.254.254.254"); err != nil {
		t.Fatal(err)
	}
	has, _ = m.HasIPAlias("10.254.254.254")
	if has {
		t.Error("expected no alias after Remove")
	}
}

func TestMockInstallIPAliasLifecycle(t *testing.T) {
	m := NewMockPlatform("test")

	// Install persistent alias.
	if err := m.InstallIPAlias("10.254.254.254"); err != nil {
		t.Fatal(err)
	}

	// Should be both active and installed.
	has, _ := m.HasIPAlias("10.254.254.254")
	if !has {
		t.Error("expected alias active after Install")
	}
	installed, _ := m.IsIPAliasInstalled("10.254.254.254")
	if !installed {
		t.Error("expected alias installed after Install")
	}

	// Uninstall.
	if err := m.UninstallIPAlias("10.254.254.254"); err != nil {
		t.Fatal(err)
	}
	has, _ = m.HasIPAlias("10.254.254.254")
	if has {
		t.Error("expected alias gone after Uninstall")
	}
	installed, _ = m.IsIPAliasInstalled("10.254.254.254")
	if installed {
		t.Error("expected not installed after Uninstall")
	}
}

func TestMockDNSLifecycle(t *testing.T) {
	m := NewMockPlatform("test")

	// Enable DNS for a TLD.
	changed, err := m.EnableDNS("localhost", "10.254.254.254", 10053)
	if err != nil {
		t.Fatal(err)
	}
	if !changed {
		t.Error("expected changed on first EnableDNS")
	}

	// Second call with same params should not change.
	changed, err = m.EnableDNS("localhost", "10.254.254.254", 10053)
	if err != nil {
		t.Fatal(err)
	}
	if changed {
		t.Error("expected no change on duplicate EnableDNS")
	}

	// Different port should change.
	changed, _ = m.EnableDNS("localhost", "10.254.254.254", 53)
	if !changed {
		t.Error("expected change when port differs")
	}

	// Disable.
	if err := m.DisableDNS("localhost"); err != nil {
		t.Fatal(err)
	}
	if _, ok := m.DNSEntries["localhost"]; ok {
		t.Error("expected DNS entry removed after Disable")
	}
}

func TestMockFlushDNS(t *testing.T) {
	m := NewMockPlatform("test")

	if m.FlushedDNS != 0 {
		t.Error("expected 0 flushes initially")
	}
	_ = m.FlushDNS()
	_ = m.FlushDNS()
	if m.FlushedDNS != 2 {
		t.Errorf("expected 2 flushes, got %d", m.FlushedDNS)
	}
}

func TestMockErrorInjection(t *testing.T) {
	m := NewMockPlatform("test")
	testErr := errors.New("simulated failure")

	m.Errors["AddIPAlias"] = testErr
	if err := m.AddIPAlias("10.0.0.1"); !errors.Is(err, testErr) {
		t.Errorf("expected injected error, got: %v", err)
	}
	// Alias should not have been added.
	has, _ := m.HasIPAlias("10.0.0.1")
	if has {
		t.Error("alias should not exist after error")
	}

	m.Errors["InstallIPAlias"] = testErr
	if err := m.InstallIPAlias("10.0.0.1"); !errors.Is(err, testErr) {
		t.Errorf("expected injected error, got: %v", err)
	}

	m.Errors["EnableDNS"] = testErr
	_, err := m.EnableDNS("localhost", "10.0.0.1", 53)
	if !errors.Is(err, testErr) {
		t.Errorf("expected injected error, got: %v", err)
	}

	m.Errors["FlushDNS"] = testErr
	if err := m.FlushDNS(); !errors.Is(err, testErr) {
		t.Errorf("expected injected error, got: %v", err)
	}
}

func TestMockGetSystemUpstreams(t *testing.T) {
	m := NewMockPlatform("test")
	m.Upstreams = []string{"8.8.8.8", "1.1.1.1"}

	servers, err := m.GetSystemUpstreams()
	if err != nil {
		t.Fatal(err)
	}
	if len(servers) != 2 {
		t.Errorf("expected 2 upstreams, got %d", len(servers))
	}
}
