//go:build !android

package server

import (
	"github.com/Artee VPNio/Artee VPN/client/firewall/iptables"
	"github.com/Artee VPNio/Artee VPN/client/firewall/nftables"
	"github.com/Artee VPNio/Artee VPN/client/internal/dns"
	"github.com/Artee VPNio/Artee VPN/client/internal/routemanager/systemops"
	"github.com/Artee VPNio/Artee VPN/client/internal/statemanager"
	"github.com/Artee VPNio/Artee VPN/client/ssh/config"
)

// registerStates registers all states that need crash recovery cleanup.
func registerStates(mgr *statemanager.Manager) {
	mgr.RegisterState(&dns.ShutdownState{})
	mgr.RegisterState(&systemops.ShutdownState{})
	mgr.RegisterState(&nftables.ShutdownState{})
	mgr.RegisterState(&iptables.ShutdownState{})
	mgr.RegisterState(&config.ShutdownState{})
}

