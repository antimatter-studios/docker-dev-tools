package app

import (
	"fmt"
	"os"
	"runtime"
	"strings"
	"time"

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

// String returns the version alone, with no program name.
//
// It used to begin with "ddt ", which cobra's version template then prefixed with its
// own "{{.Name}} version …" — printing `ddt version ddt 2.2.0 (a528ffd…) built …`.
// Whoever prints this already knows which program it is.
func (b BuildInfo) String() string {
	if b.Version == "" {
		return "dev"
	}
	return b.Version
}

// Details returns the indented lines shown beneath the version: where the build came
// from, when, and what built it.
//
// Fields a build from source does not have are left out rather than printed as "none"
// or an empty value, so `ddt --version` never shows a label with nothing after it.
func (b BuildInfo) Details() string {
	var lines []string

	if c := b.Commit; c != "" && c != "none" && c != "unknown" {
		// Short form, as git shows it. The tag identifies a release exactly; this is
		// for telling two builds apart at a glance.
		if len(c) > 7 {
			c = c[:7]
		}
		lines = append(lines, "  commit  "+c)
	}
	if d := b.built(); d != "" {
		lines = append(lines, "  built   "+d)
	}
	lines = append(lines, fmt.Sprintf("  go      %s %s/%s", runtime.Version(), runtime.GOOS, runtime.GOARCH))

	return strings.Join(lines, "\n") + "\n"
}

// built renders the build stamp readably, with its age. The stamp is fixed when the
// binary is compiled; the age is worked out when you run it.
func (b BuildInfo) built() string {
	if b.Date == "" || b.Date == "unknown" {
		return ""
	}
	t, err := time.Parse(time.RFC3339, b.Date)
	if err != nil {
		// Better to show the raw stamp than to hide it because it is in a shape this
		// does not recognise.
		return b.Date
	}
	return fmt.Sprintf("%s (%s)", t.UTC().Format("2006-01-02 15:04 UTC"), age(time.Since(t)))
}

func age(d time.Duration) string {
	switch {
	case d < 0:
		// A stamp in the future means clock skew somewhere; say so rather than
		// printing a negative age.
		return "clock skew"
	case d < time.Minute:
		return "just now"
	case d < time.Hour:
		return plural(int(d.Minutes()), "minute") + " ago"
	case d < 24*time.Hour:
		return plural(int(d.Hours()), "hour") + " ago"
	default:
		return plural(int(d.Hours()/24), "day") + " ago"
	}
}

func plural(n int, unit string) string {
	if n == 1 {
		return "1 " + unit
	}
	return fmt.Sprintf("%d %ss", n, unit)
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
