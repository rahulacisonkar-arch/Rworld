//go:build linux && !android

package iface

import (
	"errors"

	"github.com/Artee VPNio/Artee VPN/client/iface/bind"
	"github.com/Artee VPNio/Artee VPN/client/iface/device"
	"github.com/Artee VPNio/Artee VPN/client/iface/netstack"
	"github.com/Artee VPNio/Artee VPN/client/iface/wgproxy"
)

// NewWGIFace Creates a new WireGuard interface instance
func NewWGIFace(opts WGIFaceOpts) (*WGIface, error) {
	if netstack.IsEnabled() {
		iceBind := bind.NewICEBind(opts.TransportNet, opts.Address, opts.MTU)
		return &WGIface{
			tun:            device.NewNetstackDevice(opts.IFaceName, opts.Address, opts.WGPort, opts.WGPrivKey, opts.MTU, iceBind, netstack.ListenAddr()),
			userspaceBind:  true,
			wgProxyFactory: wgproxy.NewUSPFactory(iceBind, opts.MTU),
		}, nil
	}

	if device.WireGuardModuleIsLoaded() {
		return &WGIface{
			tun:            device.NewKernelDevice(opts.IFaceName, opts.Address, opts.WGPort, opts.WGPrivKey, opts.MTU, opts.TransportNet),
			wgProxyFactory: wgproxy.NewKernelFactory(opts.WGPort, opts.MTU),
		}, nil
	}

	if device.ModuleTunIsLoaded() {
		iceBind := bind.NewICEBind(opts.TransportNet, opts.Address, opts.MTU)
		return &WGIface{
			tun:            device.NewTunDevice(opts.IFaceName, opts.Address, opts.WGPort, opts.WGPrivKey, opts.MTU, iceBind),
			userspaceBind:  true,
			wgProxyFactory: wgproxy.NewUSPFactory(iceBind, opts.MTU),
		}, nil
	}

	return nil, errors.New("tun module not available")
}

