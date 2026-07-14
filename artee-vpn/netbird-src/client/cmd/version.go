package cmd

import (
	"github.com/spf13/cobra"

	"github.com/Artee VPNio/Artee VPN/version"
)

var (
	versionCmd = &cobra.Command{
		Use:   "version",
		Short: "Print the Artee VPN's client application version",
		Run: func(cmd *cobra.Command, args []string) {
			cmd.SetOut(cmd.OutOrStdout())
			out := version.Artee VPNVersion()
			if version.IsDevelopmentVersion(out) {
				if commit := version.Artee VPNCommit(); commit != "" {
					out += "-" + commit
				}
			}
			cmd.Println(out)
		},
	}
)

