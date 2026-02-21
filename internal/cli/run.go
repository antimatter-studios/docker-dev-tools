package cli

import (
	"fmt"

	"github.com/christhomas/docker-dev-tools/internal/app"
	"github.com/christhomas/docker-dev-tools/internal/tui/components"
	"github.com/christhomas/docker-dev-tools/internal/tui/styles"
	"github.com/spf13/cobra"
)

func newRunCmd(a *app.App) *cobra.Command {
	cmd := &cobra.Command{
		Use:   "run <script> <project>",
		Short: "Execute project scripts with dependency resolution",
		Long:  "Run a named script for a project, automatically resolving and executing dependent scripts first.",
		Args:  cobra.ExactArgs(2),
		RunE: func(cmd *cobra.Command, args []string) error {
			listFlag, _ := cmd.Flags().GetBool("list")
			if listFlag {
				return listProjectScripts(a, args[1])
			}
			return a.Runner.Run(args[1], args[0])
		},
	}

	cmd.Flags().BoolP("list", "l", false, "list available scripts for the project")

	return cmd
}

func listProjectScripts(a *app.App, projectName string) error {
	scripts, err := a.Runner.ListScripts(projectName)
	if err != nil {
		return err
	}
	if len(scripts) == 0 {
		fmt.Println(styles.Dimmed.Render(fmt.Sprintf("No scripts defined for %q", projectName)))
		return nil
	}
	rows := make([][]string, 0, len(scripts))
	for _, name := range scripts {
		rows = append(rows, []string{name})
	}
	fmt.Println(components.RenderTable([]string{"Script"}, rows))
	return nil
}
