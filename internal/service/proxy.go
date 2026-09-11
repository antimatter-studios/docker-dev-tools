package service

import (
	"context"
	"fmt"
	"io"
	"os"
	"strings"

	"github.com/docker/docker/api/types/container"
	"github.com/docker/docker/api/types/mount"
	"github.com/docker/docker/api/types/network"
	nat "github.com/docker/go-connections/nat"

	"github.com/christhomas/docker-dev-tools/internal/config"
	"github.com/christhomas/docker-dev-tools/internal/docker"
)

const (
	configGenVolume    = "ddt_config_gen"
	proxyCertsVol      = "ddt_proxy_certs"
	proxyVhostVol      = "ddt_proxy_vhost"
	proxyHTMLVol       = "ddt_proxy_html"
	managementVol      = "ddt_proxy_management"
	managementSockPath = "/var/run/proxy/management.sock"
)

// ProxyService manages the reverse proxy and config-gen containers.
type ProxyService struct {
	config *config.SystemConfig
	docker *docker.Client
}

// NewProxyService creates a new proxy service.
func NewProxyService(cfg *config.SystemConfig, d *docker.Client) *ProxyService {
	return &ProxyService{config: cfg, docker: d}
}

// ProxyStopReport contains the stop/remove results for proxy and config-gen.
type ProxyStopReport struct {
	Proxy     docker.StopRemoveResult
	ConfigGen docker.StopRemoveResult
}

// ProxyStatusEntry represents a proxied service discovered on a network.
type ProxyStatusEntry struct {
	Network   string
	Container string
	Host      string
	Port      string
	Path      string
	Proto     string
}

// SidecarStatusEntry represents a sidecar container publishing TCP/UDP ports.
type SidecarStatusEntry struct {
	Container string
	Port      string
	Proto     string
	Status    string
}

type ProxyStartReport struct {
	ConfigGenStarted bool
	ProxyStarted     bool
}

// Start launches the config-gen container, then the proxy container.
//
// It no longer copies nginx configuration in: the generator writes the config, and
// proxy.conf ships inside the proxy image. Network connections are managed
// dynamically by the generator too.
func (s *ProxyService) Start(ctx context.Context, pull bool) error {
	_, err := s.StartWithReport(ctx, pull)
	return err
}

func (s *ProxyService) StartWithReport(ctx context.Context, pull bool) (ProxyStartReport, error) {
	report := ProxyStartReport{}

	// Ensure images exist locally, pulling if missing or if --pull was requested.
	if err := s.docker.EnsureImage(ctx, s.config.ConfigGen.DockerImage, pull, os.Stdout); err != nil {
		return report, fmt.Errorf("config-gen image: %w", err)
	}
	if err := s.docker.EnsureImage(ctx, s.config.Proxy.DockerImage, pull, os.Stdout); err != nil {
		return report, fmt.Errorf("proxy image: %w", err)
	}

	// Clean up any existing containers so we get a fresh start.
	s.cleanupConfigGen(ctx)
	s.cleanupProxy(ctx)

	// 1. Start config-gen (watches Docker socket, generates nginx configs).
	_, err := s.startConfigGen(ctx)
	if err != nil {
		return report, fmt.Errorf("starting config-gen: %w", err)
	}
	report.ConfigGenStarted = true

	// 2. Start the proxy (nginx).
	_, err = s.startProxy(ctx)
	if err != nil {
		return report, fmt.Errorf("starting proxy: %w", err)
	}
	report.ProxyStarted = true

	return report, nil
}

func (s *ProxyService) StopProxy(ctx context.Context) (docker.StopRemoveResult, error) {
	res, err := s.docker.StopAndRemoveContainerWithReport(ctx, s.config.Proxy.ContainerName)
	if err != nil {
		return res, fmt.Errorf("stopping proxy container %q: %w", s.config.Proxy.ContainerName, err)
	}
	return res, nil
}

func (s *ProxyService) StopConfigGen(ctx context.Context) (docker.StopRemoveResult, error) {
	res, err := s.docker.StopAndRemoveContainerWithReport(ctx, s.config.ConfigGen.ContainerName)
	if err != nil {
		return res, fmt.Errorf("stopping config-gen container %q: %w", s.config.ConfigGen.ContainerName, err)
	}
	return res, nil
}

// Stop halts both the proxy and config-gen containers.
// It returns a report which indicates what actually happened.
func (s *ProxyService) Stop(ctx context.Context) (ProxyStopReport, error) {
	proxyRes, err := s.StopProxy(ctx)
	if err != nil {
		return ProxyStopReport{Proxy: proxyRes}, err
	}

	configGenRes, err := s.StopConfigGen(ctx)
	if err != nil {
		return ProxyStopReport{Proxy: proxyRes, ConfigGen: configGenRes}, err
	}

	return ProxyStopReport{Proxy: proxyRes, ConfigGen: configGenRes}, nil
}

// Restart stops and starts the proxy system.
func (s *ProxyService) Restart(ctx context.Context, pull bool) error {
	_, _ = s.Stop(ctx)
	return s.Start(ctx, pull)
}

// IsRunning checks if the proxy container is running.
func (s *ProxyService) IsRunning(ctx context.Context) (bool, error) {
	return s.docker.IsContainerRunning(ctx, s.config.Proxy.ContainerName)
}

// Logs returns the proxy container logs.
func (s *ProxyService) Logs(ctx context.Context, follow bool) (io.ReadCloser, error) {
	return s.docker.ContainerLogs(ctx, s.config.Proxy.ContainerName, follow)
}

// Reload sends SIGHUP to docker-gen in the proxy container to reload nginx config.
func (s *ProxyService) Reload(ctx context.Context) error {
	return s.docker.SignalContainer(ctx, s.config.Proxy.ContainerName, "SIGHUP")
}

// NginxConfig reads the generated nginx config from the proxy container.
func (s *ProxyService) NginxConfig(ctx context.Context) (string, error) {
	return s.docker.ExecInContainer(ctx, s.config.Proxy.ContainerName,
		[]string{"cat", "/etc/nginx/conf.d/default.conf"})
}

// Status discovers proxied services from the proxy container's Docker networks.
func (s *ProxyService) Status(ctx context.Context) ([]ProxyStatusEntry, error) {
	var entries []ProxyStatusEntry

	// Use proxy container's actual networks (managed dynamically by config-gen).
	info, err := s.docker.InspectContainer(ctx, s.config.Proxy.ContainerName)
	if err != nil {
		return entries, nil // proxy not running, nothing to report
	}
	networks := info.Networks

	for _, netName := range networks {
		containers, err := s.docker.ListContainersOnNetwork(ctx, netName)
		if err != nil {
			continue
		}

		for _, cName := range containers {
			// Skip our own containers.
			if cName == s.config.Proxy.ContainerName || cName == s.config.ConfigGen.ContainerName {
				continue
			}

			info, err := s.docker.InspectContainer(ctx, cName)
			if err != nil {
				continue
			}

			// Check VIRTUAL_HOST env var.
			if host := info.Env["VIRTUAL_HOST"]; host != "" {
				port := info.Env["VIRTUAL_PORT"]
				if port == "" {
					port = "80"
				}
				proto := info.Env["VIRTUAL_PROTO"]
				if proto == "" {
					proto = "http"
				}
				path := info.Env["VIRTUAL_PATH"]
				if path == "" {
					path = "/"
				}
				entries = append(entries, ProxyStatusEntry{
					Network:   netName,
					Container: cName,
					Host:      host,
					Port:      port,
					Proto:     proto,
					Path:      path,
				})
			}

			// Check docker-proxy.* labels (independent of VIRTUAL_HOST).
			for _, r := range labelRoutes(info.Labels) {
				entries = append(entries, ProxyStatusEntry{
					Network:   netName,
					Container: cName,
					Host:      r.Host,
					Port:      r.Port,
					Proto:     r.Proto,
					Path:      r.Path,
				})
			}
		}
	}

	return entries, nil
}

// labelRoute is one HTTP route declared with docker-proxy.<tag>.<field> labels.
type labelRoute struct {
	Host, Port, Proto, Path string
}

// labelRoutes reads a container's docker-proxy.<tag>.<field> labels into the
// HTTP routes they declare, with the same defaults docker-config-gen applies.
func labelRoutes(labels map[string]string) []labelRoute {
	groups := make(map[string]map[string]string)
	for key, val := range labels {
		if len(key) > 13 && key[:13] == "docker-proxy." {
			parts := splitLabelKey(key[13:])
			if parts == nil {
				continue
			}
			tag, field := parts[0], parts[1]
			if groups[tag] == nil {
				groups[tag] = make(map[string]string)
			}
			groups[tag][field] = val
		}
	}
	var routes []labelRoute
	for _, g := range groups {
		// .proto=tcp|udp marks a raw stream, which docker-config-gen proxies
		// separately; it is not an HTTP route.
		if g["host"] == "" || isStreamProto(g["proto"]) {
			continue
		}
		p := g["port"]
		if p == "" {
			p = "80"
		}
		// The upstream scheme is .protocol, the label docker-config-gen reads.
		pr := g["protocol"]
		if pr == "" {
			pr = "http"
		}
		pa := g["path"]
		if pa == "" {
			pa = "/"
		}
		routes = append(routes, labelRoute{Host: g["host"], Port: p, Proto: pr, Path: pa})
	}
	return routes
}

func isStreamProto(proto string) bool {
	p := strings.ToLower(proto)
	return p == "tcp" || p == "udp"
}

// SidecarStatus discovers sidecar containers managed by docker-config-gen.
func (s *ProxyService) SidecarStatus(ctx context.Context) ([]SidecarStatusEntry, error) {
	containers, err := s.docker.ListRunningContainers(ctx, "docker-proxy.sidecar=true")
	if err != nil {
		return nil, err
	}

	var entries []SidecarStatusEntry
	for _, c := range containers {
		port := c.Labels["docker-proxy.sidecar.port"]
		proto := c.Labels["docker-proxy.sidecar.proto"]
		name := c.Names[0]
		if len(name) > 0 && name[0] == '/' {
			name = name[1:]
		}
		status := "running"
		if c.State != "running" {
			status = c.State
		}
		entries = append(entries, SidecarStatusEntry{
			Container: name,
			Port:      port,
			Proto:     proto,
			Status:    status,
		})
	}

	return entries, nil
}

// splitLabelKey splits "tag.field" into [tag, field], or nil if invalid.
func splitLabelKey(s string) []string {
	for i := range s {
		if s[i] == '.' {
			if i > 0 && i < len(s)-1 {
				return []string{s[:i], s[i+1:]}
			}
			return nil
		}
	}
	return nil
}

// ContainerDetails returns inspection info for the proxy container (nil if not running).
func (s *ProxyService) ContainerDetails(ctx context.Context) *docker.ContainerInfo {
	info, err := s.docker.InspectContainer(ctx, s.config.Proxy.ContainerName)
	if err != nil {
		return nil
	}
	return info
}

// ConfigGenDetails returns inspection info for the config-gen container (nil if not running).
func (s *ProxyService) ConfigGenDetails(ctx context.Context) *docker.ContainerInfo {
	info, err := s.docker.InspectContainer(ctx, s.config.ConfigGen.ContainerName)
	if err != nil {
		return nil
	}
	return info
}

// ContainerName returns the configured proxy container name.
func (s *ProxyService) ContainerName() string {
	return s.config.Proxy.ContainerName
}

// SetContainerName updates the proxy container name in config.
func (s *ProxyService) SetContainerName(name string) error {
	s.config.Proxy.ContainerName = name
	return s.config.Save()
}

// Image returns the configured proxy Docker image.
func (s *ProxyService) Image() string {
	return s.config.Proxy.DockerImage
}

// ConfigGenImage returns the configured config-gen Docker image.
func (s *ProxyService) ConfigGenImage() string {
	return s.config.ConfigGen.DockerImage
}

// SetImage updates the proxy Docker image in config.
func (s *ProxyService) SetImage(img string) error {
	s.config.Proxy.DockerImage = img
	return s.config.Save()
}

// EnsureConfigGenImage ensures the config-gen Docker image exists locally.
func (s *ProxyService) EnsureConfigGenImage(ctx context.Context, pull bool, w io.Writer) error {
	return s.docker.EnsureImage(ctx, s.config.ConfigGen.DockerImage, pull, w)
}

// EnsureProxyImage ensures the proxy Docker image exists locally.
func (s *ProxyService) EnsureProxyImage(ctx context.Context, pull bool, w io.Writer) error {
	return s.docker.EnsureImage(ctx, s.config.Proxy.DockerImage, pull, w)
}

// Cleanup removes both the config-gen and proxy containers.
func (s *ProxyService) Cleanup(ctx context.Context) {
	s.cleanupConfigGen(ctx)
	s.cleanupProxy(ctx)
}

// StartConfigGenContainer creates and starts the config-gen container.
func (s *ProxyService) StartConfigGenContainer(ctx context.Context) error {
	_, err := s.startConfigGen(ctx)
	return err
}

// StartProxyContainer creates and starts the proxy container.
// Network connections are managed dynamically by docker-config-gen.
func (s *ProxyService) StartProxyContainer(ctx context.Context) error {
	_, err := s.startProxy(ctx)
	return err
}

// ConfigGenContainerName returns the configured config-gen container name.
func (s *ProxyService) ConfigGenContainerName() string {
	return s.config.ConfigGen.ContainerName
}

func (s *ProxyService) cleanupConfigGen(ctx context.Context) {
	s.docker.StopAndRemoveContainer(ctx, s.config.ConfigGen.ContainerName)
}

func (s *ProxyService) cleanupProxy(ctx context.Context) {
	s.docker.StopAndRemoveContainer(ctx, s.config.Proxy.ContainerName)
}

func (s *ProxyService) startConfigGen(ctx context.Context) (string, error) {
	return s.docker.RunContainer(ctx, s.config.ConfigGen.ContainerName,
		&container.Config{
			Image: s.config.ConfigGen.DockerImage,
			Env: []string{
				"MANAGEMENT_SOCKET=" + managementSockPath,
				"RENDERER=nginx",
				"PROXY_CONTAINER=" + s.config.Proxy.ContainerName,
			},
		},
		&container.HostConfig{
			RestartPolicy: container.RestartPolicy{Name: "always"},
			Mounts: []mount.Mount{
				{Type: mount.TypeBind, Source: "/var/run/docker.sock", Target: "/var/run/docker.sock", ReadOnly: true},
				{Type: mount.TypeVolume, Source: managementVol, Target: "/var/run/proxy"},
			},
		},
		nil,
	)
}

func (s *ProxyService) startProxy(ctx context.Context) (string, error) {
	return s.docker.RunContainer(ctx, s.config.Proxy.ContainerName,
		&container.Config{
			Image: s.config.Proxy.DockerImage,
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
				{Type: mount.TypeVolume, Source: proxyCertsVol, Target: "/etc/nginx/certs"},
				{Type: mount.TypeVolume, Source: proxyVhostVol, Target: "/etc/nginx/vhost.d"},
				{Type: mount.TypeVolume, Source: proxyHTMLVol, Target: "/usr/share/nginx/html"},
			},
		},
		&network.NetworkingConfig{},
	)
}
