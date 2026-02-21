package cli

import (
	"fmt"

	"github.com/christhomas/docker-dev-tools/internal/app"
	"github.com/christhomas/docker-dev-tools/internal/tui/styles"
	"github.com/spf13/cobra"
)

func newSetupCmd(a *app.App) *cobra.Command {
	cmd := &cobra.Command{
		Use:   "setup",
		Short: "Install or uninstall ddt",
		Long:  "Set up ddt by creating the config file and adding it to your shell PATH.",
	}

	cmd.AddCommand(
		&cobra.Command{
			Use:   "install",
			Short: "Install ddt and create initial configuration",
			RunE: func(cmd *cobra.Command, args []string) error {
				// Create config if it doesn't exist.
				if err := a.Config.Save(); err != nil {
					return fmt.Errorf("creating config: %w", err)
				}
				fmt.Println(styles.SuccessStyle.Render("ddt installed successfully"))
				fmt.Printf("  %s %s\n",
					styles.Label.Render("Config:"),
					styles.Value.Render(a.Config.Path()),
				)
				return nil
			},
		},
		&cobra.Command{
			Use:   "uninstall",
			Short: "Remove ddt configuration",
			RunE: func(cmd *cobra.Command, args []string) error {
				// TODO: remove config file and shell PATH entries.
				fmt.Println(styles.WarningStyle.Render("Uninstall not yet implemented"))
				return nil
			},
		},
	)

	return cmd
}
