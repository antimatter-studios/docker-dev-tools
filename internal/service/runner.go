package service

import (
	"fmt"
	"os"
	"os/exec"

	"github.com/christhomas/docker-dev-tools/internal/config"
)

// RunnerService handles script execution with dependency resolution.
type RunnerService struct {
	config  *config.SystemConfig
	project *ProjectService
}

// NewRunnerService creates a new runner service.
func NewRunnerService(cfg *config.SystemConfig, project *ProjectService) *RunnerService {
	return &RunnerService{config: cfg, project: project}
}

// Run executes a named script for a project, resolving dependencies first.
func (s *RunnerService) Run(projectName, scriptName string) error {
	cfg, err := s.project.GetProjectConfig(projectName)
	if err != nil {
		return err
	}

	return s.executeScript(cfg, scriptName, make(map[string]bool))
}

// executeScript runs a script, resolving references recursively.
func (s *RunnerService) executeScript(cfg *config.ProjectConfig, scriptName string, visited map[string]bool) error {
	key := cfg.Dir() + ":" + scriptName
	if visited[key] {
		return fmt.Errorf("circular script dependency detected: %s", key)
	}
	visited[key] = true

	command, refs, err := cfg.ResolveScript(scriptName)
	if err != nil {
		return err
	}

	// If it's a list of references, resolve each.
	if refs != nil {
		for _, ref := range refs {
			if err := s.executeScript(cfg, ref, visited); err != nil {
				return err
			}
		}
		return nil
	}

	// If it's a direct command, execute it.
	if command != "" {
		return s.exec(cfg.Dir(), command)
	}

	return fmt.Errorf("script %q not found", scriptName)
}

// exec runs a shell command in a directory.
func (s *RunnerService) exec(dir, command string) error {
	cmd := exec.Command("sh", "-c", command)
	cmd.Dir = dir
	cmd.Stdout = os.Stdout
	cmd.Stderr = os.Stderr
	cmd.Stdin = os.Stdin
	return cmd.Run()
}

// ListScripts returns available scripts for a project.
func (s *RunnerService) ListScripts(projectName string) ([]string, error) {
	cfg, err := s.project.GetProjectConfig(projectName)
	if err != nil {
		return nil, err
	}

	scripts := make([]string, 0, len(cfg.Scripts))
	for name := range cfg.Scripts {
		scripts = append(scripts, name)
	}
	return scripts, nil
}
