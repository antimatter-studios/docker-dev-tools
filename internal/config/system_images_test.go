package config

import (
	"encoding/json"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

// writeConfig puts a system config on disk in an isolated XDG dir and points
// ConfigPath() at it, so LoadOrDefault can be exercised without touching ~/.config.
func writeConfig(t *testing.T, body string) string {
	t.Helper()
	dir := t.TempDir()
	t.Setenv("XDG_CONFIG_HOME", dir)
	path := filepath.Join(dir, ConfigDirName, SystemConfigFilename)
	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(path, []byte(body), 0o644); err != nil {
		t.Fatal(err)
	}
	return path
}

func reload(t *testing.T, path string) map[string]map[string]any {
	t.Helper()
	data, err := os.ReadFile(path)
	if err != nil {
		t.Fatal(err)
	}
	var raw map[string]map[string]any
	if err := json.Unmarshal(data, &raw); err != nil {
		// The file also holds non-object keys (version, ip_address); decode loosely.
		var loose map[string]any
		if err2 := json.Unmarshal(data, &loose); err2 != nil {
			t.Fatalf("saved config is not valid JSON: %v", err2)
		}
		raw = map[string]map[string]any{}
		for k, v := range loose {
			if m, ok := v.(map[string]any); ok {
				raw[k] = m
			}
		}
	}
	return raw
}

// The whole point of pinning: a config written by an older ddt records `:latest`, and
// unless loading corrects it a release that pins digests still runs floating images for
// everyone who already has a config file.
func TestLoadReplacesStaleImages(t *testing.T) {
	stale := "ghcr.io/antimatter-studios/docker-proxy:latest"
	pinned := "ghcr.io/antimatter-studios/docker-proxy@sha256:" +
		"9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08"

	old := ProxyImage
	ProxyImage = pinned
	t.Cleanup(func() { ProxyImage = old })

	path := writeConfig(t, `{
	  "type": "system", "version": "`+CurrentVersion+`", "ip_address": "10.254.254.254",
	  "dns":        {"docker_image": "`+DNSImage+`", "container_name": "ddt-dns"},
	  "proxy":      {"docker_image": "`+stale+`", "container_name": "ddt-proxy"},
	  "config_gen": {"docker_image": "`+ConfigGenImage+`", "container_name": "ddt-config-gen"}
	}`)

	cfg := LoadOrDefault()
	if cfg.Proxy.DockerImage != pinned {
		t.Errorf("in-memory proxy image = %q, want the pinned %q", cfg.Proxy.DockerImage, pinned)
	}
	if len(cfg.ImageNotes()) != 1 {
		t.Errorf("ImageNotes() = %v, want exactly one note about proxy", cfg.ImageNotes())
	}

	// Persisted, not just corrected in memory — otherwise every run re-reports it.
	saved := reload(t, path)
	if got := saved["proxy"]["docker_image"]; got != pinned {
		t.Errorf("saved proxy image = %v, want %q", got, pinned)
	}
	// An untouched service must not gain a custom_image key: the field is additive and
	// ddt writing it would make every config claim its images are user-owned.
	if _, ok := saved["dns"]["custom_image"]; ok {
		t.Errorf("ddt wrote custom_image into a block it did not need to: %v", saved["dns"])
	}
}

func TestLoadRespectsCustomImage(t *testing.T) {
	old := ProxyImage
	ProxyImage = "ghcr.io/antimatter-studios/docker-proxy@sha256:aaa"
	t.Cleanup(func() { ProxyImage = old })

	path := writeConfig(t, `{
	  "type": "system", "version": "`+CurrentVersion+`", "ip_address": "10.254.254.254",
	  "dns":        {"docker_image": "`+DNSImage+`", "container_name": "ddt-dns"},
	  "proxy":      {"docker_image": "my-local-proxy:dev", "custom_image": true, "container_name": "ddt-proxy"},
	  "config_gen": {"docker_image": "`+ConfigGenImage+`", "container_name": "ddt-config-gen"}
	}`)

	cfg := LoadOrDefault()
	if cfg.Proxy.DockerImage != "my-local-proxy:dev" {
		t.Errorf("custom image was overwritten: %q", cfg.Proxy.DockerImage)
	}
	if len(cfg.ImageNotes()) != 0 {
		t.Errorf("ImageNotes() = %v, want none — nothing was changed", cfg.ImageNotes())
	}

	saved := reload(t, path)
	if got := saved["proxy"]["docker_image"]; got != "my-local-proxy:dev" {
		t.Errorf("saved proxy image = %v, want the user's own", got)
	}
	if got := saved["proxy"]["custom_image"]; got != true {
		t.Errorf("custom_image did not survive a save: %v", got)
	}
}

// A build from source stamps nothing, so every image is the plain `:latest` default and
// there is nothing to reconcile. Rewriting the file on every such run would churn it.
func TestLoadFromSourceBuildLeavesConfigAlone(t *testing.T) {
	body := `{
	  "type": "system", "version": "` + CurrentVersion + `", "ip_address": "10.254.254.254",
	  "dns":        {"docker_image": "` + DNSImage + `", "container_name": "ddt-dns"},
	  "proxy":      {"docker_image": "` + ProxyImage + `", "container_name": "ddt-proxy"},
	  "config_gen": {"docker_image": "` + ConfigGenImage + `", "container_name": "ddt-config-gen"}
	}`
	path := writeConfig(t, body)

	before, err := os.ReadFile(path)
	if err != nil {
		t.Fatal(err)
	}
	cfg := LoadOrDefault()
	if len(cfg.ImageNotes()) != 0 {
		t.Errorf("ImageNotes() = %v, want none", cfg.ImageNotes())
	}
	after, err := os.ReadFile(path)
	if err != nil {
		t.Fatal(err)
	}
	if string(before) != string(after) {
		t.Errorf("config was rewritten with nothing to change:\n%s", strings.TrimSpace(string(after)))
	}
}
