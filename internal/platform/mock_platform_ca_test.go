package platform

import (
	"errors"
	"testing"
)

func TestMockCATrustLifecycle(t *testing.T) {
	m := NewMockPlatform("test")
	const path = "/tmp/ca.crt"

	if trusted, _ := m.IsCATrusted(path); trusted {
		t.Fatal("trusted before TrustCA")
	}
	if err := m.TrustCA(path); err != nil {
		t.Fatal(err)
	}
	if trusted, _ := m.IsCATrusted(path); !trusted {
		t.Error("not trusted after TrustCA")
	}
	if err := m.UntrustCA(path); err != nil {
		t.Fatal(err)
	}
	if trusted, _ := m.IsCATrusted(path); trusted {
		t.Error("still trusted after UntrustCA")
	}
	if want := []string{"trust:" + path, "untrust:" + path}; len(m.CATrustLog) != 2 || m.CATrustLog[0] != want[0] || m.CATrustLog[1] != want[1] {
		t.Errorf("CATrustLog = %v, want %v", m.CATrustLog, want)
	}
}

func TestMockCAErrorInjection(t *testing.T) {
	m := NewMockPlatform("test")
	testErr := errors.New("simulated failure")

	m.Errors["TrustCA"] = testErr
	if err := m.TrustCA("/tmp/ca.crt"); !errors.Is(err, testErr) {
		t.Errorf("expected injected error, got: %v", err)
	}
	if trusted, _ := m.IsCATrusted("/tmp/ca.crt"); trusted {
		t.Error("a failed TrustCA still trusted the CA")
	}

	m.Errors["IsCATrusted"] = testErr
	if _, err := m.IsCATrusted("/tmp/ca.crt"); !errors.Is(err, testErr) {
		t.Errorf("expected injected error, got: %v", err)
	}
}

func TestMockTrustCAInSimulators(t *testing.T) {
	m := NewMockPlatform("test")
	if n, err := m.TrustCAInSimulators("/tmp/ca.crt"); err != nil || n != 0 {
		t.Errorf("with none booted: n=%d err=%v", n, err)
	}

	m.BootedSimulators = 2
	n, err := m.TrustCAInSimulators("/tmp/ca.crt")
	if err != nil || n != 2 {
		t.Errorf("n=%d err=%v, want 2", n, err)
	}
	if len(m.SimulatorCAs) != 1 {
		t.Errorf("SimulatorCAs = %v", m.SimulatorCAs)
	}
}
