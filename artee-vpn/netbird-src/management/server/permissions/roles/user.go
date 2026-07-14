package roles

import (
	"github.com/Artee VPNio/Artee VPN/management/server/permissions/operations"
	"github.com/Artee VPNio/Artee VPN/management/server/types"
)

var User = RolePermissions{
	Role: types.UserRoleUser,
	AutoAllowNew: map[operations.Operation]bool{
		operations.Read:   false,
		operations.Create: false,
		operations.Update: false,
		operations.Delete: false,
	},
}

