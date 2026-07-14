package main

import (
	_ "embed"
)

//go:embed assets/Artee VPN.ico
var iconAbout []byte

//go:embed assets/Artee VPN-disconnected.ico
var iconAboutDisconnected []byte

//go:embed assets/Artee VPN-systemtray-connected.ico
var iconConnected []byte

//go:embed assets/Artee VPN-systemtray-connected-dark.ico
var iconConnectedDark []byte

//go:embed assets/Artee VPN-systemtray-disconnected.ico
var iconDisconnected []byte

//go:embed assets/Artee VPN-systemtray-update-disconnected.ico
var iconUpdateDisconnected []byte

//go:embed assets/Artee VPN-systemtray-update-disconnected-dark.ico
var iconUpdateDisconnectedDark []byte

//go:embed assets/Artee VPN-systemtray-update-connected.ico
var iconUpdateConnected []byte

//go:embed assets/Artee VPN-systemtray-update-connected-dark.ico
var iconUpdateConnectedDark []byte

//go:embed assets/Artee VPN-systemtray-connecting.ico
var iconConnecting []byte

//go:embed assets/Artee VPN-systemtray-connecting-dark.ico
var iconConnectingDark []byte

//go:embed assets/Artee VPN-systemtray-error.ico
var iconError []byte

//go:embed assets/Artee VPN-systemtray-error-dark.ico
var iconErrorDark []byte

