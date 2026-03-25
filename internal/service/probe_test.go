package service

import (
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
)

func TestProbeServices(t *testing.T) {
	okServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
	}))
	defer okServer.Close()

	notFoundServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusNotFound)
	}))
	defer notFoundServer.Close()

	redirectServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Location", "/other")
		w.WriteHeader(http.StatusMovedPermanently)
	}))
	defer redirectServer.Close()

	okHost := strings.TrimPrefix(okServer.URL, "http://")
	notFoundHost := strings.TrimPrefix(notFoundServer.URL, "http://")
	redirectHost := strings.TrimPrefix(redirectServer.URL, "http://")

	entries := []ProxyStatusEntry{
		{Proto: "http", Host: okHost, Container: "app1", Network: "net1"},
		{Proto: "http", Host: notFoundHost, Container: "app2", Network: "net1"},
		{Proto: "http", Host: redirectHost, Container: "app3", Network: "net1"},
		{Proto: "http", Host: "127.0.0.1:1", Container: "dead", Network: "net1"},
	}

	results := ProbeServices(entries)

	if len(results) != len(entries) {
		t.Fatalf("expected %d results, got %d", len(entries), len(results))
	}

	if results[0].StatusCode != 200 {
		t.Errorf("expected 200, got %d", results[0].StatusCode)
	}
	if results[0].Fallback {
		t.Error("200 should not be fallback")
	}

	if results[1].StatusCode != 404 {
		t.Errorf("expected 404, got %d", results[1].StatusCode)
	}

	if results[2].StatusCode != 301 {
		t.Errorf("expected 301, got %d", results[2].StatusCode)
	}

	if results[3].Status != "error" {
		t.Errorf("expected 'error', got %q", results[3].Status)
	}
}

func TestProbeServicesEmpty(t *testing.T) {
	results := ProbeServices(nil)
	if len(results) != 0 {
		t.Errorf("expected 0 results for nil input, got %d", len(results))
	}
}

func TestProbeServicesWithPath(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path == "/api" {
			w.WriteHeader(http.StatusOK)
		} else {
			w.WriteHeader(http.StatusNotFound)
		}
	}))
	defer server.Close()

	host := strings.TrimPrefix(server.URL, "http://")

	entries := []ProxyStatusEntry{
		{Proto: "http", Host: host, Path: "/api", Container: "api", Network: "net1"},
		{Proto: "http", Host: host, Path: "/missing", Container: "web", Network: "net1"},
	}

	results := ProbeServices(entries)

	if results[0].StatusCode != 200 {
		t.Errorf("expected 200 for /api, got %d", results[0].StatusCode)
	}
	if results[1].StatusCode != 404 {
		t.Errorf("expected 404 for /missing, got %d", results[1].StatusCode)
	}
}

func TestProbeServicesFallbackDetection(t *testing.T) {
	fallbackServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("X-Proxy-Upstream-1", "eyJjb250YWluZXIiOiJ0ZXN0In0=")
		w.WriteHeader(http.StatusOK)
	}))
	defer fallbackServer.Close()

	normalServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
	}))
	defer normalServer.Close()

	entries := []ProxyStatusEntry{
		{Proto: "http", Host: strings.TrimPrefix(fallbackServer.URL, "http://"), Container: "broken", Network: "net1"},
		{Proto: "http", Host: strings.TrimPrefix(normalServer.URL, "http://"), Container: "healthy", Network: "net1"},
	}

	results := ProbeServices(entries)

	if !results[0].Fallback {
		t.Error("response with x-proxy-upstream header should be detected as fallback")
	}
	if results[1].Fallback {
		t.Error("normal response should not be detected as fallback")
	}
}

func TestIsProxyFallback(t *testing.T) {
	tests := []struct {
		name    string
		headers map[string]string
		want    bool
	}{
		{"no headers", nil, false},
		{"unrelated header", map[string]string{"Server": "nginx"}, false},
		{"x-proxy-upstream-1", map[string]string{"X-Proxy-Upstream-1": "data"}, true},
		{"x-proxy-upstream-2", map[string]string{"X-Proxy-Upstream-2": "data"}, true},
		{"lowercase match", map[string]string{"x-proxy-upstream-1": "data"}, true},
		{"mixed with other headers", map[string]string{
			"Content-Type":       "text/html",
			"X-Proxy-Upstream-1": "data",
			"Server":             "nginx",
		}, true},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			resp := &http.Response{Header: make(http.Header)}
			for k, v := range tt.headers {
				resp.Header.Set(k, v)
			}
			got := IsProxyFallback(resp)
			if got != tt.want {
				t.Errorf("IsProxyFallback() = %v, want %v", got, tt.want)
			}
		})
	}
}
