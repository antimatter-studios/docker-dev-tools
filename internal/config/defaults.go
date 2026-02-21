package config

// DefaultSystemConfig returns the default system configuration,
// matching the structure from default.ddt-system.json.
func DefaultSystemConfig() *SystemConfig {
	return &SystemConfig{
		Description: "The Tools Configuration file",
		Type:        "system",
		Version:     CurrentVersion,
		IPAddress:   "10.254.254.254",
		DNS: DNSConfig{
			DockerImage:   "ghcr.io/antimatter-studios/docker-dns:latest",
			ContainerName: "ddt-dns",
			Port:          10053,
			Upstream:      []string{},
		},
		Proxy: ProxyConfig{
			DockerImage:   "ghcr.io/antimatter-studios/docker-proxy:latest",
			ContainerName: "ddt-proxy",
			Network:       []string{},
		},
		ConfigGen: ConfigGenConfig{
			DockerImage:   "ghcr.io/antimatter-studios/docker-config-gen:latest",
			ContainerName: "ddt-config-gen",
			Network:       []string{},
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
