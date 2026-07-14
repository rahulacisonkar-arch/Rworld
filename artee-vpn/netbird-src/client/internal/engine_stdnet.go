//go:build !android

package internal

import (
	"github.com/Artee VPNio/Artee VPN/client/internal/stdnet"
)

func (e *Engine) newStdNet() (*stdnet.Net, error) {
	return stdnet.NewNet(e.clientCtx, e.config.IFaceBlackList)
}

