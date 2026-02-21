package components

import (
	"context"
	"fmt"
	"strings"
	"time"

	"github.com/charmbracelet/bubbles/textinput"
	"github.com/charmbracelet/bubbles/viewport"
	tea "github.com/charmbracelet/bubbletea"
	"github.com/charmbracelet/lipgloss"
	"github.com/christhomas/docker-dev-tools/internal/app"
)

const (
	logBufferSize = 500
	markPrefix    = "\x00MARK\x00"
)

// Logs dashboard colour palette — vivid colours for dark terminals.
var (
	logText       = lipgloss.Color("#E0E0FF") // Warm lavender white — easy on the eyes
	logDimText    = lipgloss.Color("#6E78A0") // Muted periwinkle for search non-matches
	tabDNSColor   = lipgloss.Color("#5BC0EB") // Bright sky blue
	tabProxyColor = lipgloss.Color("#9B5DE5") // Electric purple
	tabCGColor    = lipgloss.Color("#00F5D4") // Vivid mint/teal
	tabInactive   = lipgloss.Color("#7A82AB") // Soft lavender grey
	sepColor      = lipgloss.Color("#4A5899") // Deep indigo for separator
	markColor     = lipgloss.Color("#FE6D73") // Bright coral pink
	searchHlBg    = lipgloss.Color("#FFD166") // Sunny golden yellow
	searchHlFg    = lipgloss.Color("#1A1A2E") // Near-black for contrast
	barInfoColor  = lipgloss.Color("#72DDF7") // Bright cyan
	helpColor     = lipgloss.Color("#B8B8D1") // Soft lilac
	followOn      = lipgloss.Color("#06D6A0") // Bright emerald green
	followOff     = lipgloss.Color("#EF476F") // Hot pink
	searchPrompt  = lipgloss.Color("#FCA311") // Vivid orange
	searchActive  = lipgloss.Color("#FFD166") // Sunny yellow (matching highlight)
	waitingColor  = lipgloss.Color("#72DDF7") // Bright cyan
	errorColor    = lipgloss.Color("#EF476F") // Hot pink
)

// LogDashModel is the full-screen Bubbletea model for the logs dashboard.
type LogDashModel struct {
	app    *app.App
	width  int
	height int

	// Tab state.
	activeTab  containerTab
	tabNames   [numTabs]string
	containers [numTabs]string

	// Per-tab log buffers.
	buffers    [numTabs]*RingBuffer
	autoFollow [numTabs]bool

	// Viewport.
	viewport viewport.Model
	ready    bool

	// Search.
	searchInput  textinput.Model
	searchActive bool
	searchTerm   string

	// Stream state.
	ctx          context.Context
	cancel       context.CancelFunc
	streams      [numTabs]<-chan []string
	streamErrors [numTabs]error
}

// NewLogDashModel creates a new logs dashboard model.
func NewLogDashModel(a *app.App) LogDashModel {
	ti := textinput.New()
	ti.Placeholder = "type to search..."
	ti.CharLimit = 100
	ti.Width = 30

	ctx, cancel := context.WithCancel(context.Background())

	m := LogDashModel{
		app:    a,
		ctx:    ctx,
		cancel: cancel,
		tabNames: [numTabs]string{"DNS", "Proxy", "ConfigGen"},
		containers: [numTabs]string{
			a.Config.DNS.ContainerName,
			a.Config.Proxy.ContainerName,
			a.Config.ConfigGen.ContainerName,
		},
		searchInput: ti,
	}

	for i := range m.buffers {
		m.buffers[i] = NewRingBuffer(logBufferSize)
		m.autoFollow[i] = true
	}

	return m
}

func (m LogDashModel) Init() tea.Cmd {
	var cmds []tea.Cmd
	for i := containerTab(0); i < numTabs; i++ {
		if m.streams[i] != nil {
			cmds = append(cmds, waitForLogLines(m.streams[i], i))
		}
	}
	return tea.Batch(cmds...)
}

func (m LogDashModel) Update(msg tea.Msg) (tea.Model, tea.Cmd) {
	var cmds []tea.Cmd

	switch msg := msg.(type) {
	case tea.WindowSizeMsg:
		m.width = msg.Width
		m.height = msg.Height
		vpHeight := m.height - 4 // tab bar + separator + status bar (2 lines)
		if vpHeight < 1 {
			vpHeight = 1
		}
		if !m.ready {
			m.viewport = viewport.New(m.width, vpHeight)
			m.viewport.MouseWheelEnabled = true
			m.ready = true
		} else {
			m.viewport.Width = m.width
			m.viewport.Height = vpHeight
		}
		m.refreshViewport()

	case tea.KeyMsg:
		if m.searchActive {
			return m.handleSearchKey(msg)
		}
		return m.handleNormalKey(msg)

	case logLinesMsg:
		for _, line := range msg.lines {
			m.buffers[msg.tab].Append(line)
		}
		if msg.tab == m.activeTab {
			m.refreshViewport()
		}
		if m.streams[msg.tab] != nil {
			cmds = append(cmds, waitForLogLines(m.streams[msg.tab], msg.tab))
		}

	case logStreamErrMsg:
		m.streamErrors[msg.tab] = msg.err

	case tea.MouseMsg:
		wasAtBottom := m.viewport.AtBottom()
		var cmd tea.Cmd
		m.viewport, cmd = m.viewport.Update(msg)
		cmds = append(cmds, cmd)
		if wasAtBottom && !m.viewport.AtBottom() {
			m.autoFollow[m.activeTab] = false
		}
	}

	return m, tea.Batch(cmds...)
}

func (m LogDashModel) handleSearchKey(msg tea.KeyMsg) (tea.Model, tea.Cmd) {
	switch msg.String() {
	case "esc":
		m.searchActive = false
		m.searchInput.Blur()
		return m, nil
	case "enter":
		m.searchActive = false
		m.searchInput.Blur()
		m.searchTerm = m.searchInput.Value()
		m.refreshViewport()
		return m, nil
	case "ctrl+c":
		m.cancel()
		return m, tea.Quit
	default:
		var cmd tea.Cmd
		m.searchInput, cmd = m.searchInput.Update(msg)
		m.searchTerm = m.searchInput.Value()
		m.refreshViewport()
		return m, cmd
	}
}

func (m LogDashModel) handleNormalKey(msg tea.KeyMsg) (tea.Model, tea.Cmd) {
	switch msg.String() {
	case "q", "esc":
		m.cancel()
		return m, tea.Quit
	case "ctrl+c":
		m.cancel()
		return m, tea.Quit
	case "1":
		m.switchTab(tabDNS)
	case "2":
		m.switchTab(tabProxy)
	case "3":
		m.switchTab(tabConfigGen)
	case "tab":
		m.switchTab((m.activeTab + 1) % numTabs)
	case "shift+tab":
		m.switchTab((m.activeTab - 1 + numTabs) % numTabs)
	case "m":
		m.insertMark()
	case "/":
		m.searchActive = true
		return m, m.searchInput.Focus()
	case "G", "end":
		m.viewport.GotoBottom()
		m.autoFollow[m.activeTab] = true
	case "g", "home":
		m.viewport.GotoTop()
		m.autoFollow[m.activeTab] = false
	default:
		wasAtBottom := m.viewport.AtBottom()
		var cmd tea.Cmd
		m.viewport, cmd = m.viewport.Update(msg)
		if wasAtBottom && !m.viewport.AtBottom() {
			m.autoFollow[m.activeTab] = false
		}
		return m, cmd
	}
	return m, nil
}

func (m LogDashModel) View() string {
	if !m.ready {
		return "Initializing..."
	}

	var b strings.Builder
	b.WriteString(m.renderTabBar())
	b.WriteString("\n")
	b.WriteString(lipgloss.NewStyle().Foreground(sepColor).Render(strings.Repeat("─", m.width)))
	b.WriteString("\n")
	b.WriteString(m.viewport.View())
	b.WriteString("\n")
	b.WriteString(m.renderStatusBar())
	return b.String()
}

func (m *LogDashModel) switchTab(tab containerTab) {
	m.activeTab = tab
	m.refreshViewport()
}

func (m *LogDashModel) insertMark() {
	m.buffers[m.activeTab].Append(markPrefix + time.Now().Format("15:04:05"))
	m.refreshViewport()
}

func (m *LogDashModel) refreshViewport() {
	content := m.renderViewportContent()
	m.viewport.SetContent(content)
	if m.autoFollow[m.activeTab] {
		m.viewport.GotoBottom()
	}
}

func (m LogDashModel) renderViewportContent() string {
	lines := m.buffers[m.activeTab].Lines()
	if len(lines) == 0 {
		if m.streamErrors[m.activeTab] != nil {
			return lipgloss.NewStyle().Foreground(errorColor).
				Render(fmt.Sprintf("  Failed to connect: %s", m.streamErrors[m.activeTab]))
		}
		return lipgloss.NewStyle().Foreground(waitingColor).Render("  Waiting for logs...")
	}

	searchLower := strings.ToLower(m.searchTerm)
	highlightStyle := lipgloss.NewStyle().
		Background(searchHlBg).
		Foreground(searchHlFg).
		Bold(true)
	dimarkStyle := lipgloss.NewStyle().Foreground(logDimText)
	normalStyle := lipgloss.NewStyle().Foreground(logText)
	markStyle := lipgloss.NewStyle().Foreground(markColor).Bold(true)

	rendered := make([]string, 0, len(lines))
	for _, line := range lines {
		// Handle MARK lines.
		if strings.HasPrefix(line, markPrefix) {
			ts := strings.TrimPrefix(line, markPrefix)
			pad := m.width - 16 - len(ts) // "─── MARK " + ts + " ───" overhead
			if pad < 3 {
				pad = 3
			}
			rendered = append(rendered, markStyle.Render(
				fmt.Sprintf("─── MARK %s %s", ts, strings.Repeat("─", pad))))
			continue
		}

		// No search active: render normally.
		if m.searchTerm == "" {
			rendered = append(rendered, normalStyle.Render(line))
			continue
		}

		// Search highlighting.
		lineLower := strings.ToLower(line)
		if !strings.Contains(lineLower, searchLower) {
			// No match: dim the line.
			rendered = append(rendered, dimarkStyle.Render(line))
			continue
		}

		// Highlight all occurrences.
		var result strings.Builder
		pos := 0
		for pos < len(line) {
			idx := strings.Index(lineLower[pos:], searchLower)
			if idx < 0 {
				result.WriteString(normalStyle.Render(line[pos:]))
				break
			}
			if idx > 0 {
				result.WriteString(normalStyle.Render(line[pos : pos+idx]))
			}
			matchEnd := pos + idx + len(m.searchTerm)
			result.WriteString(highlightStyle.Render(line[pos+idx : matchEnd]))
			pos = matchEnd
		}
		rendered = append(rendered, result.String())
	}

	return strings.Join(rendered, "\n")
}

func (m LogDashModel) renderTabBar() string {
	// Each tab gets its own signature colour.
	tabColors := [numTabs]lipgloss.Color{tabDNSColor, tabProxyColor, tabCGColor}

	var tabs []string
	for i := containerTab(0); i < numTabs; i++ {
		label := fmt.Sprintf(" %d:%s ", i+1, m.tabNames[i])
		if i == m.activeTab {
			tabs = append(tabs, lipgloss.NewStyle().
				Background(tabColors[i]).
				Foreground(lipgloss.Color("#1A1A2E")).
				Bold(true).
				Render(label))
		} else {
			tabs = append(tabs, lipgloss.NewStyle().
				Foreground(tabInactive).
				Render(label))
		}
	}

	left := strings.Join(tabs, " ")

	scrollPct := lipgloss.NewStyle().Foreground(barInfoColor).
		Render(fmt.Sprintf("%3.0f%%", m.viewport.ScrollPercent()*100))
	followLabel := lipgloss.NewStyle().Foreground(barInfoColor).Render("follow: ")
	var followVal string
	if m.autoFollow[m.activeTab] {
		followVal = lipgloss.NewStyle().Foreground(followOn).Bold(true).Render("ON")
	} else {
		followVal = lipgloss.NewStyle().Foreground(followOff).Bold(true).Render("OFF")
	}
	divider := lipgloss.NewStyle().Foreground(sepColor).Render(" | ")
	right := scrollPct + divider + followLabel + followVal

	gap := m.width - lipgloss.Width(left) - lipgloss.Width(right)
	if gap < 1 {
		gap = 1
	}

	return left + strings.Repeat(" ", gap) + right
}

func (m LogDashModel) renderStatusBar() string {
	var left string
	if m.searchActive {
		left = lipgloss.NewStyle().Foreground(searchActive).Render("/ ") + m.searchInput.View()
	} else if m.searchTerm != "" {
		left = lipgloss.NewStyle().Foreground(searchActive).
			Render(fmt.Sprintf("/ %s", m.searchTerm))
	} else {
		left = lipgloss.NewStyle().Foreground(searchPrompt).Render("/ search")
	}

	help := lipgloss.NewStyle().Foreground(helpColor).
		Render("m:mark  /:search  1/2/3:tabs  q:quit")

	gap := m.width - lipgloss.Width(left) - lipgloss.Width(help)
	if gap < 1 {
		gap = 1
	}

	return left + strings.Repeat(" ", gap) + help
}

// RunLogsDashboard launches the interactive logs dashboard TUI.
func RunLogsDashboard(a *app.App) error {
	m := NewLogDashModel(a)

	// Start log streams for all containers before launching the TUI.
	for i := containerTab(0); i < numTabs; i++ {
		ch, err := startLogStream(a.Docker, m.containers[i], m.ctx)
		if err != nil {
			m.streamErrors[i] = err
			continue
		}
		m.streams[i] = ch
	}

	p := tea.NewProgram(m, tea.WithAltScreen(), tea.WithMouseCellMotion())
	_, err := p.Run()
	m.cancel()
	return err
}
