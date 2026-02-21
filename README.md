# ddt - Docker Dev Tools

A toolkit for managing local Docker development environments. Provides IP aliasing, local DNS, reverse proxying, project management, and script execution with dependency resolution.

Built in Go with a modern terminal UI powered by [Bubbletea](https://github.com/charmbracelet/bubbletea).

## Quick Start

```bash
# Install via go
go install github.com/christhomas/docker-dev-tools/cmd/ddt@latest

# Or build from source
task build
./bin/ddt setup install

# Check system status
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
ddt dns add-domain mycompany.develop    # *.mycompany.develop resolves
ddt dns start
ddt dns status
is ddt dns logs -f
```

### Reverse Proxy
Auto-configuring NGINX proxy that routes traffic based on container `VIRTUAL_HOST` environment variables:
```bash
ddt proxy add-network backbone
ddt proxy start
ddt proxy status
```

Containers expose themselves via environment variables in docker-compose:
```yaml
services:
  website:
    environment:
      - VIRTUAL_HOST=www.mycompany.develop
      - VIRTUAL_PORT=80
      - VIRTUAL_PATH=^/api
    networks:
      - backbone
```

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
- [Go 1.23+](https://go.dev/dl/)
- [Task](https://taskfile.dev/) (build system)
- [Docker](https://docs.docker.com/get-docker/)

### Build & Run
```bash
task build              # Build for current platform
task run -- status      # Build and run with arguments
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

System config lives at `~/.ddt-system.json`. Project configs can be:
1. `ddt-project.json` (preferred)
2. `composer.json` with `"docker-dev-tools"` section
3. `package.json` with `"docker-dev-tools"` section

## License

MIT
