package components

import (
	"fmt"
	"os"
	"strings"
	"time"

	"github.com/charmbracelet/lipgloss"
	"github.com/christhomas/docker-dev-tools/internal/tui/styles"
)

// Steps provides step-by-step progress output for multi-step operations.
// It uses lipgloss styling but NOT bubbletea, so it doesn't take over the
// terminal — sudo prompts and other interactive I/O still work.
type Steps struct {
	title   string
	started time.Time
	steps   int
	errors  int
}

// NewSteps creates a new step printer with a banner title.
func NewSteps(icon, title string) *Steps {
	header := lipgloss.NewStyle().
		Bold(true).
		Foreground(styles.Secondary).
		Render(fmt.Sprintf("%s %s", icon, title))

	fmt.Fprintln(os.Stderr, "\n"+header)
	fmt.Fprintln(os.Stderr, lipgloss.NewStyle().
		Foreground(styles.Muted).
		Render(strings.Repeat("─", lipgloss.Width(header))))

	return &Steps{title: title, started: time.Now()}
}

// Run executes a step function and prints success/failure.
func (s *Steps) Run(label string, fn func() error) error {
	s.steps++
	start := time.Now()
	err := fn()
	elapsed := time.Since(start).Round(time.Millisecond)

	if err != nil {
		s.errors++
		msg := fmt.Sprintf("%s %s",
			label,
			lipgloss.NewStyle().Foreground(styles.Muted).Render(fmt.Sprintf("(%s)", elapsed)),
		)
		fmt.Fprintln(os.Stderr, "  "+styles.ErrorMarker(msg))

		detail := lipgloss.NewStyle().
			Foreground(styles.Error).
			PaddingLeft(4).
			Render(err.Error())
		fmt.Fprintln(os.Stderr, detail)
		return err
	}

	msg := fmt.Sprintf("%s %s",
		label,
		lipgloss.NewStyle().Foreground(styles.Muted).Render(fmt.Sprintf("(%s)", elapsed)),
	)
	fmt.Fprintln(os.Stderr, "  "+styles.SuccessMarker(msg))
	return nil
}

// Skip prints an info marker for a step that was skipped.
func (s *Steps) Skip(label, reason string) {
	msg := fmt.Sprintf("%s %s",
		label,
		lipgloss.NewStyle().Foreground(styles.Muted).Render(fmt.Sprintf("— %s", reason)),
	)
	fmt.Fprintln(os.Stderr, "  "+styles.InfoMarker(msg))
}

// Info prints an informational note.
func (s *Steps) Info(msg string) {
	fmt.Fprintln(os.Stderr, "  "+styles.InfoMarker(msg))
}

// Warn prints a warning note.
func (s *Steps) Warn(msg string) {
	fmt.Fprintln(os.Stderr, "  "+styles.WarnMarker(msg))
}

// Done prints a completion summary.
func (s *Steps) Done() {
	elapsed := time.Since(s.started).Round(time.Millisecond)
	if s.errors > 0 {
		fmt.Fprintln(os.Stderr, "\n"+styles.ErrorMarker(
			fmt.Sprintf("Failed after %s (%d/%d steps completed)", elapsed, s.steps-s.errors, s.steps),
		))
	} else {
		fmt.Fprintln(os.Stderr, "\n"+styles.SuccessMarker(
			fmt.Sprintf("Done in %s (%d steps)", elapsed, s.steps),
		))
	}
}
