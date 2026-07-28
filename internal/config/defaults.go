package config

// The images ddt runs, resolved to immutable digests when a release is built.
//
// A release stamps these via ldflags with the digest each image had at build time, so a
// given ddt binary always runs exactly the components it was released against:
//
//	-X …/internal/config.ProxyImage=ghcr.io/antimatter-studios/docker-proxy@sha256:…
//
// `:latest` remains the default so a build from source keeps working, but it is not
// what ships. Floating tags meant two components that are only correct TOGETHER could
// be mixed: a proxy image expecting a template variable its generator does not supply
// yet, for instance, which produced an nginx config that would not load.
//
// The digest pinned is the manifest LIST digest, so it still resolves per architecture.
// A user hacking on one of these components can point the config file at a local tag —
// this is a default, not a lock.
var (
	DNSImage       = "ghcr.io/antimatter-studios/docker-dns:latest"
	ProxyImage     = "ghcr.io/antimatter-studios/docker-proxy:latest"
	ConfigGenImage = "ghcr.io/antimatter-studios/docker-config-gen:latest"
)

// DefaultSystemConfig returns the default system configuration.
func DefaultSystemConfig() *SystemConfig {
	return &SystemConfig{
		Description: "The Tools Configuration file",
		Type:        "system",
		Version:     CurrentVersion,
		IPAddress:   "10.254.254.254",
		DNS: DNSConfig{
			DockerImage:   DNSImage,
			ContainerName: "ddt-dns",
			Port:          10053,
			Upstream:      []string{},
		},
		Proxy: ProxyConfig{
			DockerImage:   ProxyImage,
			ContainerName: "ddt-proxy",
		},
		ConfigGen: ConfigGenConfig{
			DockerImage:   ConfigGenImage,
			ContainerName: "ddt-config-gen",
		},
		Projects: ProjectsConfig{
			Paths: map[string]string{},
			List:  map[string]ProjectEntry{},
		},
		SelfUpdate: SelfUpdateConfig{
			Timeout: 0,
			Period:  "+7 day",
			Enabled: true,
		},
	}
}
