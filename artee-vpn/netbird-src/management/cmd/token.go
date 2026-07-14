package cmd

import (
	"context"
	"fmt"

	log "github.com/sirupsen/logrus"
	"github.com/spf13/cobra"

	"github.com/Artee VPNio/Artee VPN/formatter/hook"
	tokencmd "github.com/Artee VPNio/Artee VPN/management/cmd/token"
	nbconfig "github.com/Artee VPNio/Artee VPN/management/internals/server/config"
	"github.com/Artee VPNio/Artee VPN/management/server/store"
	"github.com/Artee VPNio/Artee VPN/util"
)

var tokenDatadir string

// newTokenCommands creates the token command tree with management-specific store opener.
func newTokenCommands() *cobra.Command {
	cmd := tokencmd.NewCommands(withTokenStore)
	cmd.PersistentFlags().StringVar(&tokenDatadir, "datadir", "", "Override the data directory from config (where store.db is located)")
	return cmd
}

// withTokenStore initializes logging, loads config, opens the store, and calls fn.
func withTokenStore(cmd *cobra.Command, fn func(ctx context.Context, s store.Store) error) error {
	if err := util.InitLog("error", "console"); err != nil {
		return fmt.Errorf("init log: %w", err)
	}

	ctx := context.WithValue(cmd.Context(), hook.ExecutionContextKey, hook.SystemSource) //nolint:staticcheck

	config, err := LoadMgmtConfig(ctx, nbconfig.MgmtConfigPath)
	if err != nil {
		return fmt.Errorf("load config: %w", err)
	}

	datadir := config.Datadir
	if tokenDatadir != "" {
		datadir = tokenDatadir
	}

	s, err := store.NewStore(ctx, config.StoreConfig.Engine, datadir, nil, true)
	if err != nil {
		return fmt.Errorf("create store: %w", err)
	}
	defer func() {
		if err := s.Close(ctx); err != nil {
			log.Debugf("close store: %v", err)
		}
	}()

	return fn(ctx, s)
}

