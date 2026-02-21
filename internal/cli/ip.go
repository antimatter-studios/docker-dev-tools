package cli

import (
	"fmt"
	"strings"

	"github.com/charmbracelet/lipgloss"
	"github.com/christhomas/docker-dev-tools/internal/app"
	"github.com/christhomas/docker-dev-tools/internal/tui/components"
	"github.com/christhomas/docker-dev-tools/internal/tui/styles"
	"github.com/spf13/cobra"
)

func newIPCmd(a *app.App) *cobra.Command {
	cmd := &cobra.Command{
		Use:   "ip",
		Short: "Manage the local IP alias for Docker development",
		Long:  "Configure and manage the IP address alias used for host-to-container communication (e.g., XDebug callbacks).",
	}

	cmd.AddCommand(
		newIPGetCmd(a),
		newIPSetCmd(a),
		newIPAddCmd(a),
		newIPRemoveCmd(a),
		newIPStatusCmd(a),
		newIPPingCmd(a),
	)

	return cmd
}

func newIPGetCmd(a *app.App) *cobra.Command {
	return &cobra.Command{
		Use:   "get",
		Short: "Show the configured IP address",
		RunE: func(cmd *cobra.Command, args []string) error {
			ip := a.IP.Get()
			active, _ := a.IP.IsActive()

			var b strings.Builder
			b.WriteString(styles.Banner("🌐", "IP Alias"))
			b.WriteString("\n\n")
			b.WriteString(styles.KeyValueBright("Address", ip))
			b.WriteString("\n")
			b.WriteString(styles.KeyValue("Status", styles.StatusBadge(active)))
			b.WriteString("\n")
			b.WriteString(styles.KeyValue("Interface", "lo0"))
			b.WriteString("\n")
			b.WriteString(styles.KeyValue("Netmask", "255.255.255.255"))

			fmt.Println(styles.Card.Render(b.String()))
			return nil
		},
	}
}

func newIPSetCmd(a *app.App) *cobra.Command {
	return &cobra.Command{
		Use:   "set <address>",
		Short: "Set the IP address",
		Args:  cobra.ExactArgs(1),
		RunE: func(cmd *cobra.Command, args []string) error {
			oldIP := a.IP.Get()
			newIP := args[0]

			if err := a.IP.Set(newIP); err != nil {
				fmt.Println(styles.CardError.Render(
					styles.ErrorMarker(fmt.Sprintf("Invalid IP address: %s", newIP)) + "\n" +
						lipgloss.NewStyle().PaddingLeft(2).Foreground(styles.Subtle).
							Render("Expected format: X.X.X.X (e.g., 10.254.254.254)"),
				))
				return err
			}

			var b strings.Builder
			b.WriteString(styles.SuccessMarker("IP address updated"))
			b.WriteString("\n\n")

			// Show before → after.
			oldStyle := lipgloss.NewStyle().Foreground(styles.Subtle).Strikethrough(true)
			newStyle := lipgloss.NewStyle().Foreground(styles.Success).Bold(true)
			arrow := lipgloss.NewStyle().Foreground(styles.Muted).Render(" → ")

			b.WriteString(styles.Label.Render("Address"))
			b.WriteString("  ")
			b.WriteString(oldStyle.Render(oldIP))
			b.WriteString(arrow)
			b.WriteString(newStyle.Render(newIP))

			fmt.Println(styles.CardSuccess.Render(b.String()))
			return nil
		},
	}
}

func newIPAddCmd(a *app.App) *cobra.Command {
	return &cobra.Command{
		Use:   "add",
		Short: "Add the IP alias to the system",
		RunE: func(cmd *cobra.Command, args []string) error {
			ip := a.IP.Get()

			// Check if already active.
			active, _ := a.IP.IsActive()
			if active {
				fmt.Println(styles.Card.Render(
					styles.InfoMarker(fmt.Sprintf("IP alias %s is already active", ip)),
				))
				return nil
			}

			return components.RunOperation(
				fmt.Sprintf("Adding IP alias %s to lo0", ip),
				"requires sudo — you may be prompted for your password",
				func() error { return a.IP.Add() },
			)
		},
	}
}

func newIPRemoveCmd(a *app.App) *cobra.Command {
	return &cobra.Command{
		Use:   "remove",
		Short: "Remove the IP alias from the system",
		RunE: func(cmd *cobra.Command, args []string) error {
			ip := a.IP.Get()

			// Check if already inactive.
			active, _ := a.IP.IsActive()
			if !active {
				fmt.Println(styles.Card.Render(
					styles.InfoMarker(fmt.Sprintf("IP alias %s is not currently active", ip)),
				))
				return nil
			}

			return components.RunOperation(
				fmt.Sprintf("Removing IP alias %s from lo0", ip),
				"requires sudo — you may be prompted for your password",
				func() error { return a.IP.Remove() },
			)
		},
	}
}

func newIPStatusCmd(a *app.App) *cobra.Command {
	return &cobra.Command{
		Use:   "status",
		Short: "Show detailed IP alias status",
		RunE: func(cmd *cobra.Command, args []string) error {
			ip := a.IP.Get()
			active, err := a.IP.IsActive()

			var b strings.Builder
			b.WriteString(styles.Banner("🌐", "IP Alias Status"))
			b.WriteString("\n\n")

			b.WriteString(styles.KeyValueBright("Address", ip))
			b.WriteString("\n")
			b.WriteString(styles.KeyValue("Interface", "lo0"))
			b.WriteString("\n")
			b.WriteString(styles.KeyValue("Netmask", "255.255.255.255"))
			b.WriteString("\n")
			b.WriteString(styles.KeyValue("Platform", a.Platform.Name()))
			b.WriteString("\n\n")

			if err != nil {
				b.WriteString(styles.ErrorMarker(fmt.Sprintf("Could not check status: %v", err)))
			} else if active {
				b.WriteString(styles.SuccessMarker("Alias is active and bound to the loopback interface"))
			} else {
				b.WriteString(styles.WarnMarker("Alias is not active — run `ddt ip add` to create it"))
			}

			cardStyle := styles.Card
			if active {
				cardStyle = styles.CardActive
			}
			fmt.Println(cardStyle.Render(b.String()))
			return nil
		},
	}
}

func newIPPingCmd(a *app.App) *cobra.Command {
	cmd := &cobra.Command{
		Use:   "ping",
		Short: "Test connectivity to the configured IP with a live display",
		RunE: func(cmd *cobra.Command, args []string) error {
			count, _ := cmd.Flags().GetInt("count")
			ip := a.IP.Get()

			// Check if alias is active first.
			active, _ := a.IP.IsActive()
			if !active {
				fmt.Println(styles.CardError.Render(
					styles.WarnMarker(fmt.Sprintf("IP alias %s is not active", ip)) + "\n" +
						lipgloss.NewStyle().PaddingLeft(2).Foreground(styles.Subtle).
							Render("Run `ddt ip add` first, then try again"),
				))
				return nil
			}

			_, err := components.RunPing(ip, count)
			return err
		},
	}
	cmd.Flags().IntP("count", "c", 10, "number of pings to send")
	return cmd
}
