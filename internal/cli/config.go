package cli

import (
	"encoding/json"
	"fmt"

	"github.com/christhomas/docker-dev-tools/internal/app"
	"github.com/christhomas/docker-dev-tools/internal/config"
	"github.com/christhomas/docker-dev-tools/internal/tui/styles"
	"github.com/spf13/cobra"
)

func newConfigCmd(a *app.App) *cobra.Command {
	cmd := &cobra.Command{
		Use:   "config",
		Short: "Manage system configuration",
		Long:  "Read, write, and inspect the ddt system configuration.",
	}

	cmd.AddCommand(
		&cobra.Command{
			Use:   "path",
			Short: "Show the config file path",
			RunE: func(cmd *cobra.Command, args []string) error {
				fmt.Println(a.Config.Path())
				return nil
			},
		},
		&cobra.Command{
			Use:   "show",
			Short: "Display the full configuration",
			RunE: func(cmd *cobra.Command, args []string) error {
				data, err := json.MarshalIndent(a.Config, "", "  ")
				if err != nil {
					return err
				}
				fmt.Println(string(data))
				return nil
			},
		},
		&cobra.Command{
			Use:   "version",
			Short: "Show the config schema version",
			RunE: func(cmd *cobra.Command, args []string) error {
				fmt.Printf("%s  %s\n",
					styles.Label.Render("Config version:"),
					styles.Value.Render(a.Config.Version),
				)
				return nil
			},
		},
		&cobra.Command{
			Use:   "reset",
			Short: "Reset configuration to defaults",
			RunE: func(cmd *cobra.Command, args []string) error {
				defaults := config.DefaultSystemConfig()
				defaults.Save()
				fmt.Println(styles.SuccessStyle.Render("Configuration reset to defaults"))
				return nil
			},
		},
	)

	return cmd
}
