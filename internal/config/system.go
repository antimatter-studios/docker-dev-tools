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

	// imageNotes records any docker_image the load reconciled, for the caller to
	// report. Replacing a value silently is how someone loses a local image build
	// without ever being told which one.
	imageNotes []string
}

// ImageNotes returns a line per docker_image that loading brought into line with this
// binary's pinned digests. Empty unless something changed.
func (c *SystemConfig) ImageNotes() []string { return c.imageNotes }

// DNSConfig holds DNS server container settings.
type DNSConfig struct {
	DockerImage string `json:"docker_image"`
	// CustomImage marks DockerImage as the user's own, so ddt leaves it alone instead
	// of reconciling it against the digest this binary was built with. Additive and
	// absent by default: a missing key decodes to false, which is the wanted default.
	CustomImage   bool     `json:"custom_image,omitempty"`
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
	DockerImage string `json:"docker_image"`
	// CustomImage: see DNSConfig.CustomImage.
	CustomImage   bool   `json:"custom_image,omitempty"`
	ContainerName string `json:"container_name"`
}

// ConfigGenConfig holds the config generator container settings.
type ConfigGenConfig struct {
	DockerImage string `json:"docker_image"`
	// CustomImage: see DNSConfig.CustomImage.
	CustomImage   bool   `json:"custom_image,omitempty"`
	ContainerName string `json:"container_name"`
}

// ProjectsConfig holds project path and list configuration.
type ProjectsConfig struct {
	Paths map[string]string       `json:"paths"`
	List  map[string]ProjectEntry `json:"list"`
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

// LoadOrDefault loads the system config from the XDG config path,
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
	cfg.reconcileImages()
	return cfg
}

// reconcileImages brings each service's docker_image into line with the digests this
// binary was built against, and persists the result.
//
// It runs on every load because the config file outlives the binary: a config written by
// an older ddt records whatever that version used — usually `:latest` — so without this
// a release that pins digests would still run floating images for every existing user.
// Blocks marked custom_image are left alone. See ReconcileImage.
func (c *SystemConfig) reconcileImages() {
	changed := false
	reconcile := func(service string, current *string, expected string, custom bool) {
		next, note := ReconcileImage(service, *current, expected, custom)
		if next != *current {
			*current = next
			changed = true
		}
		if note != "" {
			c.imageNotes = append(c.imageNotes, note)
		}
	}

	reconcile("dns", &c.DNS.DockerImage, DNSImage, c.DNS.CustomImage)
	reconcile("proxy", &c.Proxy.DockerImage, ProxyImage, c.Proxy.CustomImage)
	reconcile("config_gen", &c.ConfigGen.DockerImage, ConfigGenImage, c.ConfigGen.CustomImage)

	if changed {
		_ = c.Save()
	}
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
