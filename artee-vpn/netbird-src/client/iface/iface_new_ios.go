//go:build ios

package iface

import (
	"github.com/Artee VPNio/Artee VPN/client/iface/bind"
	"github.com/Artee VPNio/Artee VPN/client/iface/device"
	"github.com/Artee VPNio/Artee VPN/client/iface/wgproxy"
)

// NewWGIFace Creates a new WireGuard interface instance
func NewWGIFace(opts WGIFaceOpts) (*WGIface, error) {
	iceBind := bind.NewICEBind(opts.TransportNet, opts.Address, opts.MTU)

	wgIFace := &WGIface{
		tun:            device.NewTunDevice(opts.IFaceName, opts.Address, opts.WGPort, opts.WGPrivKey, opts.MTU, iceBind, opts.MobileArgs.TunFd),
		userspaceBind:  true,
		wgProxyFactory: wgproxy.NewUSPFactory(iceBind, opts.MTU),
	}
	return wgIFace, nil
}

