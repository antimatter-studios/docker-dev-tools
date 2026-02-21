package service

import (
	"fmt"
	"os"
	"path/filepath"

	"github.com/christhomas/docker-dev-tools/internal/config"
)

// ProjectService manages projects and their configurations.
type ProjectService struct {
	config *config.SystemConfig
}

// NewProjectService creates a new project service.
func NewProjectService(cfg *config.SystemConfig) *ProjectService {
	return &ProjectService{config: cfg}
}

// AddPath registers a directory path to scan for projects.
func (s *ProjectService) AddPath(dir, name string) error {
	absDir, err := filepath.Abs(dir)
	if err != nil {
		return fmt.Errorf("resolving path: %w", err)
	}
	info, err := os.Stat(absDir)
	if err != nil || !info.IsDir() {
		return fmt.Errorf("not a valid directory: %s", absDir)
	}
	s.config.Projects.Paths[name] = absDir
	return s.config.Save()
}

// RemovePath removes a registered directory path.
func (s *ProjectService) RemovePath(name string) error {
	if _, ok := s.config.Projects.Paths[name]; !ok {
		return fmt.Errorf("path %q not found", name)
	}
	delete(s.config.Projects.Paths, name)
	return s.config.Save()
}

// ListPaths returns all registered project paths.
func (s *ProjectService) ListPaths() map[string]string {
	return s.config.Projects.Paths
}

// AddProject registers a specific project.
func (s *ProjectService) AddProject(dir, name, group string) error {
	absDir, err := filepath.Abs(dir)
	if err != nil {
		return fmt.Errorf("resolving path: %w", err)
	}
	s.config.Projects.List[name] = config.ProjectEntry{
		Path:  absDir,
		Group: group,
	}
	return s.config.Save()
}

// RemoveProject removes a registered project.
func (s *ProjectService) RemoveProject(name string) error {
	if _, ok := s.config.Projects.List[name]; !ok {
		return fmt.Errorf("project %q not found", name)
	}
	delete(s.config.Projects.List, name)
	return s.config.Save()
}

// ListProjects returns all registered projects.
func (s *ProjectService) ListProjects() map[string]config.ProjectEntry {
	return s.config.Projects.List
}

// DiscoverProjects scans all registered paths for projects with config files.
func (s *ProjectService) DiscoverProjects() (map[string]*config.ProjectConfig, error) {
	projects := make(map[string]*config.ProjectConfig)

	for _, dir := range s.config.Projects.Paths {
		entries, err := os.ReadDir(dir)
		if err != nil {
			continue
		}
		for _, entry := range entries {
			if !entry.IsDir() {
				continue
			}
			projectDir := filepath.Join(dir, entry.Name())
			cfg, err := config.LoadProjectConfig(projectDir)
			if err != nil || cfg == nil {
				continue
			}
			projects[entry.Name()] = cfg
		}
	}

	return projects, nil
}

// GetProjectConfig loads the configuration for a named project.
func (s *ProjectService) GetProjectConfig(name string) (*config.ProjectConfig, error) {
	entry, ok := s.config.Projects.List[name]
	if !ok {
		return nil, fmt.Errorf("project %q not found", name)
	}
	cfg, err := config.LoadProjectConfig(entry.Path)
	if err != nil {
		return nil, err
	}
	if cfg == nil {
		return nil, fmt.Errorf("no project config found in %s", entry.Path)
	}
	return cfg, nil
}
