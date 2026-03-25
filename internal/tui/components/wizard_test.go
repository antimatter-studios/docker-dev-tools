package components

import (
	"testing"
)

func TestNewWizardModelDefaults(t *testing.T) {
	m := newWizardModel("10.254.254.254", []string{"localhost"})

	if m.step != stepWelcome {
		t.Errorf("expected initial step to be welcome, got %d", m.step)
	}
	if m.ipInput.Value() != "10.254.254.254" {
		t.Errorf("expected IP input to be pre-filled, got %q", m.ipInput.Value())
	}
	if len(m.tlds) != 1 || m.tlds[0] != "localhost" {
		t.Errorf("expected tlds [localhost], got %v", m.tlds)
	}
}

func TestNewWizardModelEmptyDefaults(t *testing.T) {
	m := newWizardModel("", nil)

	if m.ipInput.Value() != "" {
		t.Errorf("expected empty IP input, got %q", m.ipInput.Value())
	}
	if len(m.tlds) != 0 {
		t.Errorf("expected empty tlds, got %v", m.tlds)
	}
}

func TestWizardHasTLD(t *testing.T) {
	m := newWizardModel("", []string{"localhost", "develop"})

	if !m.hasTLD("localhost") {
		t.Error("expected hasTLD(localhost) = true")
	}
	if !m.hasTLD("develop") {
		t.Error("expected hasTLD(develop) = true")
	}
	if m.hasTLD("test") {
		t.Error("expected hasTLD(test) = false")
	}
}

func TestWizardResultCancelledByDefault(t *testing.T) {
	m := newWizardModel("10.254.254.254", nil)
	if m.result.Cancelled {
		t.Error("result should not be cancelled initially")
	}
}

func TestWizardViewRendersWithoutPanic(t *testing.T) {
	// Ensure all view functions render without panicking at each step.
	steps := []wizardStep{stepWelcome, stepIP, stepTLDs, stepConfirm}
	for _, s := range steps {
		m := newWizardModel("10.254.254.254", []string{"localhost"})
		m.step = s
		m.result.IPAddress = "10.254.254.254"
		m.result.TLDs = []string{"localhost"}
		view := m.View()
		if view == "" {
			t.Errorf("expected non-empty view for step %d", s)
		}
	}
}

func TestSetConfigPathDisplay(t *testing.T) {
	original := configPathForDisplay

	SetConfigPathDisplay(func() string { return "/custom/path" })
	if got := configPathForDisplay(); got != "/custom/path" {
		t.Errorf("expected /custom/path, got %q", got)
	}

	// Restore.
	configPathForDisplay = original
}

func TestWizardTLDsViewShowsTags(t *testing.T) {
	m := newWizardModel("10.0.0.1", []string{"localhost", "develop"})
	m.step = stepTLDs
	view := m.viewTLDs(getWizardStyles())

	if !contains(view, ".localhost") {
		t.Error("expected .localhost tag in TLD view")
	}
	if !contains(view, ".develop") {
		t.Error("expected .develop tag in TLD view")
	}
}

func TestWizardConfirmViewShowsSummary(t *testing.T) {
	m := newWizardModel("10.254.254.254", []string{"localhost"})
	m.step = stepConfirm
	m.result.IPAddress = "10.254.254.254"
	m.result.TLDs = []string{"localhost"}
	view := m.viewConfirm(getWizardStyles())

	if !contains(view, "10.254.254.254") {
		t.Error("expected IP in confirm view")
	}
	if !contains(view, ".localhost") {
		t.Error("expected .localhost in confirm view")
	}
}

func contains(s, substr string) bool {
	return len(s) >= len(substr) && searchString(s, substr)
}

func searchString(s, substr string) bool {
	for i := 0; i <= len(s)-len(substr); i++ {
		if s[i:i+len(substr)] == substr {
			return true
		}
	}
	return false
}
