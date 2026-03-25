package cli

import (
	"context"
	"fmt"
	"io"
	"os"

	"github.com/christhomas/docker-dev-tools/internal/app"
	"github.com/christhomas/docker-dev-tools/internal/tui/components"
	"github.com/christhomas/docker-dev-tools/internal/tui/styles"
	"github.com/spf13/cobra"
)

func newProxyCmd(a *app.App) *cobra.Command {
	cmd := &cobra.Command{
		Use:   "proxy",
		Short: "Manage the reverse proxy",
		Long:  "Control the NGINX reverse proxy that routes traffic to Docker containers based on VIRTUAL_HOST.",
	}

	startCmd := &cobra.Command{
		Use:   "start",
		Short: "Start the reverse proxy and config-gen containers",
		RunE: func(cmd *cobra.Command, args []string) error {
			pull, _ := cmd.Flags().GetBool("pull")
			return proxyStart(context.Background(), a, pull)
		},
	}
	startCmd.Flags().Bool("pull", false, "pull latest images before starting")

	restartCmd := &cobra.Command{
		Use:   "restart",
		Short: "Restart the reverse proxy and config-gen containers",
		RunE: func(cmd *cobra.Command, args []string) error {
			pull, _ := cmd.Flags().GetBool("pull")
			ctx := context.Background()

			steps := components.NewSteps("🔀", "Stopping Reverse Proxy")
			steps.Run("Stop proxy container", func() error {
				res, _ := a.Proxy.StopProxy(ctx)
				if !res.Found {
					steps.Info("Proxy container not found")
				} else if !res.WasRunning {
					steps.Info("Proxy already stopped")
				}
				return nil
			})
			steps.Run("Stop config-gen container", func() error {
				res, _ := a.Proxy.StopConfigGen(ctx)
				if !res.Found {
					steps.Info("Config-gen container not found")
				} else if !res.WasRunning {
					steps.Info("Config-gen already stopped")
				}
				return nil
			})
			steps.Done()

			return proxyStart(ctx, a, pull)
		},
	}
	restartCmd.Flags().Bool("pull", false, "pull latest images before restarting")

	stopCmd := &cobra.Command{
		Use:   "stop",
		Short: "Stop the reverse proxy and config-gen containers",
		RunE: func(cmd *cobra.Command, args []string) error {
			ctx := context.Background()
			steps := components.NewSteps("🔀", "Stopping Reverse Proxy")

			steps.Run("Stop proxy container", func() error {
				res, _ := a.Proxy.StopProxy(ctx)
				if !res.Found {
					steps.Info("Proxy container not found")
				} else if !res.WasRunning {
					steps.Info("Proxy already stopped")
				}
				return nil
			})
			steps.Run("Stop config-gen container", func() error {
				res, _ := a.Proxy.StopConfigGen(ctx)
				if !res.Found {
					steps.Info("Config-gen container not found")
				} else if !res.WasRunning {
					steps.Info("Config-gen already stopped")
				}
				return nil
			})

			steps.Done()
			return nil
		},
	}

	cmd.AddCommand(
		newProxyListCmd(a),
		startCmd,
		stopCmd,
		restartCmd,
		&cobra.Command{
			Use:   "reload",
			Short: "Reload the NGINX configuration",
			RunE: func(cmd *cobra.Command, args []string) error {
				ctx := context.Background()
				if err := a.Proxy.Reload(ctx); err != nil {
					return err
				}
				fmt.Println(styles.SuccessStyle.Render("Proxy configuration reloaded"))
				return nil
			},
		},
		&cobra.Command{
			Use:   "nginx-config",
			Short: "Show the generated NGINX configuration",
			RunE: func(cmd *cobra.Command, args []string) error {
				ctx := context.Background()
				running, _ := a.Proxy.IsRunning(ctx)
				if !running {
					return fmt.Errorf("proxy is not running")
				}
				config, err := a.Proxy.NginxConfig(ctx)
				if err != nil {
					return err
				}
				fmt.Println(styles.Dimmed.Render(config))
				return nil
			},
		},
		&cobra.Command{
			Use:   "container-name [name]",
			Short: "Get or set the proxy container name",
			Args:  cobra.MaximumNArgs(1),
			RunE: func(cmd *cobra.Command, args []string) error {
				if len(args) == 0 {
					fmt.Println(a.Proxy.ContainerName())
					return nil
				}
				if err := a.Proxy.SetContainerName(args[0]); err != nil {
					return err
				}
				fmt.Println(styles.SuccessStyle.Render(fmt.Sprintf("Container name set to %q. Restart proxy to apply.", args[0])))
				return nil
			},
		},
		&cobra.Command{
			Use:   "docker-image [image]",
			Short: "Get or set the proxy Docker image",
			Args:  cobra.MaximumNArgs(1),
			RunE: func(cmd *cobra.Command, args []string) error {
				if len(args) == 0 {
					fmt.Println(a.Proxy.Image())
					return nil
				}
				if err := a.Proxy.SetImage(args[0]); err != nil {
					return err
				}
				fmt.Println(styles.SuccessStyle.Render(fmt.Sprintf("Docker image set to %q. Restart proxy to apply.", args[0])))
				return nil
			},
		},
		newProxyLogsCmd(a),
	)

	return cmd
}

func newProxyListCmd(a *app.App) *cobra.Command {
	return &cobra.Command{
		Use:   "list",
		Short: "List proxied services and TCP/UDP sidecars",
		RunE: func(cmd *cobra.Command, args []string) error {
			ctx := context.Background()

			running, _ := a.Proxy.IsRunning(ctx)

			fmt.Println(components.RenderProxyServiceCard(a, ctx, running))

			if !running {
				return fmt.Errorf("proxy is not running")
			}

			entries, _ := a.Proxy.Status(ctx)
			sidecars, _ := a.Proxy.SidecarStatus(ctx)

			if len(entries) == 0 && len(sidecars) == 0 {
				fmt.Println()
				fmt.Println(styles.InfoStyle.Render("No proxied services found"))
				return nil
			}

			if len(entries) > 0 {
				fmt.Println()
				fmt.Println(components.RenderProxyServicesTable(entries))
			}

			if len(sidecars) > 0 {
				fmt.Println()
				fmt.Println(components.RenderSidecarServicesTable(sidecars))
			}

			return nil
		},
	}
}

func newProxyLogsCmd(a *app.App) *cobra.Command {
	cmd := &cobra.Command{
		Use:   "logs",
		Short: "Show proxy logs",
		RunE: func(cmd *cobra.Command, args []string) error {
			follow, _ := cmd.Flags().GetBool("follow")
			ctx := context.Background()
			reader, err := a.Proxy.Logs(ctx, follow)
			if err != nil {
				return err
			}
			defer reader.Close()
			_, err = io.Copy(os.Stdout, reader)
			return err
		},
	}
	cmd.Flags().BoolP("follow", "f", false, "follow log output")
	return cmd
}

func proxyStart(ctx context.Context, a *app.App, pull bool) error {
	steps := components.NewSteps("🔀", "Starting Reverse Proxy")

	if err := steps.Run("Ensure config-gen image available", func() error {
		return a.Proxy.EnsureConfigGenImage(ctx, pull, os.Stderr)
	}); err != nil {
		steps.Done()
		return err
	}
	if meta := a.Docker.ImageInfo(ctx, a.Proxy.ConfigGenImage()); meta != nil {
		steps.Info(meta.Summary(a.Proxy.ConfigGenImage()))
	}

	if err := steps.Run("Ensure proxy image available", func() error {
		return a.Proxy.EnsureProxyImage(ctx, pull, os.Stderr)
	}); err != nil {
		steps.Done()
		return err
	}
	if meta := a.Docker.ImageInfo(ctx, a.Proxy.Image()); meta != nil {
		steps.Info(meta.Summary(a.Proxy.Image()))
	}

	steps.Run("Clean up existing containers", func() error {
		a.Proxy.Cleanup(ctx)
		return nil
	})

	if err := steps.Run("Start config-gen container", func() error {
		return a.Proxy.StartConfigGenContainer(ctx)
	}); err != nil {
		steps.Done()
		return err
	}

	if err := steps.Run("Start proxy container", func() error {
		return a.Proxy.StartProxyContainer(ctx)
	}); err != nil {
		steps.Done()
		return err
	}

	steps.Done()
	return nil
}
