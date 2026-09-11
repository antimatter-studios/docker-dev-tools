package cli

import (
	"errors"
	"fmt"
	"io/fs"

	"github.com/christhomas/docker-dev-tools/internal/app"
	"github.com/christhomas/docker-dev-tools/internal/ca"
	"github.com/christhomas/docker-dev-tools/internal/config"
	"github.com/christhomas/docker-dev-tools/internal/tui/styles"
	"github.com/spf13/cobra"
)

func newCACmd(a *app.App) *cobra.Command {
	cmd := &cobra.Command{
		Use:   "ca",
		Short: "Manage the local certificate authority behind HTTPS",
		Long: `The proxy serves every host over HTTPS with certificates from a local certificate
authority. The CA is restricted to the TLDs ddt resolves, so it cannot vouch for real
sites, and 'ddt install' or 'ddt ca trust' makes this machine trust it.`,
	}

	cmd.AddCommand(
		&cobra.Command{
			Use:   "trust",
			Short: "Create the CA if needed and trust it on this machine",
			RunE: func(cmd *cobra.Command, args []string) error {
				return installCA(a)
			},
		},
		&cobra.Command{
			Use:   "path",
			Short: "Print the CA certificate's path",
			RunE: func(cmd *cobra.Command, args []string) error {
				authority, err := loadCA()
				if err != nil {
					return err
				}
				fmt.Println(authority.CertPath())
				return nil
			},
		},
		&cobra.Command{
			Use:   "simulators",
			Short: "Trust the CA in every booted iOS simulator",
			RunE: func(cmd *cobra.Command, args []string) error {
				authority, err := loadCA()
				if err != nil {
					return err
				}
				n, err := a.Platform.TrustCAInSimulators(authority.CertPath())
				if err != nil {
					return err
				}
				printStatus("iOS simulators:", fmt.Sprintf("%d trusted", n))
				return nil
			},
		},
	)
	return cmd
}

func loadCA() (*ca.Authority, error) {
	authority, err := ca.Load(config.CADir())
	if errors.Is(err, fs.ErrNotExist) {
		return nil, errors.New("there is no CA yet: run 'ddt ca trust'")
	}
	return authority, err
}

// installCA makes sure the CA exists for the configured TLDs and is trusted on this
// machine. 'ddt install' and 'ddt ca trust' both do this.
func installCA(a *app.App) error {
	tlds := a.Config.DNS.TLDs
	if len(tlds) == 0 {
		printStatus("HTTPS CA:", "skipped: no TLDs are configured for it to cover")
		return nil
	}

	dir := config.CADir()
	authority, err := ca.Load(dir)
	switch {
	case errors.Is(err, fs.ErrNotExist):
		authority = nil
	case err != nil:
		return fmt.Errorf("reading the CA: %w", err)
	case !authority.Covers(tlds):
		// The TLDs changed. Stop trusting the old CA before replacing it, while its
		// certificate is still on disk to identify it by.
		if trusted, _ := a.Platform.IsCATrusted(authority.CertPath()); trusted {
			if err := a.Platform.UntrustCA(authority.CertPath()); err != nil {
				return fmt.Errorf("removing the previous CA: %w", err)
			}
		}
		authority = nil
	}

	created := false
	if authority == nil {
		if authority, err = ca.Create(dir, tlds); err != nil {
			return fmt.Errorf("creating the CA: %w", err)
		}
		created = true
	}

	trusted, err := a.Platform.IsCATrusted(authority.CertPath())
	if err != nil {
		return fmt.Errorf("checking whether the CA is trusted: %w", err)
	}
	if !trusted {
		if err := a.Platform.TrustCA(authority.CertPath()); err != nil {
			return fmt.Errorf("trusting the CA: %w", err)
		}
	}

	status := "already trusted"
	switch {
	case created:
		status = "created and trusted"
	case !trusted:
		status = "trusted"
	}
	printStatus("HTTPS CA:", authority.CertPath()+" ("+status+")")

	// iOS simulators keep their own trust store. Best effort: usually none is booted,
	// and 'ddt ca simulators' does it on demand.
	if n, err := a.Platform.TrustCAInSimulators(authority.CertPath()); err == nil && n > 0 {
		printStatus("iOS simulators:", fmt.Sprintf("%d trusted", n))
	}

	if created {
		// config-gen only reads the CA when it starts.
		fmt.Println(styles.WarningStyle.Render("Restart the proxy to serve HTTPS: ddt proxy restart"))
	}
	return nil
}

// uninstallCA stops this machine trusting the CA. The files stay, like the rest of
// ddt's configuration.
func uninstallCA(a *app.App) error {
	authority, err := ca.Load(config.CADir())
	if errors.Is(err, fs.ErrNotExist) {
		printStatus("HTTPS CA:", "not installed")
		return nil
	}
	if err != nil {
		return fmt.Errorf("reading the CA: %w", err)
	}
	trusted, err := a.Platform.IsCATrusted(authority.CertPath())
	if err != nil {
		return fmt.Errorf("checking whether the CA is trusted: %w", err)
	}
	if trusted {
		if err := a.Platform.UntrustCA(authority.CertPath()); err != nil {
			return fmt.Errorf("removing the CA from the trust store: %w", err)
		}
	}
	printStatus("HTTPS CA:", "removed from the trust store")
	return nil
}

func printStatus(label, value string) {
	fmt.Printf("  %s %s\n", styles.Label.Render(label), styles.Value.Render(value))
}
