package service

import (
	"strings"
	"testing"

	"github.com/christhomas/docker-dev-tools/internal/config"
)

// pinnedDigest is an image reference a release build pins, which is what makes loading
// the config replace any image not marked as the user's own.
func pinnedDigest(repo string) string {
	return "ghcr.io/antimatter-studios/" + repo + "@sha256:" + strings.Repeat("a", 64)
}

// The point of choosing an image by command is to run it. A release build reconciles
// images against its pinned digests on every load, so a choice that isn't marked as the
// user's own is silently undone the next time ddt runs.
func TestSetConfigGenImageSurvivesAPinnedRelease(t *testing.T) {
	t.Setenv("XDG_CONFIG_HOME", t.TempDir())
	old := config.ConfigGenImage
	config.ConfigGenImage = pinnedDigest("docker-config-gen")
	t.Cleanup(func() { config.ConfigGenImage = old })

	if err := NewProxyService(config.LoadOrDefault(), nil).SetConfigGenImage("ddt-local/docker-config-gen:tls"); err != nil {
		t.Fatal(err)
	}
	if got := config.LoadOrDefault().ConfigGen.DockerImage; got != "ddt-local/docker-config-gen:tls" {
		t.Errorf("after reloading, the config-gen image is %s", got)
	}
}

func TestSetImageSurvivesAPinnedRelease(t *testing.T) {
	t.Setenv("XDG_CONFIG_HOME", t.TempDir())
	old := config.ProxyImage
	config.ProxyImage = pinnedDigest("docker-proxy")
	t.Cleanup(func() { config.ProxyImage = old })

	if err := NewProxyService(config.LoadOrDefault(), nil).SetImage("ddt-local/docker-proxy:tls"); err != nil {
		t.Fatal(err)
	}
	if got := config.LoadOrDefault().Proxy.DockerImage; got != "ddt-local/docker-proxy:tls" {
		t.Errorf("after reloading, the proxy image is %s", got)
	}
}
