package cli

import (
	"github.com/christhomas/docker-dev-tools/internal/app"
	"github.com/christhomas/docker-dev-tools/internal/tui/components"
	"github.com/spf13/cobra"
)

func newLogsCmd(a *app.App) *cobra.Command {
	return &cobra.Command{
		Use:   "logs",
		Short: "Interactive logs dashboard for all DDT containers",
		Long: `Open a full-screen TUI showing real-time logs from DNS, Proxy,
and ConfigGen containers with tab navigation, search highlighting,
and mark insertion.`,
		RunE: func(cmd *cobra.Command, args []string) error {
			return components.RunLogsDashboard(a)
		},
	}
}
