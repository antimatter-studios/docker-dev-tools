package components

import (
	"fmt"
	"math"
	"net"
	"strings"
	"time"

	tea "github.com/charmbracelet/bubbletea"
	"github.com/charmbracelet/lipgloss"
	"github.com/christhomas/docker-dev-tools/internal/tui/styles"
)

const (
	pingHistoryLen = 30
	graphWidth     = 30
	graphHeight    = 6
	defaultPings   = 10
)

// Unicode braille-style bar characters for the latency graph.
var barChars = []string{"▁", "▂", "▃", "▄", "▅", "▆", "▇", "█"}

// PingResult holds a single ping measurement.
type PingResult struct {
	Seq     int
	Latency time.Duration
	Err     error
	Time    time.Time
}

type pingTickMsg struct{}
type pingResultMsg struct{ result PingResult }

// PingModel is a Bubbletea model that runs continuous pings
// with a live-updating latency graph.
type PingModel struct {
	target   string
	count    int
	history  []PingResult
	seq      int
	done     bool
	quitting bool

	// Stats.
	sent     int
	received int
	minMs    float64
	maxMs    float64
	sumMs    float64
}

// NewPing creates a ping model for the given IP, running count pings.
// If count <= 0, defaults to defaultPings.
func NewPing(target string, count int) PingModel {
	if count <= 0 {
		count = defaultPings
	}
	return PingModel{
		target:  target,
		count:   count,
		history: make([]PingResult, 0, count),
		minMs:   math.MaxFloat64,
	}
}

func (m PingModel) Init() tea.Cmd {
	return doPing(m.target, 0)
}

func (m PingModel) Update(msg tea.Msg) (tea.Model, tea.Cmd) {
	switch msg := msg.(type) {
	case tea.KeyMsg:
		switch msg.String() {
		case "q", "ctrl+c", "esc":
			m.quitting = true
			m.done = true
			return m, tea.Quit
		}

	case pingResultMsg:
		r := msg.result
		m.history = append(m.history, r)
		m.sent++

		if r.Err == nil {
			m.received++
			ms := float64(r.Latency.Microseconds()) / 1000.0
			if ms < m.minMs {
				m.minMs = ms
			}
			if ms > m.maxMs {
				m.maxMs = ms
			}
			m.sumMs += ms
		}

		m.seq++
		if m.seq >= m.count {
			m.done = true
			return m, tea.Quit
		}

		// Schedule next ping after a short delay.
		return m, tea.Tick(300*time.Millisecond, func(t time.Time) tea.Msg {
			return pingTickMsg{}
		})

	case pingTickMsg:
		return m, doPing(m.target, m.seq)
	}

	return m, nil
}

func (m PingModel) View() string {
	var b strings.Builder

	// Header.
	header := styles.Banner("⚡", fmt.Sprintf("Ping %s", m.target))
	b.WriteString(header)
	b.WriteString("\n\n")

	// Live graph.
	b.WriteString(m.renderGraph())
	b.WriteString("\n")

	// Recent results.
	b.WriteString(m.renderRecent())
	b.WriteString("\n")

	// Stats line.
	b.WriteString(m.renderStats())

	if !m.done {
		b.WriteString("\n\n")
		b.WriteString(lipgloss.NewStyle().Foreground(styles.Muted).Render("press q to stop"))
	}

	return styles.Card.Render(b.String()) + "\n"
}

func (m PingModel) renderGraph() string {
	if len(m.history) == 0 {
		return lipgloss.NewStyle().Foreground(styles.Muted).Render("waiting for first result...")
	}

	// Collect the last N latency values.
	start := 0
	if len(m.history) > graphWidth {
		start = len(m.history) - graphWidth
	}
	window := m.history[start:]

	// Find range for scaling.
	var minMs, maxMs float64
	minMs = math.MaxFloat64
	for _, r := range window {
		if r.Err != nil {
			continue
		}
		ms := float64(r.Latency.Microseconds()) / 1000.0
		if ms < minMs {
			minMs = ms
		}
		if ms > maxMs {
			maxMs = ms
		}
	}
	if maxMs == minMs {
		maxMs = minMs + 1
	}

	// Build bar characters.
	var bars strings.Builder
	for _, r := range window {
		if r.Err != nil {
			bars.WriteString(lipgloss.NewStyle().Foreground(styles.Error).Render("╳"))
			continue
		}
		ms := float64(r.Latency.Microseconds()) / 1000.0
		ratio := (ms - minMs) / (maxMs - minMs)
		idx := int(ratio * float64(len(barChars)-1))
		if idx < 0 {
			idx = 0
		}
		if idx >= len(barChars) {
			idx = len(barChars) - 1
		}

		// Color based on latency: green < 5ms, yellow < 50ms, red >= 50ms.
		color := styles.Success
		if ms > 50 {
			color = styles.Error
		} else if ms > 5 {
			color = styles.Warning
		}
		bars.WriteString(lipgloss.NewStyle().Foreground(color).Render(barChars[idx]))
	}

	// Y-axis labels.
	maxLabel := lipgloss.NewStyle().Foreground(styles.Subtle).Width(8).Align(lipgloss.Right).
		Render(fmt.Sprintf("%.1fms", maxMs))
	minLabel := lipgloss.NewStyle().Foreground(styles.Subtle).Width(8).Align(lipgloss.Right).
		Render(fmt.Sprintf("%.1fms", minMs))

	graphLine := bars.String()
	return fmt.Sprintf("%s %s %s\n%s %s",
		maxLabel,
		lipgloss.NewStyle().Foreground(styles.Muted).Render("┤"),
		graphLine,
		minLabel,
		lipgloss.NewStyle().Foreground(styles.Muted).Render("┤"),
	)
}

func (m PingModel) renderRecent() string {
	if len(m.history) == 0 {
		return ""
	}

	// Show last 3 results.
	start := 0
	if len(m.history) > 3 {
		start = len(m.history) - 3
	}

	var lines []string
	for _, r := range m.history[start:] {
		if r.Err != nil {
			lines = append(lines, styles.ErrorMarker(
				fmt.Sprintf("seq=%d  timeout", r.Seq)))
		} else {
			ms := float64(r.Latency.Microseconds()) / 1000.0
			color := styles.Success
			if ms > 50 {
				color = styles.Error
			} else if ms > 5 {
				color = styles.Warning
			}
			latStr := lipgloss.NewStyle().Foreground(color).Bold(true).
				Render(fmt.Sprintf("%.2fms", ms))
			seqStr := lipgloss.NewStyle().Foreground(styles.Subtle).
				Render(fmt.Sprintf("seq=%-3d", r.Seq))
			lines = append(lines, fmt.Sprintf("  %s  %s  %s",
				lipgloss.NewStyle().Foreground(styles.Success).Render("→"),
				seqStr,
				latStr,
			))
		}
	}

	return strings.Join(lines, "\n")
}

func (m PingModel) renderStats() string {
	if m.sent == 0 {
		return ""
	}

	lossRate := float64(m.sent-m.received) / float64(m.sent) * 100
	avgMs := 0.0
	if m.received > 0 {
		avgMs = m.sumMs / float64(m.received)
	}

	// Progress bar.
	progress := float64(m.sent) / float64(m.count)
	progressBar := styles.Sparkline(progress, 20, styles.Secondary)

	statsLine := fmt.Sprintf("%s %s/%s",
		progressBar,
		lipgloss.NewStyle().Foreground(styles.TextDim).Render(fmt.Sprintf("%d", m.sent)),
		lipgloss.NewStyle().Foreground(styles.Subtle).Render(fmt.Sprintf("%d", m.count)),
	)

	var summary string
	if m.received > 0 {
		lossColor := styles.Success
		if lossRate > 50 {
			lossColor = styles.Error
		} else if lossRate > 0 {
			lossColor = styles.Warning
		}

		summary = fmt.Sprintf("  %s  %s  %s",
			styles.KeyValue("min", fmt.Sprintf("%.2fms", m.minMs)),
			styles.KeyValue("avg", fmt.Sprintf("%.2fms", avgMs)),
			fmt.Sprintf("%s %s",
				styles.Label.Render("loss"),
				lipgloss.NewStyle().Foreground(lossColor).Render(fmt.Sprintf("%.0f%%", lossRate)),
			),
		)
	}

	return statsLine + "\n" + summary
}

// doPing performs a single ping and returns the result as a Cmd.
func doPing(target string, seq int) tea.Cmd {
	return func() tea.Msg {
		start := time.Now()

		// Try TCP connect to port 80 first (works without root).
		conn, err := net.DialTimeout("tcp", target+":80", 2*time.Second)
		if err != nil {
			// Fall back to UDP.
			conn, err = net.DialTimeout("udp", target+":1", 2*time.Second)
			if err != nil {
				return pingResultMsg{result: PingResult{
					Seq:  seq,
					Err:  err,
					Time: time.Now(),
				}}
			}
		}
		_ = conn.Close()

		return pingResultMsg{result: PingResult{
			Seq:     seq,
			Latency: time.Since(start),
			Time:    time.Now(),
		}}
	}
}

// RunPing executes the ping TUI and returns the results.
func RunPing(target string, count int) ([]PingResult, error) {
	m := NewPing(target, count)
	p := tea.NewProgram(m)
	finalModel, err := p.Run()
	if err != nil {
		return nil, err
	}
	pm := finalModel.(PingModel)
	return pm.history, nil
}
