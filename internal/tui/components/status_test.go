package components

import (
	"strings"
	"testing"
)

func TestFormatHTTPStatus(t *testing.T) {
	tests := []struct {
		code     int
		status   string
		contains string
	}{
		{200, "200", "200"},
		{201, "201", "201"},
		{301, "301", "301"},
		{404, "404", "404"},
		{500, "500", "500"},
		{0, "error", "error"},
	}

	for _, tt := range tests {
		result := FormatHTTPStatus(tt.code, tt.status)
		if !strings.Contains(result, tt.contains) {
			t.Errorf("FormatHTTPStatus(%d, %q) = %q, should contain %q", tt.code, tt.status, result, tt.contains)
		}
	}

	// Different classes should have different styling.
	s200 := FormatHTTPStatus(200, "200")
	s404 := FormatHTTPStatus(404, "404")
	s500 := FormatHTTPStatus(500, "500")

	if s200 == s404 {
		t.Error("2xx and 4xx should have different styling")
	}
	if s200 == s500 {
		t.Error("2xx and 5xx should have different styling")
	}
}

func TestCheckMark(t *testing.T) {
	okResult := CheckMark(true)
	failResult := CheckMark(false)

	if okResult == "" {
		t.Error("CheckMark(true) should not be empty")
	}
	if failResult == "" {
		t.Error("CheckMark(false) should not be empty")
	}
	if okResult == failResult {
		t.Error("CheckMark(true) and CheckMark(false) should differ")
	}
}

func TestToSet(t *testing.T) {
	tests := []struct {
		name  string
		input []string
		want  map[string]bool
	}{
		{"nil input", nil, map[string]bool{}},
		{"empty slice", []string{}, map[string]bool{}},
		{"single item", []string{"develop"}, map[string]bool{"develop": true}},
		{"multiple items", []string{"develop", "test", "local"}, map[string]bool{
			"develop": true,
			"test":    true,
			"local":   true,
		}},
		{"duplicates", []string{"develop", "test", "develop"}, map[string]bool{
			"develop": true,
			"test":    true,
		}},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got := toSet(tt.input)
			if len(got) != len(tt.want) {
				t.Errorf("toSet() length = %d, want %d", len(got), len(tt.want))
			}
			for k, v := range tt.want {
				if got[k] != v {
					t.Errorf("toSet()[%q] = %v, want %v", k, got[k], v)
				}
			}
		})
	}
}

func TestBuildDNSTLDRows(t *testing.T) {
	data := DNSTLDData{
		AllTLDs:     []string{"develop", "test"},
		InConfig:    map[string]bool{"develop": true, "test": true},
		InContainer: map[string]bool{"develop": true},
		InSystem:    map[string]bool{"develop": true, "test": true},
		DNSRunning:  true,
	}

	rows := buildDNSTLDRows(data)
	if len(rows) != 2 {
		t.Fatalf("expected 2 rows, got %d", len(rows))
	}

	// First row: .develop — all true.
	if rows[0][0] != ".develop" {
		t.Errorf("expected .develop, got %q", rows[0][0])
	}

	// Second row: .test — config yes, container no (not in InContainer), system yes.
	if rows[1][0] != ".test" {
		t.Errorf("expected .test, got %q", rows[1][0])
	}
	// Container column for "test" should be cross (not in container).
	if rows[1][2] == rows[0][2] {
		t.Error("test container column should differ from develop (missing from container)")
	}
}

func TestBuildDNSTLDRowsContainerStopped(t *testing.T) {
	data := DNSTLDData{
		AllTLDs:     []string{"develop"},
		InConfig:    map[string]bool{"develop": true},
		InContainer: map[string]bool{},
		InSystem:    map[string]bool{"develop": true},
		DNSRunning:  false,
	}

	rows := buildDNSTLDRows(data)
	if len(rows) != 1 {
		t.Fatalf("expected 1 row, got %d", len(rows))
	}

	// Container column should be cross when DNS not running.
	if rows[0][2] != CheckMark(false) {
		t.Error("container column should be cross when DNS is stopped")
	}
}
