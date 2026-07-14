package cmd

const (
	defaultMgmtDataDir   = "/var/lib/Artee VPN/"
	defaultMgmtConfigDir = "/etc/Artee VPN"
	defaultLogDir        = "/var/log/Artee VPN"

	oldDefaultMgmtDataDir   = "/var/lib/wiretrustee/"
	oldDefaultMgmtConfigDir = "/etc/wiretrustee"
	oldDefaultLogDir        = "/var/log/wiretrustee"

	defaultMgmtConfig    = defaultMgmtConfigDir + "/management.json"
	defaultLogFile       = defaultLogDir + "/management.log"
	oldDefaultMgmtConfig = oldDefaultMgmtConfigDir + "/management.json"
	oldDefaultLogFile    = oldDefaultLogDir + "/management.log"

	defaultSingleAccModeDomain = "Artee VPN.selfhosted"
)

