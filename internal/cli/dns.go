package cli

import (
	"context"
	"fmt"
	"io"
	"net"
	"os"
	"time"

	"github.com/christhomas/docker-dev-tools/internal/app"
	"github.com/christhomas/docker-dev-tools/internal/tui/components"
	"github.com/christhomas/docker-dev-tools/internal/tui/styles"
	"github.com/spf13/cobra"
)

func newDNSCmd(a *app.App) *cobra.Command {
	cmd := &cobra.Command{
		Use:   "dns",
		Short: "Manage the local DNS server",
		Long:  "Control the dnsmasq-based local DNS server for resolving development domains.",
	}

	startCmd := &cobra.Command{
		Use:   "start",
		Short: "Start the DNS server container and enable resolver",
		RunE: func(cmd *cobra.Command, args []string) error {
			pull, _ := cmd.Flags().GetBool("pull")
			return dnsStart(context.Background(), a, pull)
		},
	}
	startCmd.Flags().Bool("pull", false, "pull latest image before starting")

	restartCmd := &cobra.Command{
		Use:   "restart",
		Short: "Restart the DNS server container",
		RunE: func(cmd *cobra.Command, args []string) error {
			pull, _ := cmd.Flags().GetBool("pull")
			ctx := context.Background()
			_ = a.DNS.Stop(ctx)
			return dnsStart(ctx, a, pull)
		},
	}
	restartCmd.Flags().Bool("pull", false, "pull latest image before restarting")

	cmd.AddCommand(
		startCmd,
		&cobra.Command{
			Use:   "stop",
			Short: "Stop the DNS server container and disable resolver",
			RunE: func(cmd *cobra.Command, args []string) error {
				ctx := context.Background()
				if err := a.DNS.Stop(ctx); err != nil {
					return err
				}
				fmt.Println(styles.SuccessStyle.Render("DNS server stopped"))
				return nil
			},
		},
		restartCmd,
		&cobra.Command{
			Use:   "enable",
			Short: "Enable system DNS resolution (without restarting container)",
			RunE: func(cmd *cobra.Command, args []string) error {
				ctx := context.Background()
				if err := a.DNS.Enable(ctx); err != nil {
					return err
				}
				fmt.Println(styles.SuccessStyle.Render("DNS resolution enabled"))
				return nil
			},
		},
		&cobra.Command{
			Use:   "disable",
			Short: "Disable system DNS resolution (without stopping container)",
			RunE: func(cmd *cobra.Command, args []string) error {
				ctx := context.Background()
				if err := a.DNS.Disable(ctx); err != nil {
					return err
				}
				fmt.Println(styles.SuccessStyle.Render("DNS resolution disabled"))
				return nil
			},
		},
		&cobra.Command{
			Use:   "refresh",
			Short: "Refresh DNS: toggle resolver and reset upstreams",
			RunE: func(cmd *cobra.Command, args []string) error {
				ctx := context.Background()
				if err := a.DNS.Refresh(ctx); err != nil {
					return err
				}
				fmt.Println(styles.SuccessStyle.Render("DNS refreshed"))
				return nil
			},
		},
		&cobra.Command{
			Use:   "add-tld <tld>",
			Short: "Add a TLD for wildcard DNS resolution (e.g. \"develop\" -> *.develop)",
			Args:  cobra.ExactArgs(1),
			RunE: func(cmd *cobra.Command, args []string) error {
				ctx := context.Background()
				if err := a.DNS.AddTLD(ctx, args[0]); err != nil {
					return err
				}
				fmt.Println(styles.SuccessStyle.Render(fmt.Sprintf("TLD .%s added (*.%s will resolve)", args[0], args[0])))
				return nil
			},
		},
		&cobra.Command{
			Use:   "remove-tld <tld>",
			Short: "Remove a TLD from wildcard DNS resolution",
			Args:  cobra.ExactArgs(1),
			RunE: func(cmd *cobra.Command, args []string) error {
				ctx := context.Background()
				if err := a.DNS.RemoveTLD(ctx, args[0]); err != nil {
					return err
				}
				fmt.Println(styles.SuccessStyle.Render(fmt.Sprintf("TLD .%s removed", args[0])))
				return nil
			},
		},
		&cobra.Command{
			Use:   "add-upstream <address>",
			Short: "Add an upstream DNS server",
			Args:  cobra.ExactArgs(1),
			RunE: func(cmd *cobra.Command, args []string) error {
				ctx := context.Background()
				if err := a.DNS.AddUpstream(ctx, args[0]); err != nil {
					return err
				}
				fmt.Println(styles.SuccessStyle.Render(fmt.Sprintf("Upstream %s added", args[0])))
				return nil
			},
		},
		&cobra.Command{
			Use:   "remove-upstream <address>",
			Short: "Remove an upstream DNS server",
			Args:  cobra.ExactArgs(1),
			RunE: func(cmd *cobra.Command, args []string) error {
				ctx := context.Background()
				if err := a.DNS.RemoveUpstream(ctx, args[0]); err != nil {
					return err
				}
				fmt.Println(styles.SuccessStyle.Render(fmt.Sprintf("Upstream %s removed", args[0])))
				return nil
			},
		},
		&cobra.Command{
			Use:   "ping",
			Short: "Ping all configured domains and upstreams",
			RunE:  dnsPingCmd(a),
		},
		&cobra.Command{
			Use:   "config",
			Short: "Dump all dnsmasq configuration from the container",
			RunE: func(cmd *cobra.Command, args []string) error {
				ctx := context.Background()
				running, _ := a.DNS.IsRunning(ctx)
				if !running {
					return fmt.Errorf("DNS server is not running")
				}
				output, err := a.DNS.DumpConfig(ctx)
				if err != nil {
					return err
				}
				fmt.Println(styles.Dimmed.Render(output))
				return nil
			},
		},
		&cobra.Command{
			Use:   "container-name [name]",
			Short: "Get or set the DNS container name",
			Args:  cobra.MaximumNArgs(1),
			RunE: func(cmd *cobra.Command, args []string) error {
				if len(args) == 0 {
					fmt.Println(a.DNS.ContainerName())
					return nil
				}
				if err := a.DNS.SetContainerName(args[0]); err != nil {
					return err
				}
				fmt.Println(styles.SuccessStyle.Render(fmt.Sprintf("Container name set to %q. Restart DNS to apply.", args[0])))
				return nil
			},
		},
		&cobra.Command{
			Use:   "docker-image [image]",
			Short: "Get or set the DNS Docker image",
			Args:  cobra.MaximumNArgs(1),
			RunE: func(cmd *cobra.Command, args []string) error {
				if len(args) == 0 {
					fmt.Println(a.DNS.Image())
					return nil
				}
				if err := a.DNS.SetImage(args[0]); err != nil {
					return err
				}
				fmt.Println(styles.SuccessStyle.Render(fmt.Sprintf("Docker image set to %q. Restart DNS to apply.", args[0])))
				return nil
			},
		},
		newDNSLogsCmd(a),
	)

	return cmd
}

func dnsStart(ctx context.Context, a *app.App, pull bool) error {
	steps := components.NewSteps("📡", "Starting DNS Server")

	if err := steps.Run("Ensure DNS image available", func() error {
		return a.DNS.EnsureImage(ctx, pull, os.Stderr)
	}); err != nil {
		steps.Done()
		return err
	}
	if id := a.Docker.ImageID(ctx, a.DNS.Image()); id != "" {
		steps.Info(fmt.Sprintf("%s (%s)", a.DNS.Image(), id))
	}

	steps.Run("Clean up existing container", func() error {
		a.DNS.Cleanup(ctx)
		return nil
	})

	var aliasExisted bool
	if err := steps.Run("Configure IP alias ("+a.Config.IPAddress+")", func() error {
		existed, err := a.DNS.EnsureIPAlias()
		aliasExisted = existed
		return err
	}); err != nil {
		steps.Done()
		return err
	}
	if aliasExisted {
		steps.Info("IP alias already active")
	}

	port := a.DNS.Port()
	if err := steps.Run(fmt.Sprintf("Check port %d availability", port), func() error {
		if err := a.DNS.CheckPort(); err != nil {
			return fmt.Errorf("%w\n    Hint: run  sudo lsof +c0 -i :%d  to find the conflicting process", err, port)
		}
		return nil
	}); err != nil {
		steps.Done()
		return err
	}

	if err := steps.Run("Start DNS container", func() error {
		return a.DNS.StartContainer(ctx)
	}); err != nil {
		steps.Done()
		return err
	}

	if err := steps.Run("Configure upstream DNS servers", func() error {
		return a.DNS.ConfigureUpstreams(ctx)
	}); err != nil {
		steps.Done()
		return err
	}

	steps.Run("Configure wildcard TLDs", func() error {
		a.DNS.ConfigureTLDs(ctx)
		a.DNS.Reload(ctx)
		return nil
	})

	if err := steps.Run("Enable system resolvers", func() error {
		return a.DNS.EnableResolvers()
	}); err != nil {
		steps.Done()
		return err
	}

	// Show summary of what was configured.
	for _, tld := range a.DNS.ConfiguredTLDs() {
		steps.Info(fmt.Sprintf("*.%s → %s", tld, a.Config.IPAddress))
	}

	steps.Done()
	return nil
}

func dnsPingCmd(a *app.App) func(cmd *cobra.Command, args []string) error {
	return func(cmd *cobra.Command, args []string) error {
		ctx := context.Background()

		// Ping upstreams.
		if running, _ := a.DNS.IsRunning(ctx); running {
			upstreams, _ := a.DNS.ListUpstreams(ctx)
			for _, addr := range upstreams {
				printPingResult(addr)
			}
		}

		// Ping standard targets.
		printPingResult("127.0.0.1")
		printPingResult("google.com")

		// Ping a test host under each configured TLD.
		for _, tld := range a.DNS.ConfiguredTLDs() {
			printPingResult("test." + tld)
		}

		return nil
	}
}

func printPingResult(target string) {
	status := quickPing(target)
	fmt.Printf("  %s  %s\n", styles.Value.Render(fmt.Sprintf("%-30s", target)), status)
}

func quickPing(target string) string {
	start := time.Now()
	conn, err := net.DialTimeout("tcp", target+":80", 2*time.Second)
	if err != nil {
		// Try resolving DNS only.
		_, lookupErr := net.LookupHost(target)
		if lookupErr != nil {
			return styles.ErrorStyle.Render("could not resolve")
		}
		return styles.WarningStyle.Render("resolved but port 80 unreachable")
	}
	conn.Close()
	elapsed := time.Since(start)
	return styles.SuccessStyle.Render(fmt.Sprintf("ok (%s)", elapsed.Round(time.Millisecond)))
}

func newDNSLogsCmd(a *app.App) *cobra.Command {
	cmd := &cobra.Command{
		Use:   "logs",
		Short: "Show DNS server logs",
		RunE: func(cmd *cobra.Command, args []string) error {
			follow, _ := cmd.Flags().GetBool("follow")
			ctx := context.Background()
			reader, err := a.DNS.Logs(ctx, follow)
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
