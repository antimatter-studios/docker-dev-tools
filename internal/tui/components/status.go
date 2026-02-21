package components

import (
	"context"
	"fmt"
	"os"
	"strings"
	"time"

	"github.com/charmbracelet/lipgloss"
	"github.com/charmbracelet/x/term"
	"github.com/christhomas/docker-dev-tools/internal/app"
	"github.com/christhomas/docker-dev-tools/internal/docker"
	"github.com/christhomas/docker-dev-tools/internal/tui/styles"
)

const (
	minCardWidth     = 30
	cardGap          = 2
	defaultTermWidth = 80
)

// termWidth returns the current terminal width, falling back to a default.
func termWidth() int {
	w, _, err := term.GetSize(os.Stdout.Fd())
	if err != nil || w <= 0 {
		return defaultTermWidth
	}
	return w
}

// RenderStatusDashboard renders the full system status including service cards,
// DNS details (TLDs, domains, upstreams), and proxy details (registered services).
func RenderStatusDashboard(a *app.App) string {
	ctx := context.Background()
	width := termWidth()

	ipActive, _ := a.IP.IsActive()
	dnsRunning, _ := a.DNS.IsRunning(ctx)
	proxyRunning, _ := a.Proxy.IsRunning(ctx)

	// Gather container details for richer display.
	dnsDetails := a.DNS.ContainerDetails(ctx)
	proxyDetails := a.Proxy.ContainerDetails(ctx)

	dnsRows := []kvRow{
		{"Container", a.DNS.ContainerName()},
		{"Image", a.DNS.Image()},
	}
	if dnsDetails != nil {
		if dnsDetails.ImageID != "" {
			dnsRows = append(dnsRows, kvRow{"Image ID", shortImageID(dnsDetails.ImageID)})
		}
		if dnsDetails.ImageCreated != "" {
			dnsRows = append(dnsRows, kvRow{"Built", formatImageDate(dnsDetails.ImageCreated)})
		}
		dnsRows = append(dnsRows, kvRow{"ID", shortID(dnsDetails.ID)})
		for _, pb := range dnsDetails.PortBindings {
			dnsRows = append(dnsRows, kvRow{"Port", pb.String()})
		}
	}
	for _, tld := range a.DNS.ConfiguredTLDs() {
		dnsRows = append(dnsRows, kvRow{"TLD", "*." + tld + " \u2192 " + a.Config.IPAddress})
	}
	if dnsRunning {
		upstreams, _ := a.DNS.ListUpstreams(ctx)
		for _, u := range upstreams {
			dnsRows = append(dnsRows, kvRow{"Upstream", u})
		}
	}

	proxyRows := []kvRow{
		{"Container", a.Proxy.ContainerName()},
		{"Image", a.Proxy.Image()},
	}
	if proxyDetails != nil {
		if proxyDetails.ImageID != "" {
			proxyRows = append(proxyRows, kvRow{"Image ID", shortImageID(proxyDetails.ImageID)})
		}
		if proxyDetails.ImageCreated != "" {
			proxyRows = append(proxyRows, kvRow{"Built", formatImageDate(proxyDetails.ImageCreated)})
		}
		proxyRows = append(proxyRows, kvRow{"ID", shortID(proxyDetails.ID)})
		for _, pb := range proxyDetails.PortBindings {
			proxyRows = append(proxyRows, kvRow{"Port", pb.String()})
		}
	}
	networks := a.Proxy.Networks()
	if len(networks) > 0 {
		proxyRows = append(proxyRows, kvRow{"Networks", strings.Join(networks, ", ")})
	}

	cards := []cardDef{
		{icon: "📡", title: "DNS Server", active: dnsRunning, rows: dnsRows},
		{icon: "🔀", title: "Reverse Proxy", active: proxyRunning, rows: proxyRows},
	}

	var b strings.Builder
	b.WriteString(styles.Banner("⚙", "System Status"))
	b.WriteString("\n\n")

	cardsStr, cardsWidth := layoutCards(cards, width)
	b.WriteString(cardsStr)

	// Build the details box content, constrained to the same width as the cards.
	details := renderDetailsBox(ctx, a, cardsWidth, ipActive, proxyRunning)
	if details != "" {
		b.WriteString("\n\n")
		detailStyle := styles.CardActive.Width(cardsWidth)
		b.WriteString(detailStyle.Render(details))
	}

	b.WriteString("\n")
	return b.String()
}

// renderDetailsBox combines IP alias and proxy details into a single panel
// with a title bar matching the card layout above.
func renderDetailsBox(ctx context.Context, a *app.App, cardsWidth int, ipActive, proxyRunning bool) string {
	titleStyle := lipgloss.NewStyle().Bold(true).Foreground(styles.Text)
	iconStyle := lipgloss.NewStyle().Foreground(styles.Secondary)
	section := lipgloss.NewStyle().Bold(true).Foreground(styles.Secondary)

	// Inner width = total width minus card chrome (2 border + 4 padding).
	innerWidth := cardsWidth - 6
	if innerWidth < 10 {
		innerWidth = 10
	}

	var b strings.Builder

	// Title line + rule, matching the card style.
	b.WriteString(fmt.Sprintf("%s %s\n",
		iconStyle.Render("📋"),
		titleStyle.Render("Details"),
	))
	b.WriteString(styles.HorizontalRule(innerWidth))
	b.WriteString("\n")

	// IP alias line.
	b.WriteString(section.Render("IP Alias"))
	b.WriteString(fmt.Sprintf("  %s  %s",
		styles.Value.Render(a.IP.Get()),
		styles.StatusBadge(ipActive),
	))

	// Proxy details.
	proxySection := renderProxyDetails(ctx, a, proxyRunning)
	if proxySection != "" {
		b.WriteString("\n\n")
		b.WriteString(proxySection)
	}

	return b.String()
}

// renderProxyDetails renders the registered proxy services table.
func renderProxyDetails(ctx context.Context, a *app.App, running bool) string {
	if !running {
		return ""
	}

	entries, err := a.Proxy.Status(ctx)
	if err != nil || len(entries) == 0 {
		return ""
	}

	section := lipgloss.NewStyle().Bold(true).Foreground(styles.Secondary)

	rows := make([][]string, 0, len(entries))
	for _, e := range entries {
		rows = append(rows, []string{
			e.Network,
			e.Container,
			e.Proto + "://" + e.Host,
			e.Port,
			e.Path,
		})
	}

	var b strings.Builder
	b.WriteString(section.Render("Proxied Services"))
	b.WriteString("\n")
	b.WriteString(RenderTable(
		[]string{"Network", "Container", "Host", "Port", "Path"},
		rows,
	))
	return b.String()
}

type cardDef struct {
	icon   string
	title  string
	active bool
	rows   []kvRow
}

type kvRow struct {
	Key   string
	Value string
}

// cardContentWidth calculates the natural width a card needs based on its content.
// This accounts for the label column, gap, longest value, and card chrome (border + padding).
func cardContentWidth(c cardDef) int {
	const cardChrome = 6 // 2 border + 4 padding

	// Determine label column width.
	labelWidth := 11
	for _, r := range c.rows {
		if len(r.Key) > labelWidth-1 {
			labelWidth = len(r.Key) + 1
		}
	}

	// Find the widest row: label + 2 (gap) + value length.
	maxRow := 0
	for _, r := range c.rows {
		w := labelWidth + 2 + len(r.Value)
		if w > maxRow {
			maxRow = w
		}
	}

	// Title line: icon(~2) + space + title + 2 spaces + status badge (~12).
	titleWidth := 2 + 1 + len(c.title) + 2 + 12

	content := maxRow
	if titleWidth > content {
		content = titleWidth
	}

	w := content + cardChrome
	if w < minCardWidth {
		w = minCardWidth
	}
	return w
}

// layoutCards arranges cards responsively based on available width.
// Each card is sized to its content. Cards wrap to the next row when they
// would exceed the terminal width. All cards in the same row share the
// height of the tallest card. Returns the rendered string and the width
// of the widest row (for aligning subsequent elements).
func layoutCards(cards []cardDef, totalWidth int) (string, int) {
	n := len(cards)
	if n == 0 {
		return "", 0
	}

	// Calculate each card's natural width.
	widths := make([]int, n)
	for i, c := range cards {
		widths[i] = cardContentWidth(c)
	}

	// Greedily pack cards into rows that fit within totalWidth.
	var rowStrings []string
	maxRowWidth := 0
	i := 0
	for i < n {
		rowWidth := widths[i]
		end := i + 1

		for end < n {
			next := rowWidth + cardGap + widths[end]
			if next > totalWidth {
				break
			}
			rowWidth = next
			end++
		}

		if rowWidth > maxRowWidth {
			maxRowWidth = rowWidth
		}

		// Render card bodies (without border/padding) and measure heights.
		count := end - i
		bodies := make([]string, count)
		heights := make([]int, count)
		maxH := 0
		for j := i; j < end; j++ {
			innerW := widths[j] - 6 // subtract border (2) + padding (4)
			if innerW < 10 {
				innerW = 10
			}
			bodies[j-i] = renderCardBody(cards[j], innerW)
			heights[j-i] = lipgloss.Height(bodies[j-i])
			if heights[j-i] > maxH {
				maxH = heights[j-i]
			}
		}

		// Pad shorter bodies to match the tallest, then apply card style.
		var rendered []string
		for j := i; j < end; j++ {
			body := bodies[j-i]
			if heights[j-i] < maxH {
				body += strings.Repeat("\n", maxH-heights[j-i])
			}
			cardStyle := styles.Card.Width(widths[j])
			if cards[j].active {
				cardStyle = styles.CardActive.Width(widths[j])
			}
			rendered = append(rendered, cardStyle.Render(body))
		}

		gap := strings.Repeat(" ", cardGap)
		row := lipgloss.JoinHorizontal(lipgloss.Top, interleave(rendered, gap)...)
		rowStrings = append(rowStrings, row)

		i = end
	}

	return strings.Join(rowStrings, "\n"), maxRowWidth
}

// interleave inserts sep between each element.
func interleave(items []string, sep string) []string {
	if len(items) <= 1 {
		return items
	}
	result := make([]string, 0, len(items)*2-1)
	for i, item := range items {
		if i > 0 {
			result = append(result, sep)
		}
		result = append(result, item)
	}
	return result
}

// renderCardBody generates the inner content of a service card (title, rule,
// key-value rows) without any border or padding — those are applied later in
// layoutCards so that all cards in a row can be equalised to the same height.
func renderCardBody(c cardDef, innerWidth int) string {
	var b strings.Builder

	// Title line.
	titleStyle := lipgloss.NewStyle().Bold(true).Foreground(styles.Text)
	iconStyle := lipgloss.NewStyle().Foreground(styles.Secondary)
	b.WriteString(fmt.Sprintf("%s %s  %s\n",
		iconStyle.Render(c.icon),
		titleStyle.Render(c.title),
		styles.StatusBadge(c.active),
	))

	b.WriteString(styles.HorizontalRule(innerWidth))
	b.WriteString("\n")

	// Determine label column width from the longest key.
	labelWidth := 11
	for _, r := range c.rows {
		if len(r.Key) > labelWidth-1 {
			labelWidth = len(r.Key) + 1
		}
	}

	// Key-value rows.
	for _, r := range c.rows {
		label := lipgloss.NewStyle().
			Foreground(styles.Subtle).
			Width(labelWidth).
			Align(lipgloss.Right).
			Render(r.Key)
		val := lipgloss.NewStyle().Foreground(styles.TextDim).Render(r.Value)
		b.WriteString(fmt.Sprintf("%s  %s\n", label, val))
	}

	return b.String()
}

// shortID returns the first 12 characters of a container ID.
func shortID(id string) string {
	if len(id) > 12 {
		return id[:12]
	}
	return id
}

// shortImageID returns a truncated image ID, stripping the "sha256:" prefix.
func shortImageID(id string) string {
	id = strings.TrimPrefix(id, "sha256:")
	if len(id) > 12 {
		return id[:12]
	}
	return id
}

// formatImageDate parses an RFC 3339 timestamp and returns a human-readable date.
func formatImageDate(created string) string {
	t, err := time.Parse(time.RFC3339Nano, created)
	if err != nil {
		t, err = time.Parse(time.RFC3339, created)
		if err != nil {
			return created
		}
	}
	return t.Local().Format("2006-01-02 15:04")
}

// FormatPortBindings returns a human-readable summary of port bindings.
func FormatPortBindings(bindings []docker.PortBinding) string {
	if len(bindings) == 0 {
		return "none"
	}
	parts := make([]string, len(bindings))
	for i, b := range bindings {
		parts[i] = b.String()
	}
	return strings.Join(parts, ", ")
}
