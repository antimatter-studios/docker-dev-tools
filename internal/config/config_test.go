package config

import (
	"os"
	"path/filepath"
	"testing"
)

func TestConfigDirDefaultsToXDG(t *testing.T) {
	dir := t.TempDir()
	t.Setenv("HOME", dir)
	t.Setenv("XDG_CONFIG_HOME", "")

	got := ConfigDir()
	want := filepath.Join(dir, ".config", "docker-dev-tools")
	if got != want {
		t.Errorf("ConfigDir() = %q, want %q", got, want)
	}
}

func TestConfigDirRespectsXDGEnv(t *testing.T) {
	xdg := t.TempDir()
	t.Setenv("XDG_CONFIG_HOME", xdg)

	got := ConfigDir()
	want := filepath.Join(xdg, "docker-dev-tools")
	if got != want {
		t.Errorf("ConfigDir() = %q, want %q", got, want)
	}
}

func TestConfigPath(t *testing.T) {
	dir := t.TempDir()
	t.Setenv("HOME", dir)
	t.Setenv("XDG_CONFIG_HOME", "")

	got := ConfigPath()
	want := filepath.Join(dir, ".config", "docker-dev-tools", "config.json")
	if got != want {
		t.Errorf("ConfigPath() = %q, want %q", got, want)
	}
}

func TestSaveJSONCreatesDirectories(t *testing.T) {
	dir := t.TempDir()
	path := filepath.Join(dir, "nested", "deep", "config.json")

	data := map[string]string{"key": "value"}
	if err := SaveJSON(path, data); err != nil {
		t.Fatalf("SaveJSON failed: %v", err)
	}

	// Verify file exists and directories were created.
	if _, err := os.Stat(path); os.IsNotExist(err) {
		t.Error("expected file to exist after SaveJSON")
	}
}
