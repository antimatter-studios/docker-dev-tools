package config

import (
	"encoding/json"
	"os"
	"path/filepath"
	"testing"
)

func TestDefaultSystemConfig(t *testing.T) {
	cfg := DefaultSystemConfig()

	if cfg.Version != CurrentVersion {
		t.Errorf("expected version %s, got %s", CurrentVersion, cfg.Version)
	}
	if cfg.IPAddress != "10.254.254.254" {
		t.Errorf("expected IP 10.254.254.254, got %s", cfg.IPAddress)
	}
	if cfg.DNS.ContainerName != "ddt-dns" {
		t.Errorf("expected DNS container ddt-dns, got %s", cfg.DNS.ContainerName)
	}
	if cfg.Proxy.ContainerName != "ddt-proxy" {
		t.Errorf("expected proxy container ddt-proxy, got %s", cfg.Proxy.ContainerName)
	}
	if cfg.ConfigGen.ContainerName != "ddt-config-gen" {
		t.Errorf("expected config-gen container ddt-config-gen, got %s", cfg.ConfigGen.ContainerName)
	}
	if !cfg.SelfUpdate.Enabled {
		t.Error("expected self-update to be enabled by default")
	}
}

func TestSaveAndLoadJSON(t *testing.T) {
	dir := t.TempDir()
	path := filepath.Join(dir, "test-config.json")

	original := DefaultSystemConfig()
	original.IPAddress = "192.168.1.100"

	if err := SaveJSON(path, original); err != nil {
		t.Fatalf("SaveJSON failed: %v", err)
	}

	loaded := &SystemConfig{}
	if err := LoadJSON(path, loaded); err != nil {
		t.Fatalf("LoadJSON failed: %v", err)
	}

	if loaded.IPAddress != "192.168.1.100" {
		t.Errorf("expected IP 192.168.1.100, got %s", loaded.IPAddress)
	}
	if loaded.Version != CurrentVersion {
		t.Errorf("expected version %s, got %s", CurrentVersion, loaded.Version)
	}
}

func TestLoadOrDefaultMissingFile(t *testing.T) {
	// Temporarily override the home dir to a temp location so LoadOrDefault
	// doesn't find a real config file.
	dir := t.TempDir()
	t.Setenv("HOME", dir)

	cfg := LoadOrDefault()

	if cfg.Version != CurrentVersion {
		t.Errorf("expected version %s, got %s", CurrentVersion, cfg.Version)
	}
	if cfg.IPAddress != "10.254.254.254" {
		t.Errorf("expected default IP, got %s", cfg.IPAddress)
	}
}

func TestSystemConfigMarshalMatchesDefault(t *testing.T) {
	cfg := DefaultSystemConfig()
	data, err := json.MarshalIndent(cfg, "", "  ")
	if err != nil {
		t.Fatalf("marshal failed: %v", err)
	}

	// Verify it round-trips cleanly.
	loaded := &SystemConfig{}
	if err := json.Unmarshal(data, loaded); err != nil {
		t.Fatalf("unmarshal failed: %v", err)
	}

	if loaded.IPAddress != cfg.IPAddress {
		t.Errorf("round-trip failed for IPAddress")
	}
	if loaded.DNS.DockerImage != cfg.DNS.DockerImage {
		t.Errorf("round-trip failed for DNS.DockerImage")
	}
}

func TestLoadProjectConfigDDTFile(t *testing.T) {
	dir := t.TempDir()
	projectCfg := `{
		"scripts": {
			"start": "docker-compose up -d",
			"stop": "docker-compose stop",
			"up": ["start"]
		},
		"dependencies": {
			"service-a": {
				"repo": {
					"url": "git@github.com:org/service-a.git",
					"branch": "main"
				},
				"scripts": ["up"]
			}
		}
	}`

	if err := os.WriteFile(filepath.Join(dir, ProjectConfigFilename), []byte(projectCfg), 0644); err != nil {
		t.Fatalf("writing test config: %v", err)
	}

	cfg, err := LoadProjectConfig(dir)
	if err != nil {
		t.Fatalf("LoadProjectConfig failed: %v", err)
	}
	if cfg == nil {
		t.Fatal("expected non-nil project config")
	}
	if cfg.Dir() != dir {
		t.Errorf("expected dir %s, got %s", dir, cfg.Dir())
	}
	if len(cfg.Scripts) != 3 {
		t.Errorf("expected 3 scripts, got %d", len(cfg.Scripts))
	}
	if len(cfg.Dependencies) != 1 {
		t.Errorf("expected 1 dependency, got %d", len(cfg.Dependencies))
	}

	// Test script resolution.
	cmd, refs, err := cfg.ResolveScript("start")
	if err != nil {
		t.Fatalf("ResolveScript failed: %v", err)
	}
	if cmd != "docker-compose up -d" {
		t.Errorf("expected 'docker-compose up -d', got %q", cmd)
	}
	if refs != nil {
		t.Errorf("expected nil refs for string script, got %v", refs)
	}

	// Test array script resolution.
	cmd, refs, err = cfg.ResolveScript("up")
	if err != nil {
		t.Fatalf("ResolveScript failed: %v", err)
	}
	if cmd != "" {
		t.Errorf("expected empty command for array script, got %q", cmd)
	}
	if len(refs) != 1 || refs[0] != "start" {
		t.Errorf("expected refs [start], got %v", refs)
	}
}
