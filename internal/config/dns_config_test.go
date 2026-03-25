package config

import "testing"

func TestDNSPortOrDefault(t *testing.T) {
	tests := []struct {
		name string
		port int
		want int
	}{
		{"zero returns default", 0, 10053},
		{"explicit port used", 5353, 5353},
		{"standard DNS port", 53, 53},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			d := &DNSConfig{Port: tt.port}
			if got := d.PortOrDefault(); got != tt.want {
				t.Errorf("PortOrDefault() = %d, want %d", got, tt.want)
			}
		})
	}
}

func TestExtractTLD(t *testing.T) {
	tests := []struct {
		domain string
		want   string
	}{
		{"restmail.localhost", "localhost"},
		{"foo.bar.develop", "develop"},
		{"localhost", "localhost"},
		{"a.b.c.test", "test"},
	}
	for _, tt := range tests {
		t.Run(tt.domain, func(t *testing.T) {
			if got := extractTLD(tt.domain); got != tt.want {
				t.Errorf("extractTLD(%q) = %q, want %q", tt.domain, got, tt.want)
			}
		})
	}
}

func TestMigrateDomainsTLDs(t *testing.T) {
	dir := t.TempDir()
	path := dir + "/test.json"

	cfg := DefaultSystemConfig()
	cfg.path = path
	cfg.DNS.TLDs = []string{"localhost"}
	cfg.DNS.Domains = map[string][]string{
		"group1": {"api.develop", "web.develop"},
		"group2": {"mail.localhost"}, // localhost already in TLDs
	}

	cfg.migrateDomainsTLDs()

	// Should have added "develop" and kept "localhost".
	if len(cfg.DNS.TLDs) != 2 {
		t.Errorf("expected 2 TLDs, got %d: %v", len(cfg.DNS.TLDs), cfg.DNS.TLDs)
	}

	found := make(map[string]bool)
	for _, tld := range cfg.DNS.TLDs {
		found[tld] = true
	}
	if !found["localhost"] {
		t.Error("expected 'localhost' TLD to remain")
	}
	if !found["develop"] {
		t.Error("expected 'develop' TLD to be added from migration")
	}

	// Domains should be cleared.
	if cfg.DNS.Domains != nil {
		t.Error("expected Domains to be nil after migration")
	}
}
