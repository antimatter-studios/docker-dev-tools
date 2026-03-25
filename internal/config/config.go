package config

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
)

const (
	// ConfigDirName is the directory name under XDG_CONFIG_HOME.
	ConfigDirName = "docker-dev-tools"
	// SystemConfigFilename is the config file name inside the config directory.
	SystemConfigFilename = "config.json"
	// ProjectConfigFilename is the name of per-project config files.
	ProjectConfigFilename = "ddt-project.json"
	// CurrentVersion is the config schema version.
	CurrentVersion = "3"
)

// ConfigDir returns the directory for ddt configuration files.
// Uses $XDG_CONFIG_HOME/docker-dev-tools if set, otherwise ~/.config/docker-dev-tools.
func ConfigDir() string {
	if xdg := os.Getenv("XDG_CONFIG_HOME"); xdg != "" {
		return filepath.Join(xdg, ConfigDirName)
	}
	home, err := os.UserHomeDir()
	if err != nil {
		return ConfigDirName
	}
	return filepath.Join(home, ".config", ConfigDirName)
}

// ConfigPath returns the full path to the system config file.
func ConfigPath() string {
	return filepath.Join(ConfigDir(), SystemConfigFilename)
}

// LoadJSON reads a JSON file into the target struct.
func LoadJSON(path string, target any) error {
	data, err := os.ReadFile(path)
	if err != nil {
		return fmt.Errorf("reading config %s: %w", path, err)
	}
	if err := json.Unmarshal(data, target); err != nil {
		return fmt.Errorf("parsing config %s: %w", path, err)
	}
	return nil
}

// SaveJSON writes a struct to a JSON file with indentation.
// Creates parent directories if they don't exist.
func SaveJSON(path string, data any) error {
	bytes, err := json.MarshalIndent(data, "", "  ")
	if err != nil {
		return fmt.Errorf("marshalling config: %w", err)
	}
	if err := os.MkdirAll(filepath.Dir(path), 0755); err != nil {
		return fmt.Errorf("creating config directory: %w", err)
	}
	if err := os.WriteFile(path, bytes, 0644); err != nil {
		return fmt.Errorf("writing config %s: %w", path, err)
	}
	return nil
}
