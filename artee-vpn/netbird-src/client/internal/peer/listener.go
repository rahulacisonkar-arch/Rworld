package peer

// Listener is a callback type about the Artee VPN network connection state
type Listener interface {
	OnConnected()
	OnDisconnected()
	OnConnecting()
	OnDisconnecting()
	OnAddressChanged(string, string)
	OnPeersListChanged(int)
}

