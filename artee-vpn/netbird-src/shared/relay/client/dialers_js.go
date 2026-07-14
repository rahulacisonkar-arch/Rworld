//go:build js

package client

import (
	"github.com/Artee VPNio/Artee VPN/shared/relay/client/dialer"
	"github.com/Artee VPNio/Artee VPN/shared/relay/client/dialer/ws"
)

func (c *Client) getDialers(_ TransportMode) []dialer.DialeFn {
	// JS/WASM build only uses WebSocket transport
	return []dialer.DialeFn{ws.Dialer{}}
}

func (c *Client) baseDialers(_ TransportMode) []dialer.DialeFn {
	return []dialer.DialeFn{ws.Dialer{}}
}

