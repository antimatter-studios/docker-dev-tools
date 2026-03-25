package styles

import (
	"fmt"
	"strings"

	"github.com/charmbracelet/lipgloss"
)

// theme holds a complete set of colors for one background type.
type theme struct {
	Primary, Secondary, Accent, Highlight lipgloss.Color
	Success, Warning, Error, Info         lipgloss.Color
	Text, TextDim, Subtle, Muted          lipgloss.Color
	Surface, Base                          lipgloss.Color
}

// Dark theme: bright colors for dark terminals. No channel value below 0x55.
var darkTheme = theme{
	Primary:   lipgloss.Color("#CF79F2"),
	Secondary: lipgloss.Color("#56D6FA"),
	Accent:    lipgloss.Color("#6CB6FF"),
	Highlight: lipgloss.Color("#FF9E64"),
	Success:   lipgloss.Color("#5AF78E"),
	Warning:   lipgloss.Color("#F3F99D"),
	Error:     lipgloss.Color("#FF6E6E"),
	Info:      lipgloss.Color("#78DCE8"),
	Text:    lipgloss.Color("#FFFFFF"),
	TextDim: lipgloss.Color("#FFFFFF"),
	Subtle:  lipgloss.Color("#FFFFFF"),
	Muted:   lipgloss.Color("#FFFFFF"),
	Surface: lipgloss.Color("#555566"),
	Base:    lipgloss.Color("#555555"),
}

// Light theme: dark text on light backgrounds.
var lightTheme = theme{
	Primary:   lipgloss.Color("#7C3AED"),
	Secondary: lipgloss.Color("#0891B2"),
	Accent:    lipgloss.Color("#2563EB"),
	Highlight: lipgloss.Color("#B84500"),
	Success:   lipgloss.Color("#16A34A"),
	Warning:   lipgloss.Color("#936300"),
	Error:     lipgloss.Color("#CC2222"),
	Info:      lipgloss.Color("#0369A1"),
	Text:      lipgloss.Color("#111111"),
	TextDim:   lipgloss.Color("#222222"),
	Subtle:    lipgloss.Color("#444444"),
	Muted:     lipgloss.Color("#555555"),
	Surface:   lipgloss.Color("#E8E8E8"),
	Base:      lipgloss.Color("#F5F5F5"),
}

// Active palette — set once at init, referenced everywhere.
var (
	Primary   lipgloss.Color
	Secondary lipgloss.Color
	Accent    lipgloss.Color
	Highlight lipgloss.Color

	Success lipgloss.Color
	Warning lipgloss.Color
	Error   lipgloss.Color
	Info    lipgloss.Color

	Text    lipgloss.Color
	TextDim lipgloss.Color
	Subtle  lipgloss.Color
	Muted   lipgloss.Color
	Surface lipgloss.Color
	Base    lipgloss.Color
)

func applyTheme(t theme) {
	Primary = t.Primary
	Secondary = t.Secondary
	Accent = t.Accent
	Highlight = t.Highlight
	Success = t.Success
	Warning = t.Warning
	Error = t.Error
	Info = t.Info
	Text = t.Text
	TextDim = t.TextDim
	Subtle = t.Subtle
	Muted = t.Muted
	Surface = t.Surface
	Base = t.Base
}

func init() {
	if lipgloss.HasDarkBackground() {
		applyTheme(darkTheme)
	} else {
		applyTheme(lightTheme)
	}
	initStyles()
}

// ── Typography ─────────────────────────────────────────────────────

var (
	AppTitle     lipgloss.Style
	SectionTitle lipgloss.Style
	Heading      lipgloss.Style
	Label        lipgloss.Style
	Value        lipgloss.Style
	ValueBright  lipgloss.Style
	Dimmed       lipgloss.Style
)

// ── Semantic text ──────────────────────────────────────────────────

var (
	SuccessStyle lipgloss.Style
	WarningStyle lipgloss.Style
	ErrorStyle   lipgloss.Style
	InfoStyle    lipgloss.Style
)

// ── Containers / cards ─────────────────────────────────────────────

var (
	Card        lipgloss.Style
	CardActive  lipgloss.Style
	CardSuccess lipgloss.Style
	CardError   lipgloss.Style
	Divider     lipgloss.Style
)

// ── Notifications ───────────────────────────────────────────────────

var (
	sudoBox    lipgloss.Style
	sudoTitle  lipgloss.Style
	sudoDetail lipgloss.Style
)

// initStyles builds all styles from the active palette.
// Called once from init() after the theme is selected.
func initStyles() {
	AppTitle = lipgloss.NewStyle().Bold(true).Foreground(Primary).MarginBottom(1)
	SectionTitle = lipgloss.NewStyle().Bold(true).Foreground(Secondary).MarginBottom(1)
	Heading = lipgloss.NewStyle().Bold(true).Foreground(Text).PaddingBottom(0)
	Label = lipgloss.NewStyle().Foreground(Subtle).Width(14).Align(lipgloss.Right)
	Value = lipgloss.NewStyle().Foreground(TextDim)
	ValueBright = lipgloss.NewStyle().Foreground(Text).Bold(true)
	Dimmed = lipgloss.NewStyle().Foreground(Muted)

	SuccessStyle = lipgloss.NewStyle().Foreground(Success)
	WarningStyle = lipgloss.NewStyle().Foreground(Warning)
	ErrorStyle = lipgloss.NewStyle().Foreground(Error)
	InfoStyle = lipgloss.NewStyle().Foreground(Info)

	Card = lipgloss.NewStyle().Border(lipgloss.RoundedBorder()).BorderForeground(Muted).Padding(1, 2)
	CardActive = lipgloss.NewStyle().Border(lipgloss.RoundedBorder()).BorderForeground(Secondary).Padding(1, 2)
	CardSuccess = lipgloss.NewStyle().Border(lipgloss.RoundedBorder()).BorderForeground(Success).Padding(1, 2)
	CardError = lipgloss.NewStyle().Border(lipgloss.RoundedBorder()).BorderForeground(Error).Padding(1, 2)
	Divider = lipgloss.NewStyle().Foreground(Muted).MarginTop(1).MarginBottom(1)

	sudoBox = lipgloss.NewStyle().Border(lipgloss.RoundedBorder()).BorderForeground(Warning).Padding(0, 2).MarginTop(1).MarginBottom(1)
	sudoTitle = lipgloss.NewStyle().Foreground(Warning).Bold(true)
	sudoDetail = lipgloss.NewStyle().Foreground(TextDim)
}

// ── Status indicators ──────────────────────────────────────────────

func StatusDot(active bool) string {
	if active {
		return lipgloss.NewStyle().Foreground(Success).Bold(true).Render("●")
	}
	return lipgloss.NewStyle().Foreground(Error).Render("○")
}

func StatusLabel(active bool) string {
	if active {
		return lipgloss.NewStyle().Foreground(Success).Bold(true).Render("active")
	}
	return lipgloss.NewStyle().Foreground(Error).Render("inactive")
}

func StatusBadge(active bool) string {
	return StatusDot(active) + " " + StatusLabel(active)
}

// ── Result markers ─────────────────────────────────────────────────

func SuccessMarker(msg string) string {
	check := lipgloss.NewStyle().Foreground(Success).Bold(true).Render("✓")
	return check + " " + lipgloss.NewStyle().Foreground(Text).Render(msg)
}

func ErrorMarker(msg string) string {
	cross := lipgloss.NewStyle().Foreground(Error).Bold(true).Render("✗")
	return cross + " " + lipgloss.NewStyle().Foreground(TextDim).Render(msg)
}

func WarnMarker(msg string) string {
	icon := lipgloss.NewStyle().Foreground(Warning).Bold(true).Render("⚠")
	return icon + " " + lipgloss.NewStyle().Foreground(TextDim).Render(msg)
}

func InfoMarker(msg string) string {
	icon := lipgloss.NewStyle().Foreground(Info).Render("ℹ")
	return icon + " " + lipgloss.NewStyle().Foreground(TextDim).Render(msg)
}

// ── Notifications ───────────────────────────────────────────────────

func SudoNotice(title string, details ...string) string {
	lines := []string{sudoTitle.Render("🔐 " + title)}
	for _, d := range details {
		lines = append(lines, sudoDetail.Render("   "+d))
	}
	return sudoBox.Render(strings.Join(lines, "\n"))
}

// ── Utilities ──────────────────────────────────────────────────────

func KeyValue(key, value string) string {
	return Label.Render(key) + "  " + Value.Render(value)
}

func KeyValueBright(key, value string) string {
	return Label.Render(key) + "  " + ValueBright.Render(value)
}

func HorizontalRule(width int) string {
	return Divider.Render(strings.Repeat("─", width))
}

func Banner(icon, title string) string {
	i := lipgloss.NewStyle().Foreground(Primary).Bold(true).Render(icon)
	t := lipgloss.NewStyle().Foreground(Text).Bold(true).Render(title)
	return fmt.Sprintf("%s %s", i, t)
}

func Hyperlink(url, text string) string {
	return fmt.Sprintf("\x1b]8;;%s\x1b\\%s\x1b]8;;\x1b\\", url, text)
}

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
