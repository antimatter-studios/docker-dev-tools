package cli

import (
	"fmt"

	"github.com/christhomas/docker-dev-tools/internal/app"
	"github.com/christhomas/docker-dev-tools/internal/tui/components"
	"github.com/spf13/cobra"
)

func newStatusCmd(a *app.App) *cobra.Command {
	return &cobra.Command{
		Use:   "status",
		Short: "Show system status dashboard",
		Long:  "Display the status of all ddt services: IP alias, DNS server, reverse proxy, registered domains, and proxied services.",
		RunE: func(cmd *cobra.Command, args []string) error {
			fmt.Println(components.RenderStatusDashboard(a))
			return nil
		},
	}
}
