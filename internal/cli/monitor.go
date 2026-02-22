package cli

import (
	"github.com/christhomas/docker-dev-tools/internal/app"
	"github.com/christhomas/docker-dev-tools/internal/tui/components"
	"github.com/spf13/cobra"
)

func newMonitorCmd(a *app.App) *cobra.Command {
	return &cobra.Command{
		Use:   "monitor",
		Short: "Interactive monitoring dashboard for all DDT services",
		Long: `Open a full-screen TUI showing real-time logs from DNS, Proxy,
and ConfigGen containers, plus a live status panel with tab navigation,
search highlighting, and mark insertion.`,
		Aliases: []string{"mon"},
		RunE: func(cmd *cobra.Command, args []string) error {
			return components.RunMonitorDashboard(a)
		},
	}
}
