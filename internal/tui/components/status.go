package components

import (
	"context"
	"fmt"
	"os"
	"sort"
	"strings"
	"time"

	"github.com/charmbracelet/lipgloss"
	"github.com/charmbracelet/x/term"
	"github.com/christhomas/docker-dev-tools/internal/app"
	"github.com/christhomas/docker-dev-tools/internal/docker"
	"github.com/christhomas/docker-dev-tools/internal/service"
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
// gatherDNSCard builds the DNS service info card definition.
func gatherDNSCard(a *app.App, ctx context.Context, dnsRunning bool) cardDef {
	ipActive, _ := a.IP.IsActive()
	dnsDetails := a.DNS.ContainerDetails(ctx)

	ipStatus := "inactive"
	if ipActive {
		ipStatus = "active"
	}
	rows := []kvRow{
		{"Container", a.DNS.ContainerName()},
		{"Image", a.DNS.Image()},
	}
	if dnsDetails != nil {
		if dnsDetails.ImageID != "" {
			rows = append(rows, kvRow{"Image ID", shortImageID(dnsDetails.ImageID)})
		}
		if dnsDetails.ImageCreated != "" {
			rows = append(rows, kvRow{"Built", formatImageDate(dnsDetails.ImageCreated)})
		}
		rows = append(rows, kvRow{"ID", shortID(dnsDetails.ID)})
		rows = append(rows, kvRow{"IP Alias", a.IP.Get() + " (" + ipStatus + ")"})
		sort.Slice(dnsDetails.PortBindings, func(i, j int) bool {
			return dnsDetails.PortBindings[i].String() < dnsDetails.PortBindings[j].String()
		})
		seen := make(map[string]struct{})
		for _, pb := range dnsDetails.PortBindings {
			key := pb.HostIP + ":" + pb.HostPort
			if _, ok := seen[key]; ok {
				continue
			}
			seen[key] = struct{}{}
			rows = append(rows, kvRow{"Port", pb.String()})
		}
	}
	if dnsDetails == nil {
		rows = append(rows, kvRow{"IP Alias", a.IP.Get() + " (" + ipStatus + ")"})
	}
	if dnsDetails != nil && len(dnsDetails.Networks) > 0 {
		for _, net := range dnsDetails.Networks {
			rows = append(rows, kvRow{"Network", net})
		}
	}
	if dnsRunning {
		upstreams, _ := a.DNS.ListUpstreams(ctx)
		for _, u := range upstreams {
			rows = append(rows, kvRow{"Upstream", u})
		}
	}
	return cardDef{icon: "📡", title: "DNS Server", active: dnsRunning, rows: rows}
}

// gatherProxyCard builds the Proxy service info card definition.
func gatherProxyCard(a *app.App, ctx context.Context, proxyRunning bool) cardDef {
	proxyDetails := a.Proxy.ContainerDetails(ctx)

	rows := []kvRow{
		{"Container", a.Proxy.ContainerName()},
		{"Image", a.Proxy.Image()},
	}
	if proxyDetails != nil {
		if proxyDetails.ImageID != "" {
			rows = append(rows, kvRow{"Image ID", shortImageID(proxyDetails.ImageID)})
		}
		if proxyDetails.ImageCreated != "" {
			rows = append(rows, kvRow{"Built", formatImageDate(proxyDetails.ImageCreated)})
		}
		rows = append(rows, kvRow{"ID", shortID(proxyDetails.ID)})
		sort.Slice(proxyDetails.PortBindings, func(i, j int) bool {
			return proxyDetails.PortBindings[i].String() < proxyDetails.PortBindings[j].String()
		})
		for _, pb := range proxyDetails.PortBindings {
			rows = append(rows, kvRow{"Port", pb.String()})
		}
	}
	return cardDef{icon: "🔀", title: "Reverse Proxy", active: proxyRunning, rows: rows}
}

// RenderServiceCard renders a single service info card as a standalone string.
func RenderServiceCard(card cardDef) string {
	w := cardContentWidth(card)
	innerW := w - 6
	if innerW < 10 {
		innerW = 10
	}
	body := renderCardBody(card, innerW)
	cardStyle := styles.Card.Width(w)
	if card.active {
		cardStyle = styles.CardActive.Width(w)
	}
	return cardStyle.Render(body)
}

// RenderDNSServiceCard renders the DNS service info card for standalone use.
func RenderDNSServiceCard(a *app.App, ctx context.Context, dnsRunning bool) string {
	return RenderServiceCard(gatherDNSCard(a, ctx, dnsRunning))
}

// RenderProxyServiceCard renders the Proxy service info card for standalone use.
func RenderProxyServiceCard(a *app.App, ctx context.Context, proxyRunning bool) string {
	return RenderServiceCard(gatherProxyCard(a, ctx, proxyRunning))
}

func RenderStatusDashboard(a *app.App, width int) string {
	ctx := context.Background()
	if width <= 0 {
		width = termWidth()
	}

	dnsRunning, _ := a.DNS.IsRunning(ctx)
	proxyRunning, _ := a.Proxy.IsRunning(ctx)

	dnsCard := gatherDNSCard(a, ctx, dnsRunning)
	proxyCard := gatherProxyCard(a, ctx, proxyRunning)

	cards := []cardDef{dnsCard, proxyCard}

	// Gather proxy service entries for the layout pass.
	var proxyEntries []service.ProxyStatusEntry
	var sidecarEntries []service.SidecarStatusEntry
	if proxyRunning {
		proxyEntries, _ = a.Proxy.Status(ctx)
		sortProxyEntries(proxyEntries)
		sidecarEntries, _ = a.Proxy.SidecarStatus(ctx)
		sortSidecarEntries(sidecarEntries)
	}

	// Measure natural widths for all boxes to determine unified width.
	dnsTableNatural := dnsTLDTableNaturalWidth(a, ctx, dnsRunning)
	proxyNatural := proxyServicesNaturalWidth(proxyEntries)
	sidecarNatural := sidecarServicesNaturalWidth(sidecarEntries)
	minWidth := proxyNatural
	if dnsTableNatural > minWidth {
		minWidth = dnsTableNatural
	}
	if sidecarNatural > minWidth {
		minWidth = sidecarNatural
	}

	var b strings.Builder
	b.WriteString(styles.Banner("⚙", "System Status"))
	b.WriteString("\n\n")

	cardsStr, cardsWidth := layoutCards(cards, width, minWidth)
	b.WriteString(cardsStr)

	// DNS TLD status table.
	dnsTable := renderDNSTLDBoxFromApp(a, ctx, dnsRunning, cardsWidth)
	if dnsTable != "" {
		b.WriteString("\n\n")
		detailStyle := styles.CardActive.Width(cardsWidth - 2)
		b.WriteString(detailStyle.Render(dnsTable))
	}

	// Proxied services table with probing.
	details := renderProxyServicesBoxFromEntries(proxyEntries, cardsWidth)
	if details != "" {
		b.WriteString("\n\n")
		detailStyle := styles.CardActive.Width(cardsWidth - 2)
		b.WriteString(detailStyle.Render(details))
	}

	// Sidecar services table.
	sidecarDetails := renderSidecarServicesBoxFromEntries(sidecarEntries, cardsWidth)
	if sidecarDetails != "" {
		b.WriteString("\n\n")
		detailStyle := styles.CardActive.Width(cardsWidth - 2)
		b.WriteString(detailStyle.Render(sidecarDetails))
	}

	b.WriteString("\n")
	return b.String()
}

// proxyServicesNaturalWidth computes the natural visual width the proxy
// services box would need to display without truncation. This renders the
// table unconstrained and adds card chrome (border + padding).
func sortProxyEntries(entries []service.ProxyStatusEntry) {
	sort.Slice(entries, func(i, j int) bool {
		if entries[i].Container != entries[j].Container {
			return entries[i].Container < entries[j].Container
		}
		if entries[i].Network != entries[j].Network {
			return entries[i].Network < entries[j].Network
		}
		return entries[i].Host < entries[j].Host
	})
}

func sortSidecarEntries(entries []service.SidecarStatusEntry) {
	sort.Slice(entries, func(i, j int) bool {
		if entries[i].Proto != entries[j].Proto {
			return entries[i].Proto < entries[j].Proto
		}
		return entries[i].Port < entries[j].Port
	})
}

// ── DNS TLD table ────────────────────────────────────────────────────

// DNSTLDData holds the gathered TLD state from all three sources.
type DNSTLDData struct {
	AllTLDs      []string
	InConfig     map[string]bool
	InContainer  map[string]bool
	InSystem     map[string]bool
	DNSRunning   bool
}

// GatherDNSTLDData collects TLD information from config, container, and system resolvers.
func GatherDNSTLDData(a *app.App, ctx context.Context, dnsRunning bool) DNSTLDData {
	configTLDs := a.DNS.ConfiguredTLDs()

	var containerTLDs []string
	if dnsRunning {
		entries, _ := a.DNS.ListDomains(ctx)
		for _, e := range entries {
			d := strings.TrimPrefix(e.Domain, ".")
			containerTLDs = append(containerTLDs, d)
		}
	}

	systemTLDs, _ := a.DNS.ListSystemResolvers()

	// Build union.
	seen := make(map[string]bool)
	for _, t := range configTLDs {
		seen[t] = true
	}
	for _, t := range containerTLDs {
		seen[t] = true
	}
	for _, t := range systemTLDs {
		seen[t] = true
	}

	allTLDs := make([]string, 0, len(seen))
	for t := range seen {
		allTLDs = append(allTLDs, t)
	}
	sort.Strings(allTLDs)

	inConfig := toSet(configTLDs)
	inContainer := toSet(containerTLDs)
	inSystem := toSet(systemTLDs)

	return DNSTLDData{
		AllTLDs:     allTLDs,
		InConfig:    inConfig,
		InContainer: inContainer,
		InSystem:    inSystem,
		DNSRunning:  dnsRunning,
	}
}

func toSet(items []string) map[string]bool {
	m := make(map[string]bool, len(items))
	for _, s := range items {
		m[s] = true
	}
	return m
}

// CheckMark returns a styled check or cross mark.
func CheckMark(ok bool) string {
	if ok {
		return styles.SuccessStyle.Render("✓")
	}
	return styles.ErrorStyle.Render("✗")
}

var dnsTLDHeaders = []string{"TLD", "Config", "Container", "Resolver"}

func buildDNSTLDRows(data DNSTLDData) [][]string {
	rows := make([][]string, 0, len(data.AllTLDs))
	for _, tld := range data.AllTLDs {
		rows = append(rows, []string{
			"." + tld,
			CheckMark(data.InConfig[tld]),
			CheckMark(data.DNSRunning && data.InContainer[tld]),
			CheckMark(data.InSystem[tld]),
		})
	}
	return rows
}

// RenderDNSTLDTable renders a standalone DNS TLD status table.
func RenderDNSTLDTable(a *app.App, ctx context.Context, dnsRunning bool) string {
	data := GatherDNSTLDData(a, ctx, dnsRunning)
	if len(data.AllTLDs) == 0 {
		return styles.InfoStyle.Render("No TLDs configured")
	}

	headers := make([]string, len(dnsTLDHeaders))
	copy(headers, dnsTLDHeaders)
	if !dnsRunning {
		headers[2] = "Container (stopped)"
	}

	rows := buildDNSTLDRows(data)

	var b strings.Builder
	b.WriteString(styles.Banner("📡", fmt.Sprintf("DNS TLD Status (%d)", len(data.AllTLDs))))
	b.WriteString("\n\n")
	b.WriteString(RenderTable(headers, rows))
	return b.String()
}

func dnsTLDTableNaturalWidth(a *app.App, ctx context.Context, dnsRunning bool) int {
	data := GatherDNSTLDData(a, ctx, dnsRunning)
	if len(data.AllTLDs) == 0 {
		return 0
	}
	const cardChrome = 6
	rows := buildDNSTLDRows(data)
	tableStr := RenderTable(dnsTLDHeaders, rows)
	return lipgloss.Width(tableStr) + cardChrome
}

func renderDNSTLDBoxFromApp(a *app.App, ctx context.Context, dnsRunning bool, totalWidth int) string {
	data := GatherDNSTLDData(a, ctx, dnsRunning)
	if len(data.AllTLDs) == 0 {
		return ""
	}

	titleStyle := lipgloss.NewStyle().Bold(true).Foreground(styles.Text)
	iconStyle := lipgloss.NewStyle().Foreground(styles.Secondary)

	innerWidth := totalWidth - 6
	if innerWidth < 10 {
		innerWidth = 10
	}

	headers := make([]string, len(dnsTLDHeaders))
	copy(headers, dnsTLDHeaders)
	if !dnsRunning {
		headers[2] = "Container (stopped)"
	}

	rows := buildDNSTLDRows(data)

	var b strings.Builder
	b.WriteString(fmt.Sprintf("%s %s\n",
		iconStyle.Render("📡"),
		titleStyle.Render(fmt.Sprintf("DNS TLD Status (%d)", len(data.AllTLDs))),
	))
	b.WriteString(styles.HorizontalRule(innerWidth))
	b.WriteString("\n")
	b.WriteString(RenderTable(headers, rows, innerWidth))

	return b.String()
}

// ── Proxy services table ─────────────────────────────────────────────

// buildProxyRows probes all entries in parallel and returns table rows with a Status column.
func buildProxyRows(entries []service.ProxyStatusEntry, hyperlinks bool) [][]string {
	probes := service.ProbeServices(entries)
	rows := make([][]string, 0, len(entries))
	for i, e := range entries {
		url := e.Proto + "://" + e.Host
		if hyperlinks {
			url = styles.Hyperlink(url, url)
		}
		status := FormatHTTPStatus(probes[i].StatusCode, probes[i].Status)
		if probes[i].Fallback {
			status += " " + styles.WarningStyle.Render("(no upstream)")
		}
		rows = append(rows, []string{e.Container, e.Network, url, e.Port, e.Path, status})
	}
	return rows
}

var proxyHeaders = []string{"Container", "Network", "Host", "Port", "Path", "Status"}
var sidecarHeaders = []string{"Container", "Protocol", "Port", "Status"}

// RenderProxyServicesTable renders a standalone proxy services table with probing.
func RenderProxyServicesTable(entries []service.ProxyStatusEntry) string {
	if len(entries) == 0 {
		return ""
	}
	rows := buildProxyRows(entries, false)
	var b strings.Builder
	b.WriteString(styles.Banner("🔀", fmt.Sprintf("Proxied Services (%d)", len(entries))))
	b.WriteString("\n\n")
	b.WriteString(RenderTable(proxyHeaders, rows))
	return b.String()
}

// RenderSidecarServicesTable renders a standalone sidecar services table.
func RenderSidecarServicesTable(entries []service.SidecarStatusEntry) string {
	if len(entries) == 0 {
		return ""
	}
	rows := make([][]string, 0, len(entries))
	for _, e := range entries {
		rows = append(rows, []string{e.Container, e.Proto, e.Port, e.Status})
	}
	var b strings.Builder
	b.WriteString(styles.Banner("🔌", fmt.Sprintf("TCP/UDP Sidecars (%d)", len(entries))))
	b.WriteString("\n\n")
	b.WriteString(RenderTable(sidecarHeaders, rows))
	return b.String()
}

func proxyServicesNaturalWidth(entries []service.ProxyStatusEntry) int {
	if len(entries) == 0 {
		return 0
	}
	const cardChrome = 6 // 2 border + 4 padding
	// Use a dummy status column width for measurement.
	rows := make([][]string, 0, len(entries))
	for _, e := range entries {
		url := e.Proto + "://" + e.Host
		rows = append(rows, []string{e.Container, e.Network, url, e.Port, e.Path, "200"})
	}
	tableStr := RenderTable(proxyHeaders, rows)
	return lipgloss.Width(tableStr) + cardChrome
}

func sidecarServicesNaturalWidth(entries []service.SidecarStatusEntry) int {
	if len(entries) == 0 {
		return 0
	}
	const cardChrome = 6 // 2 border + 4 padding
	rows := make([][]string, 0, len(entries))
	for _, e := range entries {
		rows = append(rows, []string{e.Container, e.Proto, e.Port, e.Status})
	}
	tableStr := RenderTable(sidecarHeaders, rows)
	return lipgloss.Width(tableStr) + cardChrome
}

// renderProxyServicesBoxFromEntries renders the proxied services panel content
// at the given total width with HTTP probing. The caller wraps this in a CardActive style.
func renderProxyServicesBoxFromEntries(entries []service.ProxyStatusEntry, totalWidth int) string {
	if len(entries) == 0 {
		return ""
	}

	titleStyle := lipgloss.NewStyle().Bold(true).Foreground(styles.Text)
	iconStyle := lipgloss.NewStyle().Foreground(styles.Secondary)

	innerWidth := totalWidth - 6
	if innerWidth < 10 {
		innerWidth = 10
	}

	rows := buildProxyRows(entries, true)

	var b strings.Builder
	b.WriteString(fmt.Sprintf("%s %s\n",
		iconStyle.Render("🔀"),
		titleStyle.Render(fmt.Sprintf("Proxied Services (%d)", len(entries))),
	))
	b.WriteString(styles.HorizontalRule(innerWidth))
	b.WriteString("\n")
	b.WriteString(RenderTable(proxyHeaders, rows, innerWidth))

	return b.String()
}

// renderSidecarServicesBoxFromEntries renders the sidecar services panel content
// at the given total width. The caller wraps this in a CardActive style.
func renderSidecarServicesBoxFromEntries(entries []service.SidecarStatusEntry, totalWidth int) string {
	if len(entries) == 0 {
		return ""
	}

	titleStyle := lipgloss.NewStyle().Bold(true).Foreground(styles.Text)
	iconStyle := lipgloss.NewStyle().Foreground(styles.Secondary)

	innerWidth := totalWidth - 6
	if innerWidth < 10 {
		innerWidth = 10
	}

	rows := make([][]string, 0, len(entries))
	for _, e := range entries {
		rows = append(rows, []string{e.Container, e.Proto, e.Port, e.Status})
	}

	var b strings.Builder
	b.WriteString(fmt.Sprintf("%s %s\n",
		iconStyle.Render("🔌"),
		titleStyle.Render(fmt.Sprintf("TCP/UDP Sidecars (%d)", len(entries))),
	))
	b.WriteString(styles.HorizontalRule(innerWidth))
	b.WriteString("\n")
	b.WriteString(RenderTable(sidecarHeaders, rows, innerWidth))

	return b.String()
}

// FormatHTTPStatus returns a styled status string for display in tables.
func FormatHTTPStatus(code int, status string) string {
	if status == "error" {
		return styles.ErrorStyle.Render("error")
	}
	text := fmt.Sprintf("%d", code)
	switch {
	case code >= 200 && code < 300:
		return styles.SuccessStyle.Render(text)
	case code >= 300 && code < 400:
		return styles.InfoStyle.Render(text)
	case code >= 400 && code < 500:
		return styles.WarningStyle.Render(text)
	default:
		return styles.ErrorStyle.Render(text)
	}
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
// height of the tallest card. If minRowWidth > 0, each row is expanded to
// at least that width by growing the last card. Returns the rendered string
// and the width of the widest row (for aligning subsequent elements).
func layoutCards(cards []cardDef, totalWidth int, minRowWidth int) (string, int) {
	n := len(cards)
	if n == 0 {
		return "", 0
	}

	// Calculate each card's natural width.
	widths := make([]int, n)
	for i, c := range cards {
		widths[i] = cardContentWidth(c)
	}

	// Cap minimum at terminal width.
	if minRowWidth > totalWidth {
		minRowWidth = totalWidth
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

		// Expand last card in the row if the row is narrower than the minimum.
		if minRowWidth > 0 && rowWidth < minRowWidth {
			extra := minRowWidth - rowWidth
			widths[end-1] += extra
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

		// Measure actual rendered width (accounts for borders lipgloss adds).
		if w := lipgloss.Width(row); w > maxRowWidth {
			maxRowWidth = w
		}

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
