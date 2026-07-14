package roles

import (
	"github.com/Artee VPNio/Artee VPN/management/server/permissions/modules"
	"github.com/Artee VPNio/Artee VPN/management/server/permissions/operations"
	"github.com/Artee VPNio/Artee VPN/management/server/types"
)

var Admin = RolePermissions{
	Role: types.UserRoleAdmin,
	AutoAllowNew: map[operations.Operation]bool{
		operations.Read:   true,
		operations.Create: true,
		operations.Update: true,
		operations.Delete: true,
	},
	Permissions: Permissions{
		modules.Accounts: {
			operations.Read:   true,
			operations.Create: false,
			operations.Update: false,
			operations.Delete: false,
		},
	},
}

