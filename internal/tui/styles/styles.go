package styles

import (
	"fmt"
	"strings"

	"github.com/charmbracelet/lipgloss"
)

// Color palette — a cohesive dark-mode palette inspired by catppuccin/nord.
var (
	// Brand / accent colors.
	Primary   = lipgloss.Color("#B48EAD") // Soft purple
	Secondary = lipgloss.Color("#88C0D0") // Ice blue
	Accent    = lipgloss.Color("#81A1C1") // Steel blue
	Highlight = lipgloss.Color("#D08770") // Warm coral

	// Semantic colors.
	Success = lipgloss.Color("#A3BE8C") // Soft green
	Warning = lipgloss.Color("#EBCB8B") // Warm yellow
	Error   = lipgloss.Color("#BF616A") // Soft red
	Info    = lipgloss.Color("#A3C4E0") // Light steel blue

	// Neutral palette.
	Text    = lipgloss.Color("#ECEFF4") // Bright text
	TextDim = lipgloss.Color("#D8DEE9") // Slightly dimmed
	Subtle  = lipgloss.Color("#81868f") // Labels/secondary text
	Muted   = lipgloss.Color("#7B88A1") // Borders, de-emphasised
	Surface = lipgloss.Color("#3B4252") // Card backgrounds
	Base    = lipgloss.Color("#2E3440") // Deep background
)

// ── Typography ─────────────────────────────────────────────────────

var (
	// AppTitle renders the application brand.
	AppTitle = lipgloss.NewStyle().
			Bold(true).
			Foreground(Primary).
			MarginBottom(1)

	// SectionTitle renders a section heading inside a panel.
	SectionTitle = lipgloss.NewStyle().
			Bold(true).
			Foreground(Secondary).
			MarginBottom(1)

	// Heading for groups of key-value pairs.
	Heading = lipgloss.NewStyle().
		Bold(true).
		Foreground(Text).
		PaddingBottom(0)

	// Label is for key names in key-value displays.
	Label = lipgloss.NewStyle().
		Foreground(Subtle).
		Width(14).
		Align(lipgloss.Right)

	// Value is for the data shown next to a Label.
	Value = lipgloss.NewStyle().
		Foreground(TextDim)

	// ValueBright is for emphasised data values.
	ValueBright = lipgloss.NewStyle().
			Foreground(Text).
			Bold(true)

	// Dimmed renders de-emphasised text.
	Dimmed = lipgloss.NewStyle().
		Foreground(Muted)
)

// ── Semantic text ──────────────────────────────────────────────────

var (
	SuccessStyle = lipgloss.NewStyle().Foreground(Success)
	WarningStyle = lipgloss.NewStyle().Foreground(Warning)
	ErrorStyle   = lipgloss.NewStyle().Foreground(Error)
	InfoStyle    = lipgloss.NewStyle().Foreground(Info)
)

// ── Status indicators ──────────────────────────────────────────────

// StatusDot returns a colored bullet for running/stopped states.
func StatusDot(active bool) string {
	if active {
		return lipgloss.NewStyle().Foreground(Success).Bold(true).Render("●")
	}
	return lipgloss.NewStyle().Foreground(Error).Render("○")
}

// StatusLabel returns a colored word label.
func StatusLabel(active bool) string {
	if active {
		return lipgloss.NewStyle().Foreground(Success).Bold(true).Render("active")
	}
	return lipgloss.NewStyle().Foreground(Error).Render("inactive")
}

// StatusBadge returns dot + label together.
func StatusBadge(active bool) string {
	return StatusDot(active) + " " + StatusLabel(active)
}

// ── Containers / cards ─────────────────────────────────────────────

var (
	// Card is a rounded bordered panel.
	Card = lipgloss.NewStyle().
		Border(lipgloss.RoundedBorder()).
		BorderForeground(Muted).
		Padding(1, 2)

	// CardActive is a card with an accent border.
	CardActive = lipgloss.NewStyle().
			Border(lipgloss.RoundedBorder()).
			BorderForeground(Secondary).
			Padding(1, 2)

	// CardSuccess highlights a success result.
	CardSuccess = lipgloss.NewStyle().
			Border(lipgloss.RoundedBorder()).
			BorderForeground(Success).
			Padding(1, 2)

	// CardError highlights a failure.
	CardError = lipgloss.NewStyle().
			Border(lipgloss.RoundedBorder()).
			BorderForeground(Error).
			Padding(1, 2)

	// Divider renders a subtle horizontal rule.
	Divider = lipgloss.NewStyle().
		Foreground(Muted).
		MarginTop(1).
		MarginBottom(1)
)

// ── Result markers ─────────────────────────────────────────────────

// SuccessMarker returns "✓ message" in green.
func SuccessMarker(msg string) string {
	check := lipgloss.NewStyle().Foreground(Success).Bold(true).Render("✓")
	return check + " " + lipgloss.NewStyle().Foreground(Text).Render(msg)
}

// ErrorMarker returns "✗ message" in red.
func ErrorMarker(msg string) string {
	cross := lipgloss.NewStyle().Foreground(Error).Bold(true).Render("✗")
	return cross + " " + lipgloss.NewStyle().Foreground(TextDim).Render(msg)
}

// WarnMarker returns "⚠ message" in yellow.
func WarnMarker(msg string) string {
	icon := lipgloss.NewStyle().Foreground(Warning).Bold(true).Render("⚠")
	return icon + " " + lipgloss.NewStyle().Foreground(TextDim).Render(msg)
}

// InfoMarker returns "ℹ message" in blue.
func InfoMarker(msg string) string {
	icon := lipgloss.NewStyle().Foreground(Info).Render("ℹ")
	return icon + " " + lipgloss.NewStyle().Foreground(TextDim).Render(msg)
}

// ── Notifications ───────────────────────────────────────────────────

var (
	// SudoBox renders a bordered notice box for sudo operations.
	sudoBox = lipgloss.NewStyle().
		Border(lipgloss.RoundedBorder()).
		BorderForeground(Warning).
		Padding(0, 2).
		MarginTop(1).
		MarginBottom(1)

	sudoTitle = lipgloss.NewStyle().
			Foreground(Warning).
			Bold(true)

	sudoDetail = lipgloss.NewStyle().
			Foreground(TextDim)
)

// SudoNotice renders a styled box explaining a sudo operation.
// Title is a short action label, details are the specifics (file paths, commands).
func SudoNotice(title string, details ...string) string {
	lines := []string{sudoTitle.Render("🔐 " + title)}
	for _, d := range details {
		lines = append(lines, sudoDetail.Render("   "+d))
	}
	return sudoBox.Render(strings.Join(lines, "\n"))
}

// ── Utilities ──────────────────────────────────────────────────────

// KeyValue renders a single key: value row with aligned label.
func KeyValue(key, value string) string {
	return Label.Render(key) + "  " + Value.Render(value)
}

// KeyValueBright renders a key: value row with bright/bold value.
func KeyValueBright(key, value string) string {
	return Label.Render(key) + "  " + ValueBright.Render(value)
}

// HorizontalRule renders a thin line of a given width.
func HorizontalRule(width int) string {
	return Divider.Render(strings.Repeat("─", width))
}

// Banner renders a prominent top-level header with an icon.
func Banner(icon, title string) string {
	i := lipgloss.NewStyle().Foreground(Primary).Bold(true).Render(icon)
	t := lipgloss.NewStyle().Foreground(Text).Bold(true).Render(title)
	return fmt.Sprintf("%s %s", i, t)
}

// Sparkline renders a mini horizontal bar from 0.0..1.0 using block chars.
func Sparkline(ratio float64, width int, fg lipgloss.Color) string {
	if ratio < 0 {
		ratio = 0
	}
	if ratio > 1 {
		ratio = 1
	}
	filled := int(ratio * float64(width))
	empty := width - filled

	bar := lipgloss.NewStyle().Foreground(fg).Render(strings.Repeat("█", filled))
	rest := lipgloss.NewStyle().Foreground(Muted).Render(strings.Repeat("░", empty))
	return bar + rest
}
