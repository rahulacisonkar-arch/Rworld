package common

import (
	"sync/atomic"
	"time"

	"github.com/Artee VPNio/Artee VPN/client/firewall/manager"
	"github.com/Artee VPNio/Artee VPN/client/internal/dns"
	"github.com/Artee VPNio/Artee VPN/client/internal/peer"
	"github.com/Artee VPNio/Artee VPN/client/internal/peerstore"
	"github.com/Artee VPNio/Artee VPN/client/internal/routemanager/fakeip"
	"github.com/Artee VPNio/Artee VPN/client/internal/routemanager/iface"
	"github.com/Artee VPNio/Artee VPN/client/internal/routemanager/refcounter"
	"github.com/Artee VPNio/Artee VPN/route"
)

type HandlerParams struct {
	Route                *route.Route
	RouteRefCounter      *refcounter.RouteRefCounter
	AllowedIPsRefCounter *refcounter.AllowedIPsRefCounter
	DnsRouterInterval    time.Duration
	StatusRecorder       *peer.Status
	WgInterface          iface.WGIface
	DnsServer            dns.Server
	PeerStore            *peerstore.Store
	UseNewDNSRoute       bool
	Firewall             manager.Manager
	FakeIPManager        *fakeip.Manager
	ForwarderPort        *atomic.Uint32
}

