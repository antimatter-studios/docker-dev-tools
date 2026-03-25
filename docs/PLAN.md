# Docker Dev Tools (ddt) - Go Rewrite Plan

## Overview

Rewrite of the PHP-based `docker-dev-tools` CLI in Go, with a modern terminal UI
powered by Bubbletea, cross-platform support (macOS/Linux), multi-architecture
builds, and CI/CD via GitHub Actions.

## Architecture

### Tech Stack

| Component          | Choice                              | Rationale                                        |
|--------------------|-------------------------------------|--------------------------------------------------|
| Language           | Go 1.23+                            | Fast, single binary, cross-platform              |
| CLI Framework      | Cobra                               | Industry standard, completions, help generation   |
| Terminal UI        | Bubbletea + Bubbles + Lipgloss      | Modern TUI with Elm architecture                 |
| Forms/Prompts      | Huh                                 | Charm ecosystem, rich form components            |
| Logging            | Charm Log                           | Structured, styled terminal logging              |
| Configuration      | encoding/json (stdlib)              | Keep it simple, match existing JSON configs      |
| Docker SDK         | github.com/docker/docker/client     | Official Docker client for Go                    |
| Build System       | Taskfile (go-task)                  | Modern Make alternative, YAML-based              |
| CI/CD              | GitHub Actions + GoReleaser         | Multi-arch builds, automated releases            |
| Testing            | stdlib testing + testify            | Standard Go testing with assertions              |

### Project Structure

```
docker-dev-tools/
├── cmd/ddt/                  # Application entry point
│   └── main.go
├── internal/                 # Private application code
│   ├── app/                  # Application bootstrap and DI
│   │   └── app.go
│   ├── cli/                  # Cobra command definitions
│   │   ├── root.go           # Root command + global flags
│   │   ├── ip.go             # IP alias management
│   │   ├── dns.go            # DNS server management
│   │   ├── proxy.go          # Reverse proxy management
│   │   ├── project.go        # Project management
│   │   ├── run.go            # Script runner
│   │   ├── config.go         # Configuration management
│   │   ├── setup.go          # ddt install / ddt uninstall
│   │   ├── status.go         # System status
│   │   └── version.go        # Version info
│   ├── config/               # Configuration system
│   │   ├── system.go         # System-wide config (~/.config/docker-dev-tools/config.json)
│   │   ├── project.go        # Per-project config (ddt-project.json)
│   │   ├── defaults.go       # Default configuration values
│   │   └── config.go         # Shared config types and helpers
│   ├── docker/               # Docker client abstraction
│   │   ├── client.go         # Docker client wrapper
│   │   ├── container.go      # Container operations
│   │   ├── network.go        # Network operations
│   │   ├── image.go          # Image operations
│   │   └── volume.go         # Volume operations
│   ├── service/              # Business logic layer
│   │   ├── ip.go             # IP alias service
│   │   ├── dns.go            # DNS service
│   │   ├── proxy.go          # Proxy service
│   │   ├── project.go        # Project service
│   │   ├── runner.go         # Script execution service
│   │   └── git.go            # Git operations
│   ├── platform/             # Platform-specific implementations
│   │   ├── platform.go       # Platform interface
│   │   ├── darwin.go         # macOS implementation
│   │   └── linux.go          # Linux implementation
│   ├── tui/                  # Bubbletea UI layer
│   │   ├── app.go            # Main TUI application model
│   │   ├── styles/
│   │   │   └── styles.go     # Lipgloss style definitions
│   │   └── components/
│   │       ├── status.go     # Status dashboard component
│   │       ├── table.go      # Table renderer
│   │       └── spinner.go    # Spinner/progress component
│   └── model/                # Data models
│       ├── project.go        # Project model
│       ├── script.go         # Script/run configuration
│       └── docker.go         # Docker profile models
├── proxy-config/             # NGINX proxy configuration (carried over)
├── docs/                     # Documentation
│   └── PLAN.md               # This file
├── .github/workflows/        # CI/CD
│   ├── ci.yml                # Test + lint on push/PR
│   └── release.yml           # GoReleaser on tag
├── Taskfile.yml              # Build automation
├── .goreleaser.yml           # Release configuration
├── go.mod
└── README.md
```

### Design Principles

1. **Cobra for routing, Bubbletea for display** - Cobra handles command parsing,
   argument validation, and help text. Bubbletea renders rich output (status
   dashboards, progress spinners, interactive prompts).

2. **Service layer pattern** - Each domain (IP, DNS, proxy, projects) has a
   service struct that encapsulates business logic. Services receive dependencies
   via constructor injection.

3. **Platform interface** - A `Platform` interface abstracts OS-specific
   operations (IP aliasing, DNS configuration). Build tags select the
   implementation at compile time.

4. **Configuration compatibility** - The JSON config format remains compatible
   with the PHP version's JSON structure. Config location moved to
   `~/.config/docker-dev-tools/config.json` (XDG compliant).

## Feature Roadmap

### Phase 1: Foundation (Current)
- [x] Project scaffolding (Go module, directories, Taskfile, CI)
- [ ] Configuration system (load/save/validate JSON configs)
- [ ] Platform detection and interface
- [ ] Basic Bubbletea app model and styles
- [ ] Docker client abstraction

### Phase 2: Core Tools
- [ ] IP tool (set, get, add, remove, ping)
- [ ] DNS tool (start, stop, add-domain, status, logs)
- [ ] Proxy tool (start, stop, status, nginx-config) — networks managed dynamically by config-gen
- [ ] Status tool (combined dashboard view)

### Phase 3: Project Management
- [ ] Project tool (add-path, add-project, list, pull, push)
- [ ] Project config detection (ddt-project.json, composer.json, package.json)
- [ ] Run tool (script execution with dependency resolution)
- [ ] Script dependency tree resolution

### Phase 4: Polish
- [ ] Config tool (get, set, reset, version)
- [ ] Setup tool (install, uninstall, PATH management)
- [ ] Self-update mechanism
- [ ] Shell completions (bash, zsh, fish)
- [ ] Extension system

### Phase 5: Release
- [ ] GoReleaser multi-arch builds
- [ ] Homebrew tap
- [ ] Documentation site
- [ ] Migration guide from PHP version

## Configuration Format

### System Config (~/.config/docker-dev-tools/config.json)
```json
{
  "type": "system",
  "version": "3",
  "ip_address": "10.254.254.254",
  "dns": {
    "docker_image": "ghcr.io/antimatter-studios/docker-dns:latest",
    "container_name": "ddt-dns"
  },
  "proxy": {
    "docker_image": "ghcr.io/antimatter-studios/docker-proxy:latest",
    "container_name": "ddt-proxy",
    "network": []
  },
  "config_gen": {
    "docker_image": "ghcr.io/antimatter-studios/docker-config-gen:latest",
    "container_name": "ddt-config-gen",
    "network": []
  },
  "projects": {
    "paths": {},
    "list": {}
  },
  "self_update": {
    "timeout": 0,
    "period": "+7 day",
    "enabled": true
  }
}
```

### Project Config (ddt-project.json)
```json
{
  "scripts": {
    "start": "docker-compose up -d",
    "stop": "docker-compose stop",
    "up": ["start"],
    "down": ["stop"]
  },
  "dependencies": {
    "service-a": {
      "repo": {
        "url": "git@github.com:org/service-a.git",
        "branch": "main"
      },
      "scripts": ["up", "down"]
    }
  }
}
```

## Build Targets

| Target    | OS      | Architecture |
|-----------|---------|--------------|
| darwin    | macOS   | amd64        |
| darwin    | macOS   | arm64        |
| linux     | Linux   | amd64        |
| linux     | Linux   | arm64        |

## UI Improvements Over PHP Version

| Feature            | PHP (old)              | Go (new)                          |
|--------------------|------------------------|-----------------------------------|
| Output formatting  | ANSI escape codes      | Lipgloss styled components        |
| Tables             | Custom Text::Table     | Lipgloss table with borders       |
| Progress           | Dots/text              | Animated spinners + progress bars |
| Forms              | Simple stdin readline  | Huh interactive forms             |
| Status dashboard   | Plain text list        | Rich Bubbletea dashboard          |
| Logs               | Raw docker logs        | Streamed with syntax highlighting |
| Error display      | Red text               | Styled error panels with context  |
| Help               | Basic --help text      | Cobra auto-generated + styled     |
