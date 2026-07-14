package desktop

import "github.com/Artee VPNio/Artee VPN/version"

// GetUIUserAgent returns the Desktop ui user agent
func GetUIUserAgent() string {
	return "Artee VPN-desktop-ui/" + version.Artee VPNVersion()
}

