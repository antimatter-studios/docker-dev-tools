package cli

import (
	"errors"
	"fmt"
	"io/fs"

	"github.com/christhomas/docker-dev-tools/internal/app"
	"github.com/christhomas/docker-dev-tools/internal/ca"
	"github.com/christhomas/docker-dev-tools/internal/config"
	"github.com/spf13/cobra"
)

func newCACmd(_ *app.App) *cobra.Command {
	cmd := &cobra.Command{
		Use:   "ca",
		Short: "The local certificate authority behind the proxy's HTTPS",
		Long: `The proxy serves every host over HTTPS with certificates from a local certificate
authority, restricted to the TLDs ddt resolves. ddt creates it when the proxy starts
and never adds it to this machine's trust store: software that should verify the
proxy's certificates trusts this CA itself, e.g. curl --cacert "$(ddt ca path)".`,
	}

	cmd.AddCommand(&cobra.Command{
		Use:   "path",
		Short: "Print the CA certificate's path",
		RunE: func(cmd *cobra.Command, args []string) error {
			authority, err := ca.Load(config.CADir())
			if errors.Is(err, fs.ErrNotExist) {
				return errors.New("there is no CA yet: it is created when the proxy starts (ddt proxy start)")
			}
			if err != nil {
				return err
			}
			fmt.Println(authority.CertPath())
			return nil
		},
	})
	return cmd
}
