package model

// RunProfile represents a Docker connection profile for remote Docker hosts.
type RunProfile struct {
	Name     string `json:"name"`
	Host     string `json:"host"`
	CertPath string `json:"cert_path,omitempty"`
}

// SyncProfile represents a Docker file sync configuration.
type SyncProfile struct {
	Name   string `json:"name"`
	Source string `json:"source"`
	Target string `json:"target"`
}
