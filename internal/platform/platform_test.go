package platform

import (
	"testing"
)

func TestCheckPortAvailableOpen(t *testing.T) {
	// Port 0 tells the OS to pick a free port, so this should always succeed
	// on a normal system. We use 127.0.0.1 to avoid firewall prompts.
	// Just verify the function runs without error on a known-open port range.
	err := CheckPortAvailable("127.0.0.1", 0)
	if err != nil {
		t.Errorf("expected no error for port 0 (OS picks free port), got: %v", err)
	}
}

func TestDetectReturnsPlatform(t *testing.T) {
	plat := Detect()
	if plat == nil {
		t.Fatal("Detect() returned nil")
	}
	name := plat.Name()
	if name != "darwin" && name != "linux" {
		t.Errorf("unexpected platform name: %s", name)
	}
}
