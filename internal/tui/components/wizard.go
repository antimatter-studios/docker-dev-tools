package components

import (
	"strings"

	"github.com/charmbracelet/bubbles/textinput"
	tea "github.com/charmbracelet/bubbletea"
	"github.com/charmbracelet/lipgloss"

	"github.com/christhomas/docker-dev-tools/internal/tui/styles"
)

// WizardResult holds the values collected by the install wizard.
type WizardResult struct {
	IPAddress string
	TLDs      []string
	Cancelled bool
}

type wizardStep int

const (
	stepWelcome wizardStep = iota
	stepIP
	stepTLDs
	stepConfirm
)

// wizardStyles builds styles at call time so colors from styles.init() are guaranteed to be set.
type wizardStyles struct {
	title, heading, body, bullet, prompt, hint, warn lipgloss.Style
	tag, label, value, dim, content                  lipgloss.Style
}

func getWizardStyles() wizardStyles {
	return wizardStyles{
		title:   lipgloss.NewStyle().Bold(true).Foreground(styles.Primary).MarginBottom(1),
		heading: lipgloss.NewStyle().Bold(true).Foreground(styles.Secondary),
		body:    lipgloss.NewStyle().Foreground(styles.TextDim),
		bullet:  lipgloss.NewStyle().Foreground(styles.TextDim),
		prompt:  lipgloss.NewStyle().Bold(true).Foreground(styles.Secondary),
		hint:    lipgloss.NewStyle().Foreground(styles.Muted),
		warn:    lipgloss.NewStyle().Foreground(styles.Warning),
		tag:     lipgloss.NewStyle().Foreground(styles.Text).Background(styles.Surface).Padding(0, 1),
		label:   lipgloss.NewStyle().Foreground(styles.Subtle).Width(14).Align(lipgloss.Right),
		value:   lipgloss.NewStyle().Bold(true).Foreground(styles.Text),
		dim:     lipgloss.NewStyle().Foreground(styles.TextDim),
		content: lipgloss.NewStyle().PaddingLeft(2),
	}
}

type wizardModel struct {
	step     wizardStep
	ipInput  textinput.Model
	tldInput textinput.Model
	tlds     []string
	result   WizardResult
	width    int
}

func newWizardModel(defaultIP string, defaultTLDs []string) wizardModel {
	ip := textinput.New()
	ip.Placeholder = "10.254.254.254"
	ip.SetValue(defaultIP)
	ip.CharLimit = 15
	ip.Width = 20

	tld := textinput.New()
	tld.Placeholder = "e.g. develop, test"
	tld.CharLimit = 50
	tld.Width = 30

	tlds := make([]string, len(defaultTLDs))
	copy(tlds, defaultTLDs)

	return wizardModel{
		step:     stepWelcome,
		ipInput:  ip,
		tldInput: tld,
		tlds:     tlds,
		width:    60,
	}
}

func (m wizardModel) Init() tea.Cmd {
	return nil
}

func (m wizardModel) Update(msg tea.Msg) (tea.Model, tea.Cmd) {
	switch msg := msg.(type) {
	case tea.WindowSizeMsg:
		m.width = msg.Width
		if m.width > 80 {
			m.width = 80
		}
		return m, nil

	case tea.KeyMsg:
		switch msg.String() {
		case "ctrl+c", "esc":
			m.result.Cancelled = true
			return m, tea.Quit
		}
	}

	switch m.step {
	case stepWelcome:
		return m.updateWelcome(msg)
	case stepIP:
		return m.updateIP(msg)
	case stepTLDs:
		return m.updateTLDs(msg)
	case stepConfirm:
		return m.updateConfirm(msg)
	}

	return m, nil
}

func (m wizardModel) updateWelcome(msg tea.Msg) (tea.Model, tea.Cmd) {
	if key, ok := msg.(tea.KeyMsg); ok {
		if key.String() == "enter" {
			m.step = stepIP
			m.ipInput.Focus()
			return m, m.ipInput.Cursor.BlinkCmd()
		}
	}
	return m, nil
}

func (m wizardModel) updateIP(msg tea.Msg) (tea.Model, tea.Cmd) {
	if key, ok := msg.(tea.KeyMsg); ok {
		if key.String() == "enter" {
			val := strings.TrimSpace(m.ipInput.Value())
			if val == "" {
				val = "10.254.254.254"
			}
			m.result.IPAddress = val
			m.ipInput.Blur()
			m.step = stepTLDs
			m.tldInput.Focus()
			return m, m.tldInput.Cursor.BlinkCmd()
		}
	}
	var cmd tea.Cmd
	m.ipInput, cmd = m.ipInput.Update(msg)
	return m, cmd
}

func (m wizardModel) updateTLDs(msg tea.Msg) (tea.Model, tea.Cmd) {
	if key, ok := msg.(tea.KeyMsg); ok {
		switch key.String() {
		case "enter":
			val := strings.TrimSpace(m.tldInput.Value())
			if val != "" {
				for _, t := range strings.Split(val, ",") {
					t = strings.TrimSpace(t)
					if t != "" && !m.hasTLD(t) {
						m.tlds = append(m.tlds, t)
					}
				}
				m.tldInput.SetValue("")
				return m, nil
			}
			if len(m.tlds) == 0 {
				return m, nil
			}
			m.result.TLDs = m.tlds
			m.tldInput.Blur()
			m.step = stepConfirm
			return m, nil
		case "backspace":
			if m.tldInput.Value() == "" && len(m.tlds) > 0 {
				m.tlds = m.tlds[:len(m.tlds)-1]
				return m, nil
			}
		}
	}
	var cmd tea.Cmd
	m.tldInput, cmd = m.tldInput.Update(msg)
	return m, cmd
}

func (m wizardModel) hasTLD(tld string) bool {
	for _, t := range m.tlds {
		if t == tld {
			return true
		}
	}
	return false
}

func (m wizardModel) updateConfirm(msg tea.Msg) (tea.Model, tea.Cmd) {
	if key, ok := msg.(tea.KeyMsg); ok {
		switch key.String() {
		case "y", "Y", "enter":
			return m, tea.Quit
		case "n", "N":
			m.result.Cancelled = true
			return m, tea.Quit
		}
	}
	return m, nil
}

func (m wizardModel) View() string {
	s := getWizardStyles()

	var sections []string

	sections = append(sections, s.title.Render("ddt install"))

	switch m.step {
	case stepWelcome:
		sections = append(sections, m.viewWelcome(s))
	case stepIP:
		sections = append(sections, m.viewIP(s))
	case stepTLDs:
		sections = append(sections, m.viewTLDs(s))
	case stepConfirm:
		sections = append(sections, m.viewConfirm(s))
	}

	sections = append(sections, s.hint.Render("esc to cancel"))

	return s.content.Render(lipgloss.JoinVertical(lipgloss.Left, sections...))
}

func (m wizardModel) viewWelcome(s wizardStyles) string {
	lines := []string{
		s.body.Render("This will set up your local development environment:"),
		"",
		s.bullet.Render("  * Configure an IP alias on your loopback interface"),
		s.bullet.Render("  * Set up a local DNS server for custom TLDs"),
		s.bullet.Render("  * Install system services so it persists across reboots"),
		"",
		s.prompt.Render("Press enter to begin"),
	}
	return lipgloss.JoinVertical(lipgloss.Left, lines...)
}

func (m wizardModel) viewIP(s wizardStyles) string {
	lines := []string{
		s.heading.Render("IP Address"),
		s.body.Render("This IP will be aliased on your loopback interface."),
		s.body.Render("DNS and proxy will bind to this address."),
		"",
		m.ipInput.View(),
	}
	return lipgloss.JoinVertical(lipgloss.Left, lines...)
}

func (m wizardModel) viewTLDs(s wizardStyles) string {
	lines := []string{
		s.heading.Render("DNS Top-Level Domains"),
		s.body.Render("Add TLDs for wildcard DNS resolution (e.g. *.localhost, *.develop)."),
		s.body.Render("Type a name and press enter. Press enter on empty to continue."),
		"",
	}

	if len(m.tlds) > 0 {
		var tags []string
		for _, t := range m.tlds {
			tags = append(tags, s.tag.Render("."+t))
		}
		lines = append(lines, strings.Join(tags, " "), "")
	}

	lines = append(lines, m.tldInput.View())

	if len(m.tlds) > 0 {
		lines = append(lines, s.hint.Render("backspace on empty removes last | enter on empty to continue"))
	} else {
		lines = append(lines, s.warn.Render("at least one TLD is required"))
	}

	return lipgloss.JoinVertical(lipgloss.Left, lines...)
}

func (m wizardModel) viewConfirm(s wizardStyles) string {
	lines := []string{
		s.heading.Render("Summary"),
		"",
		s.label.Render("IP address:") + "  " + s.value.Render(m.result.IPAddress),
	}

	tldStrs := make([]string, len(m.result.TLDs))
	for i, t := range m.result.TLDs {
		tldStrs[i] = "." + t
	}
	lines = append(lines,
		s.label.Render("DNS TLDs:") + "  " + s.value.Render(strings.Join(tldStrs, ", ")),
		s.label.Render("Config:") + "  " + s.dim.Render(configPathForDisplay()),
		"",
		s.prompt.Render("Install? (y/n)"),
	)

	return lipgloss.JoinVertical(lipgloss.Left, lines...)
}

// configPathForDisplay returns a display-friendly config path.
// Set via SetConfigPathDisplay to avoid circular dependency with config package.
var configPathForDisplay = func() string { return "~/.config/docker-dev-tools/config.json" }

// SetConfigPathDisplay sets the function used to display the config path in the wizard.
func SetConfigPathDisplay(fn func() string) {
	configPathForDisplay = fn
}

// RunInstallWizard runs the interactive install wizard and returns the collected values.
func RunInstallWizard(defaultIP string, defaultTLDs []string) (WizardResult, error) {
	model := newWizardModel(defaultIP, defaultTLDs)
	p := tea.NewProgram(model)
	final, err := p.Run()
	if err != nil {
		return WizardResult{Cancelled: true}, err
	}
	return final.(wizardModel).result, nil
}
