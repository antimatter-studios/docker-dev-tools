package cli

import (
	"github.com/christhomas/docker-dev-tools/internal/app"
	"github.com/spf13/cobra"
)

// NewRootCmd creates the root cobra command with all subcommands.
func NewRootCmd(application *app.App) *cobra.Command {
	root := &cobra.Command{
		Use:   "ddt",
		Short: "Docker Dev Tools - local development environment toolkit",
		Long: `ddt is a toolkit for managing your local Docker development environment.

It provides IP aliasing, local DNS, reverse proxying, project management,
and script execution with dependency resolution.`,
		SilenceUsage:  true,
		SilenceErrors: true,
		Version:       application.Build.String(),
	}

	// Global flags.
	root.PersistentFlags().Bool("debug", false, "enable debug output")

	// Register subcommands.
	root.AddCommand(
		newStartCmd(application),
		newStopCmd(application),
		newRestartCmd(application),
		newIPCmd(application),
		newDNSCmd(application),
		newProxyCmd(application),
		newProjectCmd(application),
		newRunCmd(application),
		newConfigCmd(application),
		newInstallCmd(application),
		newUninstallCmd(application),
		newStatusCmd(application),
		newVersionCmd(application),
		newMonitorCmd(application),
	)

	return root
}
