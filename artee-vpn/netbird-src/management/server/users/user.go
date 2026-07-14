package users

import (
	"github.com/Artee VPNio/Artee VPN/management/server/permissions/roles"
	"github.com/Artee VPNio/Artee VPN/management/server/types"
)

// Wrapped UserInfo with Role Permissions
type UserInfoWithPermissions struct {
	*types.UserInfo

	Permissions roles.Permissions
	Restricted  bool
}

