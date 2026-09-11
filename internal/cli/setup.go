package cli

import (
	"fmt"

	"github.com/christhomas/docker-dev-tools/internal/app"
	"github.com/christhomas/docker-dev-tools/internal/config"
	"github.com/christhomas/docker-dev-tools/internal/tui/components"
	"github.com/christhomas/docker-dev-tools/internal/tui/styles"
	"github.com/spf13/cobra"
)

func newInstallCmd(a *app.App) *cobra.Command {
	return &cobra.Command{
		Use:   "install",
		Short: "Interactive setup: configure IP, DNS, and install system services",
		RunE: func(cmd *cobra.Command, args []string) error {
			// Set the config path display function for the wizard.
			components.SetConfigPathDisplay(config.ConfigPath)

			// Run the interactive wizard.
			result, err := components.RunInstallWizard(
				a.Config.IPAddress,
				a.Config.DNS.TLDs,
			)
			if err != nil {
				return fmt.Errorf("install wizard: %w", err)
			}
			if result.Cancelled {
				fmt.Println(styles.WarningStyle.Render("Installation cancelled"))
				return nil
			}

			// Apply wizard values to config.
			a.Config.IPAddress = result.IPAddress
			a.Config.DNS.TLDs = result.TLDs

			// 1. Save config.
			if err := a.Config.Save(); err != nil {
				return fmt.Errorf("saving config: %w", err)
			}
			fmt.Printf("  %s %s\n",
				styles.Label.Render("Config:"),
				styles.Value.Render(a.Config.Path()),
			)

			// 2. Install persistent IP alias.
			ip := a.Config.IPAddress
			installed, _ := a.Platform.IsIPAliasInstalled(ip)
			if installed {
				fmt.Printf("  %s %s\n",
					styles.Label.Render("IP alias:"),
					styles.Value.Render(ip+" (already installed)"),
				)
			} else {
				if err := a.Platform.InstallIPAlias(ip); err != nil {
					return fmt.Errorf("installing IP alias: %w", err)
				}
				fmt.Printf("  %s %s\n",
					styles.Label.Render("IP alias:"),
					styles.Value.Render(ip+" (installed)"),
				)
			}

			// 3. Install DNS resolver files for configured TLDs.
			port := a.Config.DNS.PortOrDefault()
			for _, tld := range a.Config.DNS.TLDs {
				changed, err := a.Platform.EnableDNS(tld, ip, port)
				if err != nil {
					return fmt.Errorf("enabling DNS for .%s: %w", tld, err)
				}
				status := "already configured"
				if changed {
					status = "installed"
				}
				fmt.Printf("  %s %s\n",
					styles.Label.Render(fmt.Sprintf("DNS .%s:", tld)),
					styles.Value.Render(status),
				)
			}

			// Flush DNS if any TLDs were configured.
			if len(a.Config.DNS.TLDs) > 0 {
				_ = a.Platform.FlushDNS()
			}

			fmt.Println(styles.SuccessStyle.Render("ddt installed successfully"))
			return nil
		},
	}
}

func newUninstallCmd(a *app.App) *cobra.Command {
	return &cobra.Command{
		Use:   "uninstall",
		Short: "Remove ddt: IP alias service and DNS resolvers",
		RunE: func(cmd *cobra.Command, args []string) error {
			ip := a.Config.IPAddress

			// 1. Remove persistent IP alias.
			installed, _ := a.Platform.IsIPAliasInstalled(ip)
			if installed {
				if err := a.Platform.UninstallIPAlias(ip); err != nil {
					return fmt.Errorf("uninstalling IP alias: %w", err)
				}
				fmt.Printf("  %s %s\n",
					styles.Label.Render("IP alias:"),
					styles.Value.Render(ip+" (removed)"),
				)
			} else {
				fmt.Printf("  %s %s\n",
					styles.Label.Render("IP alias:"),
					styles.Value.Render("not installed"),
				)
			}

			// 2. Remove DNS resolver files for configured TLDs.
			for _, tld := range a.Config.DNS.TLDs {
				if err := a.Platform.DisableDNS(tld); err != nil {
					return fmt.Errorf("disabling DNS for .%s: %w", tld, err)
				}
				fmt.Printf("  %s %s\n",
					styles.Label.Render(fmt.Sprintf("DNS .%s:", tld)),
					styles.Value.Render("removed"),
				)
			}

			if len(a.Config.DNS.TLDs) > 0 {
				_ = a.Platform.FlushDNS()
			}

			fmt.Println(styles.SuccessStyle.Render("ddt uninstalled successfully"))
			return nil
		},
	}
}
