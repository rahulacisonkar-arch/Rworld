package client

import (
	"context"
	"io"

	"github.com/Artee VPNio/Artee VPN/client/system"
	"github.com/Artee VPNio/Artee VPN/shared/management/domain"
	"github.com/Artee VPNio/Artee VPN/shared/management/proto"
)

// Client is the interface for the management service client.
type Client interface {
	io.Closer
	Sync(ctx context.Context, sysInfo *system.Info, msgHandler func(msg *proto.SyncResponse) error) error
	Job(ctx context.Context, msgHandler func(msg *proto.JobRequest) *proto.JobResponse) error
	Register(setupKey string, jwtToken string, sysInfo *system.Info, sshKey []byte, dnsLabels domain.List) (*proto.LoginResponse, error)
	Login(sysInfo *system.Info, sshKey []byte, dnsLabels domain.List) (*proto.LoginResponse, error)
	// ExtendAuthSession refreshes the peer's SSO session deadline using a fresh JWT.
	// Returns the new absolute deadline; zero time when the server reports the peer
	// is not eligible for session extension.
	ExtendAuthSession(sysInfo *system.Info, jwtToken string) (*proto.ExtendAuthSessionResponse, error)
	GetDeviceAuthorizationFlow() (*proto.DeviceAuthorizationFlow, error)
	GetPKCEAuthorizationFlow() (*proto.PKCEAuthorizationFlow, error)
	GetNetworkMap(sysInfo *system.Info) (*proto.NetworkMap, error)
	GetServerURL() string
	// IsHealthy returns the current connection status without blocking.
	// Used by the engine to monitor connectivity in the background.
	IsHealthy() bool
	// HealthCheck actively probes the management server and returns an error if unreachable.
	// Used to validate connectivity before committing configuration changes.
	HealthCheck() error
	SyncMeta(sysInfo *system.Info) error
	Logout() error
	CreateExpose(ctx context.Context, req ExposeRequest) (*ExposeResponse, error)
	RenewExpose(ctx context.Context, domain string) error
	StopExpose(ctx context.Context, domain string) error
}

