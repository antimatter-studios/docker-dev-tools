# Feature Parity — What Can Users Do?

What the PHP tool lets you accomplish, and where the Go rewrite stands.

`[x]` done | `[~]` stubbed/partial | `[ ]` missing | `[-]` intentionally dropped

---

## 1. Stable IP address for your dev machine
Give your machine a fixed IP (e.g., `10.254.254.254`) so containers can call back to the host — XDebug, webhooks, etc.

- [x] Configure which IP to use
- [x] Add/remove the alias on the loopback interface (macOS + Linux)
- [x] Check whether it's currently active
- [x] Ping it to verify connectivity (Go: animated live latency graph)
- [ ] Reset (remove + re-add in one shot)

---

## 2. Local DNS with wildcard domains
Run a dnsmasq container so `*.mycompany.develop` resolves to your dev IP — no `/etc/hosts` hacking.

- [~] Start/stop/restart the DNS container
- [~] Add a domain (wildcard automatically)
- [ ] Remove a domain
- [ ] Enable/disable DNS on the system without stopping the container
- [ ] Refresh after a VPN session breaks resolution
- [ ] Manage upstream DNS servers
- [ ] View the actual dnsmasq config inside the container
- [ ] Ping all configured domains to verify they resolve correctly
- [~] View container logs

---

## 3. Auto-configuring reverse proxy
An NGINX proxy that watches the Docker socket — when a container with `VIRTUAL_HOST` starts, it automatically routes traffic to it.

- [~] Start/stop/restart the proxy + config-gen containers
- [~] Register Docker networks to monitor
- [ ] Live-scan running containers to show what's being proxied (host, port, path)
- [ ] Show the generated NGINX config for debugging
- [ ] Hot-reload NGINX without restarting
- [~] View container logs

---

## 4. Multi-project management
Register directories full of projects so you can operate on them as a fleet instead of one at a time.

- [x] Register a directory path (every subdirectory = a project)
- [x] Register individual projects manually
- [~] List projects with their type, group, path, and repo URL
- [ ] Group projects (e.g., by client, team, environment)
- [ ] Auto-detect project type (ddt-project.json / composer.json / package.json)
- [ ] Git pull/push across all projects in one command
- [ ] Clone a repo and auto-register it as a project

---

## 5. Script runner with dependency resolution
Define scripts in `ddt-project.json` and run them across projects — dependencies get started automatically.

- [~] Run a named script for a project
- [~] List available scripts
- [ ] Dependency tree: project A depends on B, running `start` on A starts B first
- [ ] Show the full dependency tree before executing
- [ ] Run across an entire group at once
- [ ] Permission system: dependencies declare which scripts they allow to be called
- [ ] Argument passthrough to scripts

---

## 6. System status dashboard
One command to see if everything is healthy.

- [x] Combined view of IP alias, DNS, and proxy status
- [x] Rich TUI with side-by-side cards and colored indicators (Go improvement)
- [ ] Per-project health checks

---

## 7. Configuration management
JSON config at `~/.ddt-system.json` — stores all settings, project paths, networks, etc.

- [x] View the full config / config path
- [x] Reset to defaults
- [ ] Get/set individual values by dotted path (e.g., `ddt config get .dns.docker_image`)
- [ ] Migrate config schema between versions (v1 → v2 → v3)
- [x] Backward-compatible with the PHP config format

---

## 8. Installation and shell integration
Get `ddt` available globally in your terminal.

- [x] Initial setup (create config)
- [ ] Add `ddt` to shell PATH (bash/zsh)
- [ ] Uninstall (clean up PATH entries)
- [ ] Self-test (verify installation in a new shell)

---

## 9. Self-updating
Automatically pull the latest version periodically.

- [ ] Auto-update on a configurable timer (e.g., every 7 days)
- [ ] Manual trigger
- [ ] Update extensions alongside the main tool

---

## 10. Extension system
Let users add custom tools via Git repos that plug into the `ddt` command.

- [ ] Install/uninstall extensions from Git URLs
- [ ] Auto-load extensions at boot
- [ ] List installed extensions

---

## 11. Docker remote profiles and file sync
Connect to remote Docker hosts and sync files into containers (for remote dev servers).

- [ ] Store connection profiles (host, port, TLS certs)
- [ ] Run docker commands against remote hosts
- [ ] Watch local files and sync changes into a running container

---

## 12. Wrapper tools (Docker-ised CLI commands)
Run tools inside containers so nothing needs installing locally.

- [-] `ddt composer` — run Composer in a container (dropped: use devcontainers)
- [-] `ddt aws` — run AWS CLI in a container (dropped: company-specific)
- [-] `ddt terraform` — run Terraform with AWS profile injection (dropped: company-specific)

---

## Summary

| Capability                          | Status   |
|-------------------------------------|----------|
| Stable dev IP alias                 | **Done** |
| Local DNS with wildcards            | Partial  |
| Auto-configuring reverse proxy      | Partial  |
| Multi-project management            | Partial  |
| Script runner + dependency tree     | Partial  |
| Status dashboard                    | **Done** |
| Configuration management            | Partial  |
| Shell installation                  | Partial  |
| Self-updating                       | Missing  |
| Extension system                    | Missing  |
| Docker remote profiles / file sync  | Missing  |
| Wrapper tools (composer/aws/tf)     | Dropped  |

### Recommended build order (next steps)
1. **DNS** — flesh out domain management, enable/disable, upstream servers, system resolver integration
2. **Proxy** — container scanning for VIRTUAL_HOST, live status table, NGINX config display
3. **Project management** — auto-detect types, groups, git operations across fleet
4. **Script runner** — dependency resolution, tree display, group execution
5. **Config** — dotted path get/set, schema migration
6. **Setup** — shell PATH integration, self-test
7. **Self-update / Extensions** — if still needed with Go binary distribution (goreleaser may replace self-update)
