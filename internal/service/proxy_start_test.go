package service

import (
	"context"
	"encoding/json"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/christhomas/docker-dev-tools/internal/ca"
	"github.com/christhomas/docker-dev-tools/internal/config"
	"github.com/christhomas/docker-dev-tools/internal/docker"
)

// `ddt proxy start` starts config-gen through StartConfigGenContainer, not Start, so the
// CA has to be made wherever config-gen is started. Otherwise that path starts config-gen
// without a CA and every host stays HTTP-only.
func TestStartingConfigGenCreatesAndMountsTheCA(t *testing.T) {
	t.Setenv("XDG_CONFIG_HOME", t.TempDir())

	// A Docker daemon that answers only what starting a container needs, and keeps the
	// container that was asked for.
	var created struct {
		HostConfig struct {
			Mounts []struct{ Source, Target string }
		}
	}
	daemon := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch {
		case strings.HasSuffix(r.URL.Path, "/_ping"):
			w.Header().Set("API-Version", "1.47")
			w.WriteHeader(http.StatusOK)
		case r.Method == http.MethodPost && strings.HasSuffix(r.URL.Path, "/containers/create"):
			if err := json.NewDecoder(r.Body).Decode(&created); err != nil {
				t.Errorf("decoding the container: %v", err)
			}
			w.Header().Set("Content-Type", "application/json")
			w.WriteHeader(http.StatusCreated)
			_, _ = io.WriteString(w, `{"Id":"config-gen","Warnings":[]}`)
		case r.Method == http.MethodPost && strings.HasSuffix(r.URL.Path, "/containers/config-gen/start"):
			w.WriteHeader(http.StatusNoContent)
		default:
			t.Errorf("unexpected request %s %s", r.Method, r.URL.Path)
			http.Error(w, "not faked", http.StatusNotFound)
		}
	}))
	defer daemon.Close()
	t.Setenv("DOCKER_HOST", "tcp://"+daemon.Listener.Addr().String())
	t.Setenv("DOCKER_TLS_VERIFY", "")
	t.Setenv("DOCKER_CERT_PATH", "")
	t.Setenv("DOCKER_API_VERSION", "")

	cfg := config.DefaultSystemConfig()
	cfg.DNS.TLDs = []string{"test"}
	svc := NewProxyService(cfg, docker.NewClient())

	if err := svc.StartConfigGenContainer(context.Background()); err != nil {
		t.Fatal(err)
	}

	if _, err := ca.Load(config.CADir()); err != nil {
		t.Fatalf("starting config-gen left no CA: %v", err)
	}
	for _, m := range created.HostConfig.Mounts {
		if m.Source == config.CADir() && m.Target == configGenCADir {
			return
		}
	}
	t.Errorf("config-gen was started without the CA mounted at %s; mounts: %+v", configGenCADir, created.HostConfig.Mounts)
}
