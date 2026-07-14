package extra_settings

import (
	"context"

	"github.com/Artee VPNio/Artee VPN/management/server/types"
)

type Manager interface {
	GetExtraSettings(ctx context.Context, accountID string) (*types.ExtraSettings, error)
	UpdateExtraSettings(ctx context.Context, accountID, userID string, extraSettings *types.ExtraSettings) (bool, error)
}

