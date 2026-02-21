package service

import (
	"context"
	"fmt"
	"io"
	"os"
	"regexp"
	"strings"

	"github.com/docker/docker/api/types/container"
	"github.com/docker/docker/api/types/mount"
	nat "github.com/docker/go-connections/nat"

	"github.com/christhomas/docker-dev-tools/internal/config"
	"github.com/christhomas/docker-dev-tools/internal/docker"
	"github.com/christhomas/docker-dev-tools/internal/platform"
)

// DNSService manages the local dnsmasq DNS server container.
type DNSService struct {
	config   *config.SystemConfig
	docker   *docker.Client
	platform platform.Platform
}

// NewDNSService creates a new DNS service.
func NewDNSService(cfg *config.SystemConfig, d *docker.Client, plat platform.Platform) *DNSService {
	return &DNSService{config: cfg, docker: d, platform: plat}
}

// EnsureImage checks the DNS image exists locally, pulling if needed.
func (s *DNSService) EnsureImage(ctx context.Context, pull bool, w io.Writer) error {
	return s.docker.EnsureImage(ctx, s.config.DNS.DockerImage, pull, w)
}

// Cleanup stops and removes any existing DNS container.
func (s *DNSService) Cleanup(ctx context.Context) {
	s.docker.StopAndRemoveContainer(ctx, s.config.DNS.ContainerName)
}

// EnsureIPAlias checks the IP alias and creates it if missing.
// Returns true if the alias already existed.
func (s *DNSService) EnsureIPAlias() (alreadyExisted bool, err error) {
	ip := s.config.IPAddress
	has, _ := s.platform.HasIPAlias(ip)
	if has {
		return true, nil
	}
	return false, s.platform.AddIPAlias(ip)
}

// CheckPort verifies the configured DNS port is available on the configured IP.
func (s *DNSService) CheckPort() error {
	return platform.CheckPortAvailable(s.config.IPAddress, s.config.DNS.PortOrDefault())
}

// Port returns the configured host port for the DNS service.
func (s *DNSService) Port() int {
	return s.config.DNS.PortOrDefault()
}

// StartContainer creates and starts the dnsmasq container.
// The container's internal port 53 is mapped to the configured host port.
func (s *DNSService) StartContainer(ctx context.Context) error {
	ip := s.config.IPAddress
	hostPort := fmt.Sprintf("%d", s.config.DNS.PortOrDefault())
	_, err := s.docker.RunContainer(ctx, s.config.DNS.ContainerName,
		&container.Config{
			Image: s.config.DNS.DockerImage,
			ExposedPorts: nat.PortSet{
				"53/tcp": struct{}{},
				"53/udp": struct{}{},
			},
		},
		&container.HostConfig{
			RestartPolicy: container.RestartPolicy{Name: "always"},
			PortBindings: nat.PortMap{
				"53/tcp": []nat.PortBinding{{HostIP: ip, HostPort: hostPort}},
				"53/udp": []nat.PortBinding{{HostIP: ip, HostPort: hostPort}},
			},
			Mounts: []mount.Mount{},
		},
		nil,
	)
	if err != nil {
		return fmt.Errorf("binding %s:%s: %w", ip, hostPort, err)
	}
	return nil
}

// ConfigureUpstreams resets upstream DNS servers inside the container.
func (s *DNSService) ConfigureUpstreams(ctx context.Context) error {
	return s.resetUpstreams(ctx)
}

// ConfigureTLDs writes all configured TLDs into the container.
func (s *DNSService) ConfigureTLDs(ctx context.Context) {
	ip := s.config.IPAddress
	for _, tld := range s.config.DNS.TLDs {
		s.writeTLDConfig(ctx, tld, ip)
	}
}

// EnableResolvers creates system resolver files for all configured TLDs.
// Only flushes the DNS cache if any resolver files were actually written.
func (s *DNSService) EnableResolvers() error {
	ip := s.config.IPAddress
	port := s.config.DNS.PortOrDefault()
	anyChanged := false
	for _, tld := range s.config.DNS.TLDs {
		changed, err := s.platform.EnableDNS(tld, ip, port)
		if err != nil {
			return err
		}
		if changed {
			anyChanged = true
		}
	}
	if anyChanged {
		return s.platform.FlushDNS()
	}
	return nil
}

// Start launches the DNS container, configures upstreams, adds all configured
// domains, enables the system resolver, and reloads. This is a convenience
// method — the CLI layer uses the individual step methods for richer output.
func (s *DNSService) Start(ctx context.Context, pull bool) error {
	if err := s.EnsureImage(ctx, pull, os.Stdout); err != nil {
		return fmt.Errorf("DNS image: %w", err)
	}
	s.Cleanup(ctx)
	if _, err := s.EnsureIPAlias(); err != nil {
		return fmt.Errorf("IP alias: %w", err)
	}
	if err := s.StartContainer(ctx); err != nil {
		return err
	}
	if err := s.ConfigureUpstreams(ctx); err != nil {
		return fmt.Errorf("setting upstreams: %w", err)
	}
	s.ConfigureTLDs(ctx)
	s.Reload(ctx)
	return s.EnableResolvers()
}

// Stop stops and removes the DNS container. Resolver files are left in place
// to avoid unnecessary sudo prompts — they are harmless when the server is
// down (queries simply time out after ~5 s and fall through). Use Disable()
// to explicitly remove resolver files.
func (s *DNSService) Stop(ctx context.Context) error {
	s.docker.StopAndRemoveContainer(ctx, s.config.DNS.ContainerName)
	return nil
}

// Restart stops and starts the DNS system.
func (s *DNSService) Restart(ctx context.Context, pull bool) error {
	_ = s.Stop(ctx)
	return s.Start(ctx, pull)
}

// Enable enables system DNS resolution for all configured TLDs
// without starting/stopping the container.
func (s *DNSService) Enable(ctx context.Context) error {
	ip := s.config.IPAddress
	port := s.config.DNS.PortOrDefault()
	anyChanged := false
	for _, tld := range s.config.DNS.TLDs {
		changed, err := s.platform.EnableDNS(tld, ip, port)
		if err != nil {
			return fmt.Errorf("enabling DNS for TLD .%s: %w", tld, err)
		}
		if changed {
			anyChanged = true
		}
	}
	if anyChanged {
		return s.platform.FlushDNS()
	}
	return nil
}

// Disable removes system DNS resolution for all configured TLDs.
func (s *DNSService) Disable(ctx context.Context) error {
	for _, tld := range s.config.DNS.TLDs {
		if err := s.platform.DisableDNS(tld); err != nil {
			return fmt.Errorf("disabling DNS for TLD .%s: %w", tld, err)
		}
	}
	return s.platform.FlushDNS()
}

// Refresh ensures resolver files are up to date and resets upstreams.
// EnableDNS already skips writing when the file content hasn't changed,
// so this only triggers sudo when the IP or port actually changed.
func (s *DNSService) Refresh(ctx context.Context) error {
	_ = s.Enable(ctx)
	_ = s.resetUpstreams(ctx)
	s.Reload(ctx)
	return nil
}

// Reload sends SIGHUP to dnsmasq to reload its configuration.
func (s *DNSService) Reload(ctx context.Context) {
	_, _ = s.docker.ExecInContainer(ctx, s.config.DNS.ContainerName,
		[]string{"kill", "-s", "SIGHUP", "1"})
}

// IsRunning checks if the DNS container is running.
func (s *DNSService) IsRunning(ctx context.Context) (bool, error) {
	return s.docker.IsContainerRunning(ctx, s.config.DNS.ContainerName)
}

// Logs returns the DNS container logs.
func (s *DNSService) Logs(ctx context.Context, follow bool) (io.ReadCloser, error) {
	return s.docker.ContainerLogs(ctx, s.config.DNS.ContainerName, follow)
}

// AddTLD registers a TLD for wildcard resolution (e.g. "develop" -> *.develop).
func (s *DNSService) AddTLD(ctx context.Context, tld string) error {
	ip := s.config.IPAddress

	running, _ := s.IsRunning(ctx)
	if running {
		s.writeTLDConfig(ctx, tld, ip)
		s.Reload(ctx)
	}

	// Enable system resolver for this TLD.
	changed, _ := s.platform.EnableDNS(tld, ip, s.config.DNS.PortOrDefault())
	if changed {
		_ = s.platform.FlushDNS()
	}

	// Save to config.
	for _, t := range s.config.DNS.TLDs {
		if t == tld {
			return nil // already present
		}
	}
	s.config.DNS.TLDs = append(s.config.DNS.TLDs, tld)
	return s.config.Save()
}

// RemoveTLD removes a TLD from wildcard resolution.
func (s *DNSService) RemoveTLD(ctx context.Context, tld string) error {
	confFile := fmt.Sprintf("/etc/dnsmasq.d/tld_%s.conf", tld)

	running, _ := s.IsRunning(ctx)
	if running {
		_, _ = s.docker.ExecInContainer(ctx, s.config.DNS.ContainerName, []string{"rm", "-f", confFile})
		s.Reload(ctx)
	}

	// Disable system resolver for this TLD.
	_ = s.platform.DisableDNS(tld)
	_ = s.platform.FlushDNS()

	// Remove from config.
	filtered := make([]string, 0, len(s.config.DNS.TLDs))
	for _, t := range s.config.DNS.TLDs {
		if t != tld {
			filtered = append(filtered, t)
		}
	}
	s.config.DNS.TLDs = filtered
	return s.config.Save()
}

// ConfiguredTLDs returns the TLDs stored in the config file.
func (s *DNSService) ConfiguredTLDs() []string {
	return s.config.DNS.TLDs
}

func (s *DNSService) writeTLDConfig(ctx context.Context, tld, ip string) {
	// address=/.develop/10.254.254.254 resolves *.develop and develop itself.
	confContent := fmt.Sprintf("address=/.%s/%s", tld, ip)
	confFile := fmt.Sprintf("/etc/dnsmasq.d/tld_%s.conf", tld)
	cmd := []string{"sh", "-c", fmt.Sprintf("echo '%s' > %s", confContent, confFile)}
	_, _ = s.docker.ExecInContainer(ctx, s.config.DNS.ContainerName, cmd)
}

// AddUpstream adds an upstream DNS server to the container and config.
func (s *DNSService) AddUpstream(ctx context.Context, address string) error {
	running, _ := s.IsRunning(ctx)
	if running {
		filename := upstreamFilename(address)
		cmd := []string{"sh", "-c", fmt.Sprintf("echo 'server=%s' > %s", address, filename)}
		if _, err := s.docker.ExecInContainer(ctx, s.config.DNS.ContainerName, cmd); err != nil {
			return fmt.Errorf("writing upstream config: %w", err)
		}
		s.Reload(ctx)
	}

	// Save to config.
	for _, u := range s.config.DNS.Upstream {
		if u == address {
			return nil
		}
	}
	s.config.DNS.Upstream = append(s.config.DNS.Upstream, address)
	return s.config.Save()
}

// RemoveUpstream removes an upstream DNS server from the container and config.
func (s *DNSService) RemoveUpstream(ctx context.Context, address string) error {
	running, _ := s.IsRunning(ctx)
	if running {
		filename := upstreamFilename(address)
		_, _ = s.docker.ExecInContainer(ctx, s.config.DNS.ContainerName, []string{"rm", "-f", filename})
		s.Reload(ctx)
	}

	filtered := make([]string, 0, len(s.config.DNS.Upstream))
	for _, u := range s.config.DNS.Upstream {
		if u != address {
			filtered = append(filtered, u)
		}
	}
	s.config.DNS.Upstream = filtered
	return s.config.Save()
}

// resetUpstreams clears existing upstream configs and writes configured ones.
func (s *DNSService) resetUpstreams(ctx context.Context) error {
	// Clear existing upstream files.
	_, _ = s.docker.ExecInContainer(ctx, s.config.DNS.ContainerName,
		[]string{"sh", "-c", "rm -f /etc/dnsmasq.d/upstream_dns_*.conf"})

	// Get configured upstreams, or fall back to system upstreams.
	upstreams := s.config.DNS.Upstream
	if len(upstreams) == 0 {
		sysUpstreams, _ := s.platform.GetSystemUpstreams()
		upstreams = filterIP(sysUpstreams, s.config.IPAddress)
	}

	for _, addr := range upstreams {
		filename := upstreamFilename(addr)
		cmd := []string{"sh", "-c", fmt.Sprintf("echo 'server=%s' > %s", addr, filename)}
		if _, err := s.docker.ExecInContainer(ctx, s.config.DNS.ContainerName, cmd); err != nil {
			return fmt.Errorf("writing upstream %s: %w", addr, err)
		}
	}
	return nil
}

func upstreamFilename(address string) string {
	safe := strings.NewReplacer(".", "_", ":", "_").Replace(address)
	return fmt.Sprintf("/etc/dnsmasq.d/upstream_dns_%s.conf", safe)
}

func filterIP(list []string, exclude string) []string {
	var out []string
	for _, s := range list {
		if !strings.Contains(s, exclude) {
			out = append(out, s)
		}
	}
	return out
}

// DomainEntry represents a domain registered in the DNS container.
type DomainEntry struct {
	Domain    string
	IPAddress string
}

// ListDomains reads the domain configs from inside the running DNS container.
func (s *DNSService) ListDomains(ctx context.Context) ([]DomainEntry, error) {
	output, err := s.docker.ExecInContainer(ctx, s.config.DNS.ContainerName,
		[]string{"sh", "-c", "cat /etc/dnsmasq.d/*.conf 2>/dev/null || true"})
	if err != nil {
		return nil, err
	}

	re := regexp.MustCompile(`address=/([^/]+)/([^/\s]+)`)
	var entries []DomainEntry
	for _, line := range strings.Split(output, "\n") {
		matches := re.FindStringSubmatch(strings.TrimSpace(line))
		if matches != nil {
			entries = append(entries, DomainEntry{Domain: matches[1], IPAddress: matches[2]})
		}
	}
	return entries, nil
}

// ListUpstreams reads the upstream server configs from the running DNS container.
func (s *DNSService) ListUpstreams(ctx context.Context) ([]string, error) {
	output, err := s.docker.ExecInContainer(ctx, s.config.DNS.ContainerName,
		[]string{"sh", "-c", "cat /etc/dnsmasq.d/upstream_dns_*.conf 2>/dev/null || true"})
	if err != nil {
		return nil, err
	}

	re := regexp.MustCompile(`server=(.+)`)
	var upstreams []string
	for _, line := range strings.Split(output, "\n") {
		matches := re.FindStringSubmatch(strings.TrimSpace(line))
		if matches != nil {
			upstreams = append(upstreams, matches[1])
		}
	}
	return upstreams, nil
}

// DumpConfig reads all dnsmasq config files from the container.
func (s *DNSService) DumpConfig(ctx context.Context) (string, error) {
	// Get main config.
	main, _ := s.docker.ExecInContainer(ctx, s.config.DNS.ContainerName,
		[]string{"cat", "/etc/dnsmasq.conf"})

	// Get extra config files.
	files, _ := s.docker.ExecInContainer(ctx, s.config.DNS.ContainerName,
		[]string{"sh", "-c", "ls /etc/dnsmasq.d/ 2>/dev/null || true"})

	var b strings.Builder
	b.WriteString("/etc/dnsmasq.conf:\n")
	b.WriteString(main)
	b.WriteString("\n\n")

	for _, f := range strings.Split(strings.TrimSpace(files), "\n") {
		f = strings.TrimSpace(f)
		if f == "" {
			continue
		}
		path := "/etc/dnsmasq.d/" + f
		content, _ := s.docker.ExecInContainer(ctx, s.config.DNS.ContainerName,
			[]string{"cat", path})
		b.WriteString(path + ":\n")
		b.WriteString(strings.TrimSpace(content))
		b.WriteString("\n\n")
	}

	return b.String(), nil
}

// ContainerDetails returns inspection info for the DNS container (nil if not running).
func (s *DNSService) ContainerDetails(ctx context.Context) *docker.ContainerInfo {
	info, err := s.docker.InspectContainer(ctx, s.config.DNS.ContainerName)
	if err != nil {
		return nil
	}
	return info
}

// ContainerName returns the configured DNS container name.
func (s *DNSService) ContainerName() string {
	return s.config.DNS.ContainerName
}

// SetContainerName updates the DNS container name in config.
func (s *DNSService) SetContainerName(name string) error {
	s.config.DNS.ContainerName = name
	return s.config.Save()
}

// Image returns the configured DNS Docker image.
func (s *DNSService) Image() string {
	return s.config.DNS.DockerImage
}

// SetImage updates the DNS Docker image in config.
func (s *DNSService) SetImage(img string) error {
	s.config.DNS.DockerImage = img
	return s.config.Save()
}
