package service

import "testing"

func routeFor(t *testing.T, labels map[string]string) labelRoute {
	t.Helper()
	routes := labelRoutes(labels)
	if len(routes) != 1 {
		t.Fatalf("want one route, got %d: %+v", len(routes), routes)
	}
	return routes[0]
}

func TestLabelRoutesDefaults(t *testing.T) {
	got := routeFor(t, map[string]string{"docker-proxy.web.host": "web.localhost"})
	want := labelRoute{Host: "web.localhost", Port: "80", Proto: "http", Path: "/"}
	if got != want {
		t.Fatalf("got %+v, want %+v", got, want)
	}
}

// The upstream scheme is .protocol, as docker-config-gen reads it (#9).
func TestLabelRoutesReadTheSchemeFromProtocol(t *testing.T) {
	got := routeFor(t, map[string]string{
		"docker-proxy.api.host":     "api.localhost",
		"docker-proxy.api.port":     "8443",
		"docker-proxy.api.protocol": "https",
	})
	if got.Proto != "https" {
		t.Fatalf("scheme = %q, want https", got.Proto)
	}
}

// .proto marks a raw TCP or UDP stream, which is not an HTTP route (#9).
func TestLabelRoutesSkipStreamGroups(t *testing.T) {
	routes := labelRoutes(map[string]string{
		"docker-proxy.db.host":  "db.localhost",
		"docker-proxy.db.proto": "tcp",
		"docker-proxy.db.port":  "5432",
	})
	if len(routes) != 0 {
		t.Fatalf("stream group listed as an HTTP route: %+v", routes)
	}
}
