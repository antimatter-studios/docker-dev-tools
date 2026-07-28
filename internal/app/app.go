package app

import (
	"fmt"
	"os"
	"runtime"

	"github.com/christhomas/docker-dev-tools/internal/config"
	"github.com/christhomas/docker-dev-tools/internal/docker"
	"github.com/christhomas/docker-dev-tools/internal/platform"
	"github.com/christhomas/docker-dev-tools/internal/service"
)

// BuildInfo holds version information injected at build time.
type BuildInfo struct {
	Version string
	Commit  string
	Date    string
}

func (b BuildInfo) String() string {
	return fmt.Sprintf("ddt %s (%s) built %s %s/%s", b.Version, b.Commit, b.Date, runtime.GOOS, runtime.GOARCH)
}

// App is the top-level application container holding all services.
type App struct {
	Build    BuildInfo
	Config   *config.SystemConfig
	Platform platform.Platform
	Docker   *docker.Client
	IP       *service.IPService
	DNS      *service.DNSService
	Proxy    *service.ProxyService
	Project  *service.ProjectService
	Runner   *service.RunnerService
	Git      *service.GitService
}

// New creates and wires up a new App instance.
func New(build BuildInfo) *App {
	cfg := config.LoadOrDefault()

	// Loading may have rewritten a docker_image to match the digests this build pins.
	// On stderr so it never lands in the output of a command being parsed, but said out
	// loud — a silently replaced image is the one thing this mechanism must not do.
	for _, note := range cfg.ImageNotes() {
		fmt.Fprintln(os.Stderr, note)
	}

	plat := platform.Detect()
	dockerClient := docker.NewClient()

	ipSvc := service.NewIPService(cfg, plat)
	dnsSvc := service.NewDNSService(cfg, dockerClient, plat)
	proxySvc := service.NewProxyService(cfg, dockerClient)
	projectSvc := service.NewProjectService(cfg)
	gitSvc := service.NewGitService()
	runnerSvc := service.NewRunnerService(cfg, projectSvc)

	return &App{
		Build:    build,
		Config:   cfg,
		Platform: plat,
		Docker:   dockerClient,
		IP:       ipSvc,
		DNS:      dnsSvc,
		Proxy:    proxySvc,
		Project:  projectSvc,
		Runner:   runnerSvc,
		Git:      gitSvc,
	}
}
