package main

import (
	"github.com/Artee VPNio/Artee VPN/signal/cmd"
	"os"
)

func main() {
	if err := cmd.Execute(); err != nil {
		os.Exit(1)
	}
}

