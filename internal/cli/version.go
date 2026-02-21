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
			fmt.Println(a.Build.String())
			return nil
		},
	}
}
