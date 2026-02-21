package main

import (
	"fmt"
	"os"

	"github.com/christhomas/docker-dev-tools/internal/app"
	"github.com/christhomas/docker-dev-tools/internal/cli"
)

// Set by ldflags at build time.
var (
	version = "dev"
	commit  = "none"
	date    = "unknown"
)

func main() {
	application := app.New(app.BuildInfo{
		Version: version,
		Commit:  commit,
		Date:    date,
	})

	rootCmd := cli.NewRootCmd(application)
	if err := rootCmd.Execute(); err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(1)
	}
}
