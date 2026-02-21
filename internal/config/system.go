package config

import (
	"os"
	"strings"
)

// SystemConfig represents the top-level system configuration.
type SystemConfig struct {
	Description string           `json:"description,omitempty"`
	Type        string           `json:"type"`
	Version     string           `json:"version"`
	IPAddress   string           `json:"ip_address"`
	DNS         DNSConfig        `json:"dns"`
	Proxy       ProxyConfig      `json:"proxy"`
	ConfigGen   ConfigGenConfig  `json:"config_gen"`
	Projects    ProjectsConfig   `json:"projects"`
	SelfUpdate  SelfUpdateConfig `json:"self_update"`

	// path is the file this config was loaded from.
	path string
}

// DNSConfig holds DNS server container settings.
type DNSConfig struct {
	DockerImage   string   `json:"docker_image"`
	ContainerName string   `json:"container_name"`
	Port          int      `json:"port,omitempty"`
	TLDs          []string `json:"tlds,omitempty"`
	Upstream      []string `json:"upstream,omitempty"`

	// Deprecated: Domains is only kept for migration. New code uses TLDs exclusively.
	Domains map[string][]string `json:"domains,omitempty"`
}

// PortOrDefault returns the configured DNS port, defaulting to 5353.
func (d *DNSConfig) PortOrDefault() int {
	if d.Port == 0 {
		return 10053
	}
	return d.Port
}

// ProxyConfig holds reverse proxy container settings.
type ProxyConfig struct {
	DockerImage   string   `json:"docker_image"`
	ContainerName string   `json:"container_name"`
	Network       []string `json:"network"`
}

// ConfigGenConfig holds the config generator container settings.
type ConfigGenConfig struct {
	DockerImage   string   `json:"docker_image"`
	ContainerName string   `json:"container_name"`
	Network       []string `json:"network"`
}

// ProjectsConfig holds project path and list configuration.
type ProjectsConfig struct {
	Paths map[string]string         `json:"paths"`
	List  map[string]ProjectEntry   `json:"list"`
}

// ProjectEntry represents a single project in the system config.
type ProjectEntry struct {
	Path  string `json:"path"`
	Group string `json:"group,omitempty"`
}

// SelfUpdateConfig holds auto-update settings.
type SelfUpdateConfig struct {
	Timeout int    `json:"timeout"`
	Period  string `json:"period"`
	Enabled bool   `json:"enabled"`
}

// Path returns the file path this config was loaded from.
func (c *SystemConfig) Path() string {
	return c.path
}

// Save writes the config back to disk.
func (c *SystemConfig) Save() error {
	return SaveJSON(c.path, c)
}

// LoadOrDefault loads the system config from ~/.ddt-system.json,
// falling back to defaults if the file doesn't exist.
func LoadOrDefault() *SystemConfig {
	path := ConfigPath()

	cfg := &SystemConfig{}
	if err := LoadJSON(path, cfg); err != nil {
		if os.IsNotExist(err) {
			cfg = DefaultSystemConfig()
			cfg.path = path
			return cfg
		}
		// On parse errors, start from defaults too.
		cfg = DefaultSystemConfig()
		cfg.path = path
		return cfg
	}

	cfg.path = path
	cfg.migrateDomainsTLDs()
	return cfg
}

// migrateDomainsTLDs converts any legacy per-domain entries into wildcard TLDs
// and clears the Domains map. For example, "restmail.localhost" becomes the TLD
// "localhost". The config is saved if any migration occurs.
func (c *SystemConfig) migrateDomainsTLDs() {
	if len(c.DNS.Domains) == 0 {
		return
	}

	existing := make(map[string]bool, len(c.DNS.TLDs))
	for _, t := range c.DNS.TLDs {
		existing[t] = true
	}

	changed := false
	for _, domains := range c.DNS.Domains {
		for _, domain := range domains {
			tld := extractTLD(domain)
			if tld != "" && !existing[tld] {
				c.DNS.TLDs = append(c.DNS.TLDs, tld)
				existing[tld] = true
				changed = true
			}
		}
	}

	// Clear legacy domains.
	c.DNS.Domains = nil
	changed = true

	if changed {
		_ = c.Save()
	}
}

// extractTLD returns the top-level portion of a domain name.
// "restmail.localhost" → "localhost", "foo.bar.develop" → "develop", "localhost" → "localhost".
func extractTLD(domain string) string {
	if i := strings.LastIndex(domain, "."); i >= 0 {
		return domain[i+1:]
	}
	return domain
}
