package cli

import (
	"os"
	"testing"

	"github.com/christhomas/docker-dev-tools/internal/app"
	"github.com/christhomas/docker-dev-tools/internal/config"
)

// `ddt config reset` failed with "writing config : open : no such file or directory":
// the default configuration it saved carried no path to save to.
func TestConfigResetWritesTheDefaults(t *testing.T) {
	t.Setenv("XDG_CONFIG_HOME", t.TempDir())

	cmd := newConfigCmd(&app.App{Config: config.LoadOrDefault()})
	cmd.SetArgs([]string{"reset"})
	if err := cmd.Execute(); err != nil {
		t.Fatalf("config reset: %v", err)
	}

	if _, err := os.Stat(config.ConfigPath()); err != nil {
		t.Fatalf("no config written at %s: %v", config.ConfigPath(), err)
	}
	if got, want := config.LoadOrDefault().IPAddress, config.DefaultSystemConfig().IPAddress; got != want {
		t.Errorf("the reset config has IP address %q, want the default %q", got, want)
	}
}
