package roles

import (
	"github.com/Artee VPNio/Artee VPN/management/server/permissions/operations"
	"github.com/Artee VPNio/Artee VPN/management/server/types"
)

var Auditor = RolePermissions{
	Role: types.UserRoleAuditor,
	AutoAllowNew: map[operations.Operation]bool{
		operations.Read:   true,
		operations.Create: false,
		operations.Update: false,
		operations.Delete: false,
	},
}

