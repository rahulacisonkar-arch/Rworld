package cmd

import (
	"context"
	"net"
	"testing"
	"time"

	"github.com/golang/mock/gomock"
	"github.com/stretchr/testify/require"
	"go.opentelemetry.io/otel"
	"google.golang.org/grpc"

	"github.com/Artee VPNio/Artee VPN/management/server/integrations/integrated_validator/validator"

	nbcache "github.com/Artee VPNio/Artee VPN/management/server/cache"

	"github.com/Artee VPNio/Artee VPN/management/internals/controllers/network_map/controller"
	"github.com/Artee VPNio/Artee VPN/management/internals/controllers/network_map/update_channel"
	"github.com/Artee VPNio/Artee VPN/management/internals/modules/peers"
	"github.com/Artee VPNio/Artee VPN/management/internals/modules/peers/ephemeral/manager"
	nbgrpc "github.com/Artee VPNio/Artee VPN/management/internals/shared/grpc"
	"github.com/Artee VPNio/Artee VPN/management/server/job"

	clientProto "github.com/Artee VPNio/Artee VPN/client/proto"
	client "github.com/Artee VPNio/Artee VPN/client/server"
	"github.com/Artee VPNio/Artee VPN/management/internals/server/config"
	mgmt "github.com/Artee VPNio/Artee VPN/management/server"
	"github.com/Artee VPNio/Artee VPN/management/server/activity"
	"github.com/Artee VPNio/Artee VPN/management/server/groups"
	"github.com/Artee VPNio/Artee VPN/management/server/integrations/port_forwarding"
	"github.com/Artee VPNio/Artee VPN/management/server/permissions"
	"github.com/Artee VPNio/Artee VPN/management/server/settings"
	"github.com/Artee VPNio/Artee VPN/management/server/store"
	"github.com/Artee VPNio/Artee VPN/management/server/telemetry"
	"github.com/Artee VPNio/Artee VPN/management/server/types"
	mgmtProto "github.com/Artee VPNio/Artee VPN/shared/management/proto"
	sigProto "github.com/Artee VPNio/Artee VPN/shared/signal/proto"
	sig "github.com/Artee VPNio/Artee VPN/signal/server"
	"github.com/Artee VPNio/Artee VPN/util"
)

func startTestingServices(t *testing.T) string {
	t.Helper()
	config := &config.Config{}
	_, err := util.ReadJson("../testdata/management.json", config)
	if err != nil {
		t.Fatal(err)
	}

	_, signalLis := startSignal(t)
	signalAddr := signalLis.Addr().String()
	config.Signal.URI = signalAddr

	_, mgmLis := startManagement(t, config, "../testdata/store.sql")
	mgmAddr := mgmLis.Addr().String()
	return mgmAddr
}

func startSignal(t *testing.T) (*grpc.Server, net.Listener) {
	t.Helper()
	lis, err := net.Listen("tcp", ":0")
	if err != nil {
		t.Fatal(err)
	}
	s := grpc.NewServer()
	srv, err := sig.NewServer(context.Background(), otel.Meter(""))
	require.NoError(t, err)

	sigProto.RegisterSignalExchangeServer(s, srv)
	go func() {
		if err := s.Serve(lis); err != nil {
			panic(err)
		}
	}()

	return s, lis
}

func startManagement(t *testing.T, config *config.Config, testFile string) (*grpc.Server, net.Listener) {
	t.Helper()

	lis, err := net.Listen("tcp", ":0")
	if err != nil {
		t.Fatal(err)
	}
	s := grpc.NewServer()
	store, cleanUp, err := store.NewTestStoreFromSQL(context.Background(), testFile, t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(cleanUp)

	eventStore := &activity.InMemoryEventStore{}

	ctrl := gomock.NewController(t)
	t.Cleanup(ctrl.Finish)

	permissionsManagerMock := permissions.NewMockManager(ctrl)
	peersmanager := peers.NewManager(store, permissionsManagerMock)
	settingsManagerMock := settings.NewMockManager(ctrl)

	jobManager := job.NewJobManager(nil, store, peersmanager)

	ctx := context.Background()

	cacheStore, err := nbcache.NewStore(ctx, 100*time.Millisecond, 300*time.Millisecond, 100)
	if err != nil {
		t.Fatal(err)
	}

	iv, _ := validator.NewIntegratedValidator(ctx, peersmanager, settingsManagerMock, eventStore, cacheStore)

	metrics, err := telemetry.NewDefaultAppMetrics(ctx)
	require.NoError(t, err)

	settingsMockManager := settings.NewMockManager(ctrl)
	groupsManager := groups.NewManagerMock()

	settingsMockManager.EXPECT().
		GetSettings(gomock.Any(), gomock.Any(), gomock.Any()).
		Return(&types.Settings{}, nil).
		AnyTimes()

	updateManager := update_channel.NewPeersUpdateManager(metrics)
	requestBuffer := mgmt.NewAccountRequestBuffer(ctx, store)
	networkMapController := controller.NewController(ctx, store, metrics, updateManager, requestBuffer, mgmt.MockIntegratedValidator{}, settingsMockManager, "Artee VPN.cloud", port_forwarding.NewControllerMock(), manager.NewEphemeralManager(store, peersmanager), config)

	accountManager, err := mgmt.BuildManager(ctx, config, store, networkMapController, jobManager, nil, "", eventStore, nil, false, iv, metrics, port_forwarding.NewControllerMock(), settingsMockManager, permissionsManagerMock, false, cacheStore)
	if err != nil {
		t.Fatal(err)
	}

	secretsManager, err := nbgrpc.NewTimeBasedAuthSecretsManager(updateManager, config.TURNConfig, config.Relay, settingsMockManager, groupsManager)
	if err != nil {
		t.Fatal(err)
	}
	mgmtServer, err := nbgrpc.NewServer(config, accountManager, settingsMockManager, jobManager, secretsManager, nil, nil, &mgmt.MockIntegratedValidator{}, networkMapController, nil, nil)
	if err != nil {
		t.Fatal(err)
	}
	mgmtProto.RegisterManagementServiceServer(s, mgmtServer)
	go func() {
		if err := s.Serve(lis); err != nil {
			t.Error(err)
		}
	}()

	return s, lis
}

func startClientDaemon(
	t *testing.T, ctx context.Context, _, _ string,
) (*grpc.Server, net.Listener) {
	t.Helper()
	lis, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		t.Fatal(err)
	}
	s := grpc.NewServer()

	server := client.New(ctx,
		"", "", false, false, false, false)
	if err := server.Start(); err != nil {
		t.Fatal(err)
	}
	clientProto.RegisterDaemonServiceServer(s, server)
	go func() {
		if err := s.Serve(lis); err != nil {
			t.Error(err)
		}
	}()

	time.Sleep(time.Second)

	return s, lis
}

