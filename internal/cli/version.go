package cli

import (
	"fmt"

	"github.com/christhomas/docker-dev-tools/internal/app"
	"github.com/spf13/cobra"
)

func newVersionCmd(a *app.App) *cobra.Command {
	return &cobra.Command{
		Use:   "version",
		Short: "Show version information",
		RunE: func(cmd *cobra.Command, args []string) error {
			// The same output as `ddt --version`, so the two cannot drift apart.
			_, err := fmt.Fprintf(cmd.OutOrStdout(), "%s %s\n%s",
				cmd.Root().Name(), a.Build.String(), a.Build.Details())
			return err
		},
	}
}
