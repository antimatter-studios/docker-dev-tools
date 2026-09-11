# ddt - Docker Dev Tools

A toolkit for managing local Docker development environments. Provides IP aliasing, local DNS, reverse proxying, project management, and script execution with dependency resolution.

Built in Go with a modern terminal UI powered by [Bubbletea](https://github.com/charmbracelet/bubbletea).

## Installation

### Homebrew (recommended)

```bash
brew install antimatter-studios/tap/ddt
```

### Go install

```bash
go install github.com/christhomas/docker-dev-tools/cmd/ddt@latest
```

### From source

```bash
task build
./bin/ddt install
```

## Quick Start

After installing, run the interactive setup wizard:

```bash
ddt install
```

The wizard walks you through:
1. **IP address** — configure the loopback alias for host-to-container communication (default: `10.254.254.254`)
2. **DNS TLDs** — add wildcard top-level domains for local resolution (e.g. `.develop`)
3. **System services** — installs the IP alias as a persistent system service and configures DNS resolver files

The wizard saves config to `~/.config/docker-dev-tools/config.json` (respects `$XDG_CONFIG_HOME`).

To remove everything the wizard installed:

```bash
ddt uninstall
```

Once installed, check system status:

```bash
ddt status
```

## Features

### IP Alias Management
Create stable IP aliases for host-to-container communication (XDebug, etc.):
```bash
ddt ip set 10.254.254.254
ddt ip add          # Add alias to system
ddt ip status       # Check if active
ddt ip ping         # Test connectivity
```

### Local DNS Server
Run a dnsmasq-based DNS server with wildcard domain support:
```bash
ddt dns add-tld develop                 # *.develop resolves
ddt dns start
ddt dns status
ddt dns logs -f
```

### Reverse Proxy
Auto-configuring NGINX proxy that routes traffic based on container `VIRTUAL_HOST` environment variables.
Networks are managed automatically — docker-config-gen discovers which networks have proxied containers and connects the proxy dynamically.
```bash
ddt proxy start
ddt proxy status
```

The reverse proxy is composed of two containers managed by DDT:

- **Proxy** (`docker-proxy`) runs NGINX and attaches to the configured Docker networks.
- **Configuration generator** (`docker-config-gen`) watches Docker events via the Docker socket and communicates with the proxy over a Unix socket on a shared volume.

The config generator does not need to join your project networks to observe containers; it uses the Docker API via the socket.

Containers register themselves for proxying using `docker-proxy.*` labels:
```yaml
services:
  website:
    labels:
      - docker-proxy.web.host=www.mycompany.develop
      - docker-proxy.web.port=80
      - docker-proxy.web.path=^/api
    networks:
      - backbone
```

Labels follow the pattern `docker-proxy.{tag}.{field}` where `tag` is an arbitrary group name and `field` is one of:

| Field      | Default | Description                         |
|------------|---------|-------------------------------------|
| `host`     | —       | Hostname to route (required)        |
| `port`     | `80`    | Target container port               |
| `protocol` | `http`  | Upstream scheme (`http` or `https`) |
| `path`     | `/`     | URL path regex pattern              |

A group with `proto=tcp` or `proto=udp` is a raw TCP or UDP stream rather than
an HTTP route; docker-config-gen proxies those separately.

A single container can define multiple routes using different tags:
```yaml
labels:
  - docker-proxy.app.host=app.develop
  - docker-proxy.app.port=8080
  - docker-proxy.api.host=api.develop
  - docker-proxy.api.port=3000
  - docker-proxy.api.path=^/v1
```

Environment variables (`VIRTUAL_HOST`, `VIRTUAL_PORT`, `VIRTUAL_PROTO`, `VIRTUAL_PATH`) are also supported for backward compatibility, but they only allow a single route per container. Labels solve this limitation — by grouping fields under different tags, one container can serve multiple hostnames or path patterns. For example, a container running both a website and an API can expose each on its own hostname with independent port and path settings, which isn't possible with environment variables.

### Project Management
Register project directories and manage them as a group:
```bash
ddt project add-path ~/projects client-a
ddt project list
```

### Script Runner
Execute scripts across projects with dependency resolution:
```bash
ddt run start my-project       # Run 'start' script with deps
ddt run --list my-project      # List available scripts
```

Projects define scripts in `ddt-project.json`:
```json
{
  "scripts": {
    "start": "docker compose up -d",
    "stop": "docker compose stop",
    "up": ["start"],
    "down": ["stop"]
  },
  "dependencies": {
    "service-a": {
      "repo": { "url": "git@github.com:org/service-a.git", "branch": "main" },
      "scripts": ["up", "down"]
    }
  }
}
```

### Configuration
```bash
ddt config show         # Display full config
ddt config path         # Show config file location
ddt config version      # Show schema version
ddt config reset        # Reset to defaults
```

## Development

### Prerequisites
- [Go 1.25+](https://go.dev/dl/)
- [Task](https://taskfile.dev/) (build system)
- [Docker](https://docs.docker.com/get-docker/)

### Build & Run
```bash
task build              # Build for current platform
task run -- status      # Build and run with arguments
go run ./cmd/ddt -- status  # Run directly without building
task test               # Run tests
task lint               # Run linter
task fmt                # Format code
```

### Cross-Compilation
```bash
task build:all              # All platforms
task build:darwin-arm64     # macOS Apple Silicon
task build:linux-amd64      # Linux x86_64
```

### Project Structure
```
cmd/ddt/              Entry point
internal/
  app/                Application bootstrap and DI
  cli/                Cobra command definitions
  config/             Configuration system (JSON)
  docker/             Docker SDK client wrapper
  service/            Business logic layer
  platform/           OS-specific implementations (macOS/Linux)
  tui/                Bubbletea UI components
    styles/           Lipgloss style definitions
    components/       Reusable UI components
  model/              Data models
docs/                 Architecture and planning docs
.github/workflows/    CI/CD pipelines
```

### Architecture
- **Cobra** for CLI command routing, argument parsing, and help generation
- **Bubbletea** for rich terminal UI (spinners, dashboards, interactive prompts)
- **Lipgloss** for styled output (tables, colored text, boxes)
- **Docker SDK** for direct Docker daemon communication (no CLI shelling)
- **Build tags** for platform-specific code (darwin/linux)
- **JSON configs** compatible with the original PHP version

## Configuration

Project configs can be:
1. `ddt-project.json` (preferred)
2. `composer.json` with `"docker-dev-tools"` section
3. `package.json` with `"docker-dev-tools"` section

## License

MIT
