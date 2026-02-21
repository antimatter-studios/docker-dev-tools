package docker

import (
	"bytes"
	"context"
	"fmt"
	"io"
	"strings"
	"time"

	"github.com/docker/docker/api/types"
	"github.com/docker/docker/api/types/container"
	"github.com/docker/docker/api/types/filters"
	"github.com/docker/docker/api/types/image"
	"github.com/docker/docker/api/types/network"
	"github.com/docker/docker/client"
	"github.com/docker/docker/errdefs"
	ocispec "github.com/opencontainers/image-spec/specs-go/v1"
)

// Client wraps the Docker SDK client with convenience methods.
type Client struct {
	api client.APIClient
}

// NewClient creates a new Docker client using environment defaults.
func NewClient() *Client {
	return &Client{}
}

// connect lazily initialises the Docker API client.
func (c *Client) connect() (client.APIClient, error) {
	if c.api != nil {
		return c.api, nil
	}
	api, err := client.NewClientWithOpts(client.FromEnv, client.WithAPIVersionNegotiation())
	if err != nil {
		return nil, fmt.Errorf("connecting to docker: %w", err)
	}
	c.api = api
	return c.api, nil
}

// API returns the underlying Docker API client, connecting if needed.
func (c *Client) API() (client.APIClient, error) {
	return c.connect()
}

// Ping checks if the Docker daemon is reachable.
func (c *Client) Ping(ctx context.Context) error {
	api, err := c.connect()
	if err != nil {
		return err
	}
	_, err = api.Ping(ctx)
	if err != nil {
		return fmt.Errorf("docker daemon not reachable: %w", err)
	}
	return nil
}

// HasImage checks if a Docker image exists locally.
func (c *Client) HasImage(ctx context.Context, ref string) bool {
	api, err := c.connect()
	if err != nil {
		return false
	}
	_, _, err = api.ImageInspectWithRaw(ctx, ref)
	return err == nil
}

// ImageID returns the short image ID (first 12 hex chars) for a local image.
// Returns "" if the image is not found.
func (c *Client) ImageID(ctx context.Context, ref string) string {
	api, err := c.connect()
	if err != nil {
		return ""
	}
	inspect, _, err := api.ImageInspectWithRaw(ctx, ref)
	if err != nil {
		return ""
	}
	id := strings.TrimPrefix(inspect.ID, "sha256:")
	if len(id) > 12 {
		return id[:12]
	}
	return id
}

// ImageMeta holds short image ID and creation timestamp.
type ImageMeta struct {
	ID      string // short hex hash (12 chars)
	Created string // RFC 3339 timestamp
}

// ImageInfo returns the short ID and creation date for a local image.
// Returns nil if the image is not found.
func (c *Client) ImageInfo(ctx context.Context, ref string) *ImageMeta {
	api, err := c.connect()
	if err != nil {
		return nil
	}
	inspect, _, err := api.ImageInspectWithRaw(ctx, ref)
	if err != nil {
		return nil
	}
	id := strings.TrimPrefix(inspect.ID, "sha256:")
	if len(id) > 12 {
		id = id[:12]
	}
	return &ImageMeta{ID: id, Created: inspect.Created}
}

// String returns "image-ref (sha, built date)" suitable for display.
func (m *ImageMeta) Summary(ref string) string {
	date := m.Created
	if t, err := time.Parse(time.RFC3339Nano, m.Created); err == nil {
		date = t.Local().Format("2006-01-02 15:04")
	} else if t, err := time.Parse(time.RFC3339, m.Created); err == nil {
		date = t.Local().Format("2006-01-02 15:04")
	}
	return fmt.Sprintf("%s (%s, built %s)", ref, m.ID, date)
}

// PullImage pulls a Docker image.
func (c *Client) PullImage(ctx context.Context, ref string) error {
	api, err := c.connect()
	if err != nil {
		return err
	}
	reader, err := api.ImagePull(ctx, ref, image.PullOptions{})
	if err != nil {
		return fmt.Errorf("pulling image %s: %w", ref, err)
	}
	defer reader.Close()
	_, _ = io.Copy(io.Discard, reader)
	return nil
}

// EnsureImage checks if an image exists locally; if not, pulls it.
// If forcePull is true, always pulls regardless. Logs actions to w if non-nil.
func (c *Client) EnsureImage(ctx context.Context, ref string, forcePull bool, w io.Writer) error {
	if forcePull {
		if w != nil {
			fmt.Fprintf(w, "Pulling image %s ...\n", ref)
		}
		return c.PullImage(ctx, ref)
	}

	if !c.HasImage(ctx, ref) {
		if w != nil {
			fmt.Fprintf(w, "Image %s not found locally, pulling ...\n", ref)
		}
		return c.PullImage(ctx, ref)
	}

	return nil
}

// RunContainer creates and starts a container, returning its ID.
func (c *Client) RunContainer(ctx context.Context, name string, cfg *container.Config, hostCfg *container.HostConfig, netCfg *network.NetworkingConfig) (string, error) {
	api, err := c.connect()
	if err != nil {
		return "", err
	}

	resp, err := api.ContainerCreate(ctx, cfg, hostCfg, netCfg, &ocispec.Platform{}, name)
	if err != nil {
		return "", fmt.Errorf("creating container %s: %w", name, err)
	}

	if err := api.ContainerStart(ctx, resp.ID, container.StartOptions{}); err != nil {
		return "", fmt.Errorf("starting container %s: %w", name, err)
	}

	return resp.ID, nil
}

// StopContainer stops a running container.
func (c *Client) StopContainer(ctx context.Context, nameOrID string) error {
	api, err := c.connect()
	if err != nil {
		return err
	}
	if err := api.ContainerStop(ctx, nameOrID, container.StopOptions{}); err != nil {
		return fmt.Errorf("stopping container %s: %w", nameOrID, err)
	}
	return nil
}

// RemoveContainer removes a container.
func (c *Client) RemoveContainer(ctx context.Context, nameOrID string, force bool) error {
	api, err := c.connect()
	if err != nil {
		return err
	}
	return api.ContainerRemove(ctx, nameOrID, container.RemoveOptions{Force: force})
}

// StopAndRemoveContainer stops then removes a container, ignoring errors
// if the container doesn't exist.
func (c *Client) StopAndRemoveContainer(ctx context.Context, nameOrID string) {
	_ = c.StopContainer(ctx, nameOrID)
	_ = c.RemoveContainer(ctx, nameOrID, true)
}

// StopRemoveResult reports what actions were taken when stopping/removing a container.
type StopRemoveResult struct {
	Found      bool
	WasRunning bool
	Stopped    bool
	Removed    bool
}

// StopAndRemoveContainerWithReport stops and removes a container if it exists.
// It distinguishes between "not found", "already stopped", and "stopped".
func (c *Client) StopAndRemoveContainerWithReport(ctx context.Context, nameOrID string) (StopRemoveResult, error) {
	api, err := c.connect()
	if err != nil {
		return StopRemoveResult{}, err
	}

	info, err := api.ContainerInspect(ctx, nameOrID)
	if err != nil {
		// Treat not-found as a non-error; caller can decide what to print.
		if errdefs.IsNotFound(err) || strings.Contains(err.Error(), "No such container") {
			return StopRemoveResult{Found: false}, nil
		}
		return StopRemoveResult{}, fmt.Errorf("inspecting container %s: %w", nameOrID, err)
	}

	res := StopRemoveResult{Found: true, WasRunning: info.State != nil && info.State.Running}

	if res.WasRunning {
		if err := c.StopContainer(ctx, nameOrID); err != nil {
			return res, err
		}
		res.Stopped = true
	}

	if err := c.RemoveContainer(ctx, nameOrID, true); err != nil {
		// If it disappears between inspect and remove, treat as removed.
		if errdefs.IsNotFound(err) || strings.Contains(err.Error(), "No such container") {
			res.Removed = true
			return res, nil
		}
		return res, err
	}

	res.Removed = true
	return res, nil
}

// IsContainerRunning checks if a container is currently running.
func (c *Client) IsContainerRunning(ctx context.Context, nameOrID string) (bool, error) {
	api, err := c.connect()
	if err != nil {
		return false, err
	}
	info, err := api.ContainerInspect(ctx, nameOrID)
	if err != nil {
		return false, nil
	}
	return info.State.Running, nil
}

// ContainerLogs returns the logs of a container.
func (c *Client) ContainerLogs(ctx context.Context, nameOrID string, follow bool) (io.ReadCloser, error) {
	api, err := c.connect()
	if err != nil {
		return nil, err
	}
	return api.ContainerLogs(ctx, nameOrID, container.LogsOptions{
		ShowStdout: true,
		ShowStderr: true,
		Follow:     follow,
	})
}

// CopyToContainer copies a tar archive into a container at the given path.
func (c *Client) CopyToContainer(ctx context.Context, containerID, destPath string, content io.Reader) error {
	api, err := c.connect()
	if err != nil {
		return err
	}
	return api.CopyToContainer(ctx, containerID, destPath, content, container.CopyToContainerOptions{})
}

// ExecInContainer runs a command inside a running container.
func (c *Client) ExecInContainer(ctx context.Context, containerID string, cmd []string) (string, error) {
	api, err := c.connect()
	if err != nil {
		return "", err
	}

	exec, err := api.ContainerExecCreate(ctx, containerID, container.ExecOptions{
		Cmd:          cmd,
		AttachStdout: true,
		AttachStderr: true,
	})
	if err != nil {
		return "", fmt.Errorf("creating exec: %w", err)
	}

	resp, err := api.ContainerExecAttach(ctx, exec.ID, container.ExecStartOptions{})
	if err != nil {
		return "", fmt.Errorf("attaching exec: %w", err)
	}
	defer resp.Close()

	var buf bytes.Buffer
	_, _ = io.Copy(&buf, resp.Reader)
	return buf.String(), nil
}

// CreateNetwork creates a Docker network.
func (c *Client) CreateNetwork(ctx context.Context, name string) (string, error) {
	api, err := c.connect()
	if err != nil {
		return "", err
	}
	resp, err := api.NetworkCreate(ctx, name, network.CreateOptions{})
	if err != nil {
		return "", fmt.Errorf("creating network %s: %w", name, err)
	}
	return resp.ID, nil
}

// EnsureNetwork creates a network if it doesn't already exist.
func (c *Client) EnsureNetwork(ctx context.Context, name string) error {
	api, err := c.connect()
	if err != nil {
		return err
	}
	_, err = api.NetworkInspect(ctx, name, network.InspectOptions{})
	if err == nil {
		return nil
	}
	_, err = api.NetworkCreate(ctx, name, network.CreateOptions{})
	if err != nil {
		return fmt.Errorf("creating network %s: %w", name, err)
	}
	return nil
}

// ConnectNetwork connects a container to a network.
func (c *Client) ConnectNetwork(ctx context.Context, networkName, containerID string) error {
	api, err := c.connect()
	if err != nil {
		return err
	}
	return api.NetworkConnect(ctx, networkName, containerID, nil)
}

// DisconnectNetwork disconnects a container from a network.
func (c *Client) DisconnectNetwork(ctx context.Context, networkName, containerID string) error {
	api, err := c.connect()
	if err != nil {
		return err
	}
	return api.NetworkDisconnect(ctx, networkName, containerID, false)
}

// SignalContainer sends a signal (e.g. "SIGHUP") to a running container.
func (c *Client) SignalContainer(ctx context.Context, nameOrID, signal string) error {
	api, err := c.connect()
	if err != nil {
		return err
	}
	return api.ContainerKill(ctx, nameOrID, signal)
}

// PortBinding represents a host-to-container port mapping.
type PortBinding struct {
	ContainerPort string // e.g. "53/tcp"
	HostIP        string // e.g. "10.254.254.254"
	HostPort      string // e.g. "10053"
}

// String returns a human-readable port binding like "10.254.254.254:10053->53/tcp".
func (p PortBinding) String() string {
	host := p.HostIP
	if host == "" || host == "0.0.0.0" {
		host = "0.0.0.0"
	}
	return fmt.Sprintf("%s:%s->%s", host, p.HostPort, p.ContainerPort)
}

// ContainerInfo holds inspected container metadata.
type ContainerInfo struct {
	ID           string
	Name         string
	Image        string
	ImageID      string
	ImageCreated string
	Running      bool
	Env          map[string]string
	Labels       map[string]string
	Ports        []string
	PortBindings []PortBinding
	Networks     []string
}

// InspectContainer returns metadata about a container.
func (c *Client) InspectContainer(ctx context.Context, nameOrID string) (*ContainerInfo, error) {
	api, err := c.connect()
	if err != nil {
		return nil, err
	}
	info, err := api.ContainerInspect(ctx, nameOrID)
	if err != nil {
		return nil, fmt.Errorf("inspecting container %s: %w", nameOrID, err)
	}

	env := make(map[string]string)
	for _, e := range info.Config.Env {
		parts := bytes.SplitN([]byte(e), []byte("="), 2)
		if len(parts) == 2 {
			env[string(parts[0])] = string(parts[1])
		}
	}

	var ports []string
	for p := range info.Config.ExposedPorts {
		ports = append(ports, string(p))
	}

	var bindings []PortBinding
	if info.NetworkSettings != nil {
		for containerPort, hostBindings := range info.NetworkSettings.Ports {
			for _, hb := range hostBindings {
				bindings = append(bindings, PortBinding{
					ContainerPort: string(containerPort),
					HostIP:        hb.HostIP,
					HostPort:      hb.HostPort,
				})
			}
		}
	}

	var networks []string
	if info.NetworkSettings != nil {
		for netName := range info.NetworkSettings.Networks {
			networks = append(networks, netName)
		}
	}

	// Fetch image metadata for ID and build date.
	var imageID, imageCreated string
	imgInspect, _, err := api.ImageInspectWithRaw(ctx, info.Image)
	if err == nil {
		imageID = imgInspect.ID
		imageCreated = imgInspect.Created
	}

	return &ContainerInfo{
		ID:           info.ID,
		Name:         info.Name,
		Image:        info.Config.Image,
		ImageID:      imageID,
		ImageCreated: imageCreated,
		Running:      info.State.Running,
		Env:          env,
		Labels:       info.Config.Labels,
		Ports:        ports,
		PortBindings: bindings,
		Networks:     networks,
	}, nil
}

// ListContainersOnNetwork returns the names of all containers connected to a network.
func (c *Client) ListContainersOnNetwork(ctx context.Context, networkName string) ([]string, error) {
	api, err := c.connect()
	if err != nil {
		return nil, err
	}

	netInfo, err := api.NetworkInspect(ctx, networkName, network.InspectOptions{})
	if err != nil {
		return nil, fmt.Errorf("inspecting network %s: %w", networkName, err)
	}

	var names []string
	for _, ep := range netInfo.Containers {
		names = append(names, ep.Name)
	}
	return names, nil
}

// ListRunningContainers returns all running containers, optionally filtered by labels.
func (c *Client) ListRunningContainers(ctx context.Context, labelFilter ...string) ([]types.Container, error) {
	api, err := c.connect()
	if err != nil {
		return nil, err
	}

	f := filters.NewArgs()
	f.Add("status", "running")
	for _, l := range labelFilter {
		f.Add("label", l)
	}

	return api.ContainerList(ctx, container.ListOptions{Filters: f})
}
