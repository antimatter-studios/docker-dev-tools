package cli

import (
	"context"
	"fmt"

	"github.com/christhomas/docker-dev-tools/internal/app"
	"github.com/christhomas/docker-dev-tools/internal/tui/components"
	"github.com/christhomas/docker-dev-tools/internal/tui/styles"
	"github.com/spf13/cobra"
)

func newStartCmd(a *app.App) *cobra.Command {
	cmd := &cobra.Command{
		Use:   "start",
		Short: "Start DNS and proxy services",
		Long:  "Start both the local DNS server and the reverse proxy in the correct order.",
		RunE: func(cmd *cobra.Command, args []string) error {
			pull, _ := cmd.Flags().GetBool("pull")
			ctx := context.Background()
			if err := dnsStart(ctx, a, pull); err != nil {
				return fmt.Errorf("DNS: %w", err)
			}
			if err := proxyStart(ctx, a, pull); err != nil {
				return fmt.Errorf("proxy: %w", err)
			}
			return nil
		},
	}
	cmd.Flags().Bool("pull", false, "pull latest images before starting")
	return cmd
}

func newRestartCmd(a *app.App) *cobra.Command {
	cmd := &cobra.Command{
		Use:   "restart",
		Short: "Restart DNS and proxy services",
		Long:  "Stop then start both the local DNS server and the reverse proxy.",
		RunE: func(cmd *cobra.Command, args []string) error {
			pull, _ := cmd.Flags().GetBool("pull")
			ctx := context.Background()

			// Stop proxy first, then DNS.
			steps := components.NewSteps("🔀", "Stopping Reverse Proxy")
			steps.Run("Stop proxy container", func() error {
				a.Proxy.StopProxy(ctx)
				return nil
			})
			steps.Run("Stop config-gen container", func() error {
				a.Proxy.StopConfigGen(ctx)
				return nil
			})
			steps.Done()

			_ = a.DNS.Stop(ctx)
			fmt.Println(styles.SuccessStyle.Render("Services stopped"))

			// Start DNS then proxy.
			if err := dnsStart(ctx, a, pull); err != nil {
				return fmt.Errorf("DNS: %w", err)
			}
			if err := proxyStart(ctx, a, pull); err != nil {
				return fmt.Errorf("proxy: %w", err)
			}
			return nil
		},
	}
	cmd.Flags().Bool("pull", false, "pull latest images before starting")
	return cmd
}

func newStopCmd(a *app.App) *cobra.Command {
	return &cobra.Command{
		Use:   "stop",
		Short: "Stop DNS and proxy services",
		Long:  "Stop both the reverse proxy and the local DNS server.",
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

			if err := a.DNS.Stop(ctx); err != nil {
				return err
			}
			fmt.Println(styles.SuccessStyle.Render("DNS server stopped"))
			return nil
		},
	}
}
