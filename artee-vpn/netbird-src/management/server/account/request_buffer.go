package account

import (
	"context"

	"github.com/Artee VPNio/Artee VPN/management/server/types"
)

type RequestBuffer interface {
	GetAccountWithBackpressure(ctx context.Context, accountID string) (*types.Account, error)
}

