package service

import (
	"github.com/docker/docker/api/types/container"
	"github.com/docker/docker/api/types/mount"
	nat "github.com/docker/go-connections/nat"

	"github.com/christhomas/docker-dev-tools/internal/config"
)

const (
	// certsDir is where the host certificates live, in the proxy and in config-gen
	// alike: config-gen writes these paths into the proxy's configuration, so the
	// volume must be mounted at the same place in both.
	certsDir = "/etc/nginx/certs"
	// configGenCADir is where config-gen finds ddt's CA.
	configGenCADir = "/etc/docker-config-gen/ca"
)

// configGenSpec is the config-gen container. When caDir holds a CA usable for the
// configured TLDs (see usableCA), it is mounted read-only, so config-gen can issue host
// certificates and the proxy serves HTTPS; otherwise every host stays HTTP-only.
func configGenSpec(cfg *config.SystemConfig, caDir string) (*container.Config, *container.HostConfig) {
	mounts := []mount.Mount{
		{Type: mount.TypeBind, Source: "/var/run/docker.sock", Target: "/var/run/docker.sock", ReadOnly: true},
		{Type: mount.TypeVolume, Source: managementVol, Target: "/var/run/proxy"},
		{Type: mount.TypeVolume, Source: proxyCertsVol, Target: certsDir},
	}
	if usableCA(caDir, cfg.DNS.TLDs) {
		mounts = append(mounts, mount.Mount{Type: mount.TypeBind, Source: caDir, Target: configGenCADir, ReadOnly: true})
	}

	return &container.Config{
			Image: cfg.ConfigGen.DockerImage,
			Env: []string{
				"MANAGEMENT_SOCKET=" + managementSockPath,
				"RENDERER=nginx",
				"PROXY_CONTAINER=" + cfg.Proxy.ContainerName,
				"CA_DIR=" + configGenCADir,
				"CERTS_DIR=" + certsDir,
			},
		},
		&container.HostConfig{
			RestartPolicy: container.RestartPolicy{Name: "always"},
			Mounts:        mounts,
		}
}

// proxySpec is the proxy container. It gets the host certificates but never the CA.
func proxySpec(cfg *config.SystemConfig) (*container.Config, *container.HostConfig) {
	return &container.Config{
			Image: cfg.Proxy.DockerImage,
			ExposedPorts: nat.PortSet{
				"80/tcp":  struct{}{},
				"443/tcp": struct{}{},
			},
		},
		&container.HostConfig{
			RestartPolicy: container.RestartPolicy{Name: "always"},
			PortBindings: nat.PortMap{
				"80/tcp":  []nat.PortBinding{{HostPort: "80"}},
				"443/tcp": []nat.PortBinding{{HostPort: "443"}},
			},
			Mounts: []mount.Mount{
				{Type: mount.TypeVolume, Source: managementVol, Target: "/var/run/proxy"},
				{Type: mount.TypeVolume, Source: proxyCertsVol, Target: certsDir},
				{Type: mount.TypeVolume, Source: proxyVhostVol, Target: "/etc/nginx/vhost.d"},
				{Type: mount.TypeVolume, Source: proxyHTMLVol, Target: "/usr/share/nginx/html"},
			},
		}
}
