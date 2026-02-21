package config

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
)

const (
	// SystemConfigFilename is the name of the system-wide config file.
	SystemConfigFilename = ".ddt-system.json"
	// ProjectConfigFilename is the name of per-project config files.
	ProjectConfigFilename = "ddt-project.json"
	// CurrentVersion is the config schema version.
	CurrentVersion = "3"
)

// ConfigPath returns the full path to the system config file.
func ConfigPath() string {
	home, err := os.UserHomeDir()
	if err != nil {
		return SystemConfigFilename
	}
	return filepath.Join(home, SystemConfigFilename)
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
func SaveJSON(path string, data any) error {
	bytes, err := json.MarshalIndent(data, "", "  ")
	if err != nil {
		return fmt.Errorf("marshalling config: %w", err)
	}
	if err := os.WriteFile(path, bytes, 0644); err != nil {
		return fmt.Errorf("writing config %s: %w", path, err)
	}
	return nil
}
