package cli

import (
	"fmt"

	"github.com/christhomas/docker-dev-tools/internal/app"
	"github.com/christhomas/docker-dev-tools/internal/tui/components"
	"github.com/christhomas/docker-dev-tools/internal/tui/styles"
	"github.com/spf13/cobra"
)

func newProjectCmd(a *app.App) *cobra.Command {
	cmd := &cobra.Command{
		Use:   "project",
		Short: "Manage development projects",
		Long:  "Register project paths, add individual projects, and manage project groups.",
	}

	addPathCmd := &cobra.Command{
		Use:   "add-path <directory> <name>",
		Short: "Register a directory to scan for projects",
		Args:  cobra.ExactArgs(2),
		RunE: func(cmd *cobra.Command, args []string) error {
			if err := a.Project.AddPath(args[0], args[1]); err != nil {
				return err
			}
			fmt.Println(styles.SuccessStyle.Render(fmt.Sprintf("Path %q registered as %q", args[0], args[1])))
			return nil
		},
	}

	removePathCmd := &cobra.Command{
		Use:   "remove-path <name>",
		Short: "Remove a registered directory path",
		Args:  cobra.ExactArgs(1),
		RunE: func(cmd *cobra.Command, args []string) error {
			if err := a.Project.RemovePath(args[0]); err != nil {
				return err
			}
			fmt.Println(styles.SuccessStyle.Render(fmt.Sprintf("Path %q removed", args[0])))
			return nil
		},
	}

	listPathsCmd := &cobra.Command{
		Use:   "list-paths",
		Short: "List all registered project paths",
		RunE: func(cmd *cobra.Command, args []string) error {
			paths := a.Project.ListPaths()
			if len(paths) == 0 {
				fmt.Println(styles.Dimmed.Render("No project paths registered"))
				return nil
			}
			rows := make([][]string, 0, len(paths))
			for name, dir := range paths {
				rows = append(rows, []string{name, dir})
			}
			fmt.Println(components.RenderTable([]string{"Name", "Directory"}, rows))
			return nil
		},
	}

	addProjectCmd := &cobra.Command{
		Use:   "add <directory> <name>",
		Short: "Register a specific project",
		Args:  cobra.ExactArgs(2),
		RunE: func(cmd *cobra.Command, args []string) error {
			group, _ := cmd.Flags().GetString("group")
			if err := a.Project.AddProject(args[0], args[1], group); err != nil {
				return err
			}
			fmt.Println(styles.SuccessStyle.Render(fmt.Sprintf("Project %q added", args[1])))
			return nil
		},
	}
	addProjectCmd.Flags().StringP("group", "g", "", "project group name")

	removeProjectCmd := &cobra.Command{
		Use:   "remove <name>",
		Short: "Remove a registered project",
		Args:  cobra.ExactArgs(1),
		RunE: func(cmd *cobra.Command, args []string) error {
			if err := a.Project.RemoveProject(args[0]); err != nil {
				return err
			}
			fmt.Println(styles.SuccessStyle.Render(fmt.Sprintf("Project %q removed", args[0])))
			return nil
		},
	}

	listCmd := &cobra.Command{
		Use:   "list",
		Short: "List all registered projects",
		RunE: func(cmd *cobra.Command, args []string) error {
			projects := a.Project.ListProjects()
			if len(projects) == 0 {
				fmt.Println(styles.Dimmed.Render("No projects registered"))
				return nil
			}
			rows := make([][]string, 0, len(projects))
			for name, entry := range projects {
				rows = append(rows, []string{name, entry.Path, entry.Group})
			}
			fmt.Println(components.RenderTable([]string{"Name", "Path", "Group"}, rows))
			return nil
		},
	}

	cmd.AddCommand(
		addPathCmd,
		removePathCmd,
		listPathsCmd,
		addProjectCmd,
		removeProjectCmd,
		listCmd,
	)

	return cmd
}
