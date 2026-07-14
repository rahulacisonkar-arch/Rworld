package network_map

import (
	"github.com/Artee VPNio/Artee VPN/shared/management/proto"
)

// MessageType indicates the type of update message for debouncing strategy
type MessageType int

const (
	// MessageTypeNetworkMap represents network map updates (peers, routes, DNS, firewall)
	// These updates can be safely debounced - only the latest state matters
	MessageTypeNetworkMap MessageType = iota
	// MessageTypeControlConfig represents control/config updates (tokens, peer expiration)
	// These updates should not be dropped as they contain time-sensitive information
	MessageTypeControlConfig
)

type UpdateMessage struct {
	Update      *proto.SyncResponse
	MessageType MessageType
}

