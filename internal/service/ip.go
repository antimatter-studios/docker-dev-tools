package service

import (
	"fmt"
	"net"
	"time"

	"github.com/christhomas/docker-dev-tools/internal/config"
	"github.com/christhomas/docker-dev-tools/internal/platform"
)

// IPService manages IP alias operations.
type IPService struct {
	config   *config.SystemConfig
	platform platform.Platform
}

// NewIPService creates a new IP service.
func NewIPService(cfg *config.SystemConfig, plat platform.Platform) *IPService {
	return &IPService{config: cfg, platform: plat}
}

// Get returns the currently configured IP address.
func (s *IPService) Get() string {
	return s.config.IPAddress
}

// Set updates the configured IP address.
func (s *IPService) Set(ip string) error {
	if net.ParseIP(ip) == nil {
		return fmt.Errorf("invalid IP address: %s", ip)
	}
	s.config.IPAddress = ip
	return s.config.Save()
}

// Add creates the IP alias on the system.
func (s *IPService) Add() error {
	return s.platform.AddIPAlias(s.config.IPAddress)
}

// Remove deletes the IP alias from the system.
func (s *IPService) Remove() error {
	return s.platform.RemoveIPAlias(s.config.IPAddress)
}

// IsActive checks if the IP alias is currently configured.
func (s *IPService) IsActive() (bool, error) {
	return s.platform.HasIPAlias(s.config.IPAddress)
}

// Ping tests connectivity to the configured IP address.
func (s *IPService) Ping() (time.Duration, error) {
	start := time.Now()
	conn, err := net.DialTimeout("tcp", s.config.IPAddress+":80", 2*time.Second)
	if err != nil {
		// Try ICMP-style check via UDP.
		conn, err = net.DialTimeout("udp", s.config.IPAddress+":1", 2*time.Second)
		if err != nil {
			return 0, fmt.Errorf("host %s unreachable: %w", s.config.IPAddress, err)
		}
	}
	conn.Close()
	return time.Since(start), nil
}
