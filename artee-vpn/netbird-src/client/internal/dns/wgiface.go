//go:build !windows

package dns

import (
	"net"

	"golang.zx2c4.com/wireguard/tun/netstack"

	"github.com/Artee VPNio/Artee VPN/client/iface/device"
	"github.com/Artee VPNio/Artee VPN/client/iface/wgaddr"
)

// WGIface defines subset methods of interface required for manager
type WGIface interface {
	Name() string
	Address() wgaddr.Address
	ToInterface() *net.Interface
	IsUserspaceBind() bool
	GetFilter() device.PacketFilter
	GetDevice() *device.FilteredDevice
	GetNet() *netstack.Net
}

