package components

import (
	"fmt"
	"strings"
	"time"

	"github.com/charmbracelet/bubbles/spinner"
	tea "github.com/charmbracelet/bubbletea"
	"github.com/charmbracelet/lipgloss"
	"github.com/christhomas/docker-dev-tools/internal/tui/styles"
)

// ── Operation model ────────────────────────────────────────────────
// Runs an async function with a spinner, then renders success/failure.

// OperationFunc is the function executed by the operation model.
type OperationFunc func() error

type operationDoneMsg struct{ err error }

// OperationModel runs a function with a visual spinner.
type OperationModel struct {
	spinner spinner.Model
	title   string
	detail  string
	fn      OperationFunc
	started time.Time
	done    bool
	err     error
	elapsed time.Duration
}

// NewOperation creates an operation model.
func NewOperation(title, detail string, fn OperationFunc) OperationModel {
	s := spinner.New()
	s.Spinner = spinner.MiniDot
	s.Style = lipgloss.NewStyle().Foreground(styles.Secondary)
	return OperationModel{
		spinner: s,
		title:   title,
		detail:  detail,
		fn:      fn,
		started: time.Now(),
	}
}

func (m OperationModel) Init() tea.Cmd {
	return tea.Batch(
		m.spinner.Tick,
		func() tea.Msg {
			err := m.fn()
			return operationDoneMsg{err: err}
		},
	)
}

func (m OperationModel) Update(msg tea.Msg) (tea.Model, tea.Cmd) {
	switch msg := msg.(type) {
	case tea.KeyMsg:
		if msg.String() == "ctrl+c" {
			return m, tea.Quit
		}
	case operationDoneMsg:
		m.done = true
		m.err = msg.err
		m.elapsed = time.Since(m.started)
		return m, tea.Quit
	case spinner.TickMsg:
		var cmd tea.Cmd
		m.spinner, cmd = m.spinner.Update(msg)
		return m, cmd
	}
	return m, nil
}

func (m OperationModel) View() string {
	var b strings.Builder

	if m.done {
		if m.err != nil {
			b.WriteString(styles.ErrorMarker(m.title))
			b.WriteString("\n")
			errDetail := lipgloss.NewStyle().
				Foreground(styles.Error).
				PaddingLeft(2).
				Render(m.err.Error())
			b.WriteString(errDetail)
		} else {
			b.WriteString(styles.SuccessMarker(m.title))
			if m.detail != "" {
				b.WriteString("\n")
				b.WriteString(lipgloss.NewStyle().PaddingLeft(2).Foreground(styles.Subtle).Render(m.detail))
			}
			b.WriteString("\n")
			b.WriteString(lipgloss.NewStyle().PaddingLeft(2).Foreground(styles.Muted).
				Render(fmt.Sprintf("completed in %s", m.elapsed.Round(time.Millisecond))))
		}
	} else {
		fmt.Fprintf(&b, "%s %s",
			m.spinner.View(),
			lipgloss.NewStyle().Foreground(styles.TextDim).Render(m.title),
		)
		if m.detail != "" {
			b.WriteString("\n")
			b.WriteString(lipgloss.NewStyle().PaddingLeft(2).Foreground(styles.Subtle).Render(m.detail))
		}
	}

	return b.String() + "\n"
}

// RunOperation runs an operation with animated spinner TUI.
func RunOperation(title, detail string, fn OperationFunc) error {
	m := NewOperation(title, detail, fn)
	p := tea.NewProgram(m)
	finalModel, err := p.Run()
	if err != nil {
		return err
	}
	if om, ok := finalModel.(OperationModel); ok && om.err != nil {
		return om.err
	}
	return nil
}
