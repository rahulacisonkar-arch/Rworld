//go:build !ios

package dynamic

import (
	"net"

	"github.com/Artee VPNio/Artee VPN/shared/management/domain"
)

func (r *Route) getIPsFromResolver(domain domain.Domain) ([]net.IP, error) {
	return net.LookupIP(domain.PunycodeString())
}

