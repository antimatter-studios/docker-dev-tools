package service

import (
	"path/filepath"
	"slices"
	"testing"

	"github.com/moby/moby/api/types/mount"

	"github.com/christhomas/docker-dev-tools/internal/ca"
	"github.com/christhomas/docker-dev-tools/internal/config"
)

func testConfig() *config.SystemConfig {
	cfg := &config.SystemConfig{}
	cfg.Proxy.ContainerName = "ddt-proxy"
	cfg.Proxy.DockerImage = "proxy:test"
	cfg.ConfigGen.DockerImage = "config-gen:test"
	return cfg
}

func mountAt(mounts []mount.Mount, target string) (mount.Mount, bool) {
	for _, m := range mounts {
		if m.Target == target {
			return m, true
		}
	}
	return mount.Mount{}, false
}

// config-gen writes certificate paths into the proxy's configuration, so it only works
// if both containers see the certs volume at the same path.
func TestConfigGenAndProxyShareTheCertsVolume(t *testing.T) {
	_, genHost := configGenSpec(testConfig(), t.TempDir())
	_, proxyHost := proxySpec(testConfig())

	gen, ok := mountAt(genHost.Mounts, certsDir)
	if !ok || gen.Source != proxyCertsVol || gen.ReadOnly {
		t.Errorf("config-gen certs mount = %+v, want %s writable at %s", gen, proxyCertsVol, certsDir)
	}
	proxy, ok := mountAt(proxyHost.Mounts, certsDir)
	if !ok || proxy.Source != proxyCertsVol {
		t.Errorf("proxy certs mount = %+v, want %s at %s", proxy, proxyCertsVol, certsDir)
	}
}

func TestConfigGenGetsTheCAReadOnlyWhenThereIsOne(t *testing.T) {
	caDir := filepath.Join(t.TempDir(), "ca")
	if _, err := ca.Create(caDir, []string{"localhost"}); err != nil {
		t.Fatal(err)
	}
	cfg := testConfig()
	cfg.DNS.TLDs = []string{"localhost"}

	genCfg, genHost := configGenSpec(cfg, caDir)
	m, ok := mountAt(genHost.Mounts, configGenCADir)
	if !ok || m.Type != mount.TypeBind || m.Source != caDir || !m.ReadOnly {
		t.Errorf("CA mount = %+v, want %s bound read-only at %s", m, caDir, configGenCADir)
	}
	for _, want := range []string{"CA_DIR=" + configGenCADir, "CERTS_DIR=" + certsDir} {
		if !slices.Contains(genCfg.Env, want) {
			t.Errorf("config-gen env lacks %s: %v", want, genCfg.Env)
		}
	}
}

// Docker refuses to create a container whose bind-mount source doesn't exist, so a
// machine without a CA must still get a config-gen, just without HTTPS.
func TestConfigGenStartsWithoutACA(t *testing.T) {
	_, genHost := configGenSpec(testConfig(), filepath.Join(t.TempDir(), "missing"))
	if m, ok := mountAt(genHost.Mounts, configGenCADir); ok {
		t.Errorf("mounted a CA directory that doesn't exist: %+v", m)
	}
}

// The CA key can vouch for every development host, so only config-gen may hold it.
func TestProxyNeverGetsTheCA(t *testing.T) {
	_, proxyHost := proxySpec(testConfig())
	for _, m := range proxyHost.Mounts {
		if m.Target == configGenCADir || m.Type == mount.TypeBind {
			t.Errorf("the proxy has a mount it should not: %+v", m)
		}
	}
}
