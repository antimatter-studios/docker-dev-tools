package config

import (
	"encoding/json"
	"os"
	"path/filepath"
)

// ProjectConfig represents a per-project ddt-project.json configuration.
type ProjectConfig struct {
	Scripts      map[string]json.RawMessage `json:"scripts,omitempty"`
	Dependencies map[string]Dependency      `json:"dependencies,omitempty"`

	// dir is the project root directory.
	dir string
}

// Dependency represents a project dependency with optional repo info.
type Dependency struct {
	Repo    *RepoConfig `json:"repo,omitempty"`
	Scripts []string    `json:"scripts,omitempty"`
}

// RepoConfig holds git repository information for a dependency.
type RepoConfig struct {
	URL    string `json:"url"`
	Branch string `json:"branch,omitempty"`
}

// Dir returns the project root directory.
func (p *ProjectConfig) Dir() string {
	return p.dir
}

// ResolveScript resolves a script name to either a command string or
// a list of script references. Returns the command string and whether
// it's a list of references.
func (p *ProjectConfig) ResolveScript(name string) (command string, refs []string, err error) {
	raw, ok := p.Scripts[name]
	if !ok {
		return "", nil, nil
	}

	// Try string first.
	var str string
	if err := json.Unmarshal(raw, &str); err == nil {
		return str, nil, nil
	}

	// Try array of strings.
	var arr []string
	if err := json.Unmarshal(raw, &arr); err == nil {
		return "", arr, nil
	}

	return "", nil, nil
}

// LoadProjectConfig attempts to load project configuration from a directory.
// It checks for ddt-project.json first, then falls back to composer.json
// and package.json sections.
func LoadProjectConfig(dir string) (*ProjectConfig, error) {
	// Try ddt-project.json first.
	ddtPath := filepath.Join(dir, ProjectConfigFilename)
	if _, err := os.Stat(ddtPath); err == nil {
		cfg := &ProjectConfig{}
		if err := LoadJSON(ddtPath, cfg); err != nil {
			return nil, err
		}
		cfg.dir = dir
		return cfg, nil
	}

	// Try composer.json with docker-dev-tools section.
	composerPath := filepath.Join(dir, "composer.json")
	if cfg, err := loadEmbeddedConfig(composerPath, dir); err == nil && cfg != nil {
		return cfg, nil
	}

	// Try package.json with docker-dev-tools section.
	packagePath := filepath.Join(dir, "package.json")
	if cfg, err := loadEmbeddedConfig(packagePath, dir); err == nil && cfg != nil {
		return cfg, nil
	}

	return nil, nil
}

// loadEmbeddedConfig loads a docker-dev-tools section from a composer.json
// or package.json file.
func loadEmbeddedConfig(path, dir string) (*ProjectConfig, error) {
	data, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}

	var wrapper struct {
		DDT *ProjectConfig `json:"docker-dev-tools"`
	}
	if err := json.Unmarshal(data, &wrapper); err != nil {
		return nil, err
	}

	if wrapper.DDT == nil {
		return nil, nil
	}

	wrapper.DDT.dir = dir
	return wrapper.DDT, nil
}
