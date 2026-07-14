package internal

import "github.com/Artee VPNio/Artee VPN/client/internal/stdnet"

func (e *Engine) newStdNet() (*stdnet.Net, error) {
	return stdnet.NewNetWithDiscover(e.clientCtx, e.mobileDep.IFaceDiscover, e.config.IFaceBlackList)
}

