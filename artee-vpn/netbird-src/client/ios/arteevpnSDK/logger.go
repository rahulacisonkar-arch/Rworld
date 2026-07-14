//go:build ios

package Artee VPNSDK

import (
	"github.com/Artee VPNio/Artee VPN/util"
)

// InitializeLog initializes the log file.
func InitializeLog(logLevel string, filePath string) error {
	return util.InitLog(logLevel, filePath)
}

