package components

import (
	"github.com/charmbracelet/lipgloss"
	"github.com/charmbracelet/lipgloss/table"
	"github.com/christhomas/docker-dev-tools/internal/tui/styles"
)

// RenderTable creates a styled table from headers and rows.
func RenderTable(headers []string, rows [][]string) string {
	t := table.New().
		Border(lipgloss.NormalBorder()).
		BorderStyle(lipgloss.NewStyle().Foreground(styles.Muted)).
		Headers(headers...).
		Rows(rows...).
		StyleFunc(func(row, col int) lipgloss.Style {
			if row == table.HeaderRow {
				return lipgloss.NewStyle().
					Bold(true).
					Foreground(styles.Primary).
					Padding(0, 1)
			}
			return lipgloss.NewStyle().
				Foreground(styles.Text).
				Padding(0, 1)
		})

	return t.Render()
}
