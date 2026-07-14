package iface

import (
	"github.com/Artee VPNio/Artee VPN/client/iface/device"
)

// GetInterfaceGUIDString returns an interface GUID. This is useful on Windows only
func (w *WGIface) GetInterfaceGUIDString() (string, error) {
	return w.tun.(*device.TunDevice).GetInterfaceGUIDString()
}

