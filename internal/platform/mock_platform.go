package platform

import "fmt"

// MockPlatform is a test double for the Platform interface.
// All operations are recorded in memory with no side effects.
type MockPlatform struct {
	PlatformName     string
	Aliases          map[string]bool // active IP aliases
	InstalledAliases map[string]bool // persistently installed aliases
	DNSEntries       map[string]string // domain -> "ip:port"
	FlushedDNS       int
	Upstreams        []string
	Errors           map[string]error // method name -> error to return
}

// NewMockPlatform creates a MockPlatform with initialized maps.
func NewMockPlatform(name string) *MockPlatform {
	return &MockPlatform{
		PlatformName:     name,
		Aliases:          make(map[string]bool),
		InstalledAliases: make(map[string]bool),
		DNSEntries:       make(map[string]string),
		Errors:           make(map[string]error),
	}
}

func (m *MockPlatform) Name() string { return m.PlatformName }

func (m *MockPlatform) AddIPAlias(ip string) error {
	if err := m.Errors["AddIPAlias"]; err != nil {
		return err
	}
	m.Aliases[ip] = true
	return nil
}

func (m *MockPlatform) RemoveIPAlias(ip string) error {
	if err := m.Errors["RemoveIPAlias"]; err != nil {
		return err
	}
	delete(m.Aliases, ip)
	return nil
}

func (m *MockPlatform) HasIPAlias(ip string) (bool, error) {
	if err := m.Errors["HasIPAlias"]; err != nil {
		return false, err
	}
	return m.Aliases[ip], nil
}

func (m *MockPlatform) InstallIPAlias(ip string) error {
	if err := m.Errors["InstallIPAlias"]; err != nil {
		return err
	}
	m.InstalledAliases[ip] = true
	m.Aliases[ip] = true // installing also activates
	return nil
}

func (m *MockPlatform) UninstallIPAlias(ip string) error {
	if err := m.Errors["UninstallIPAlias"]; err != nil {
		return err
	}
	delete(m.InstalledAliases, ip)
	delete(m.Aliases, ip)
	return nil
}

func (m *MockPlatform) IsIPAliasInstalled(ip string) (bool, error) {
	if err := m.Errors["IsIPAliasInstalled"]; err != nil {
		return false, err
	}
	return m.InstalledAliases[ip], nil
}

func (m *MockPlatform) EnableDNS(domain, ip string, port int) (bool, error) {
	if err := m.Errors["EnableDNS"]; err != nil {
		return false, err
	}
	key := domain
	val := fmt.Sprintf("%s:%d", ip, port)
	if m.DNSEntries[key] == val {
		return false, nil
	}
	m.DNSEntries[key] = val
	return true, nil
}

func (m *MockPlatform) DisableDNS(domain string) error {
	if err := m.Errors["DisableDNS"]; err != nil {
		return err
	}
	delete(m.DNSEntries, domain)
	return nil
}

func (m *MockPlatform) FlushDNS() error {
	if err := m.Errors["FlushDNS"]; err != nil {
		return err
	}
	m.FlushedDNS++
	return nil
}

func (m *MockPlatform) GetSystemUpstreams() ([]string, error) {
	if err := m.Errors["GetSystemUpstreams"]; err != nil {
		return nil, err
	}
	return m.Upstreams, nil
}
