package platform

func (m *MockPlatform) TrustCA(certPath string) error {
	if err := m.Errors["TrustCA"]; err != nil {
		return err
	}
	if m.TrustedCAs == nil {
		m.TrustedCAs = make(map[string]bool)
	}
	m.TrustedCAs[certPath] = true
	m.CATrustLog = append(m.CATrustLog, "trust:"+certPath)
	return nil
}

func (m *MockPlatform) UntrustCA(certPath string) error {
	if err := m.Errors["UntrustCA"]; err != nil {
		return err
	}
	delete(m.TrustedCAs, certPath)
	m.CATrustLog = append(m.CATrustLog, "untrust:"+certPath)
	return nil
}

func (m *MockPlatform) IsCATrusted(certPath string) (bool, error) {
	if err := m.Errors["IsCATrusted"]; err != nil {
		return false, err
	}
	return m.TrustedCAs[certPath], nil
}

func (m *MockPlatform) TrustCAInSimulators(certPath string) (int, error) {
	if err := m.Errors["TrustCAInSimulators"]; err != nil {
		return 0, err
	}
	if m.BootedSimulators > 0 {
		m.SimulatorCAs = append(m.SimulatorCAs, certPath)
	}
	return m.BootedSimulators, nil
}
