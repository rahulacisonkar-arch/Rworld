package main

import (
	"os"

	"github.com/Artee VPNio/Artee VPN/client/cmd"
)

func main() {
	if err := cmd.Execute(); err != nil {
		os.Exit(1)
	}
}

