//go:build !devcert

package tls

import (
	"crypto/tls"
	"crypto/x509"

	log "github.com/sirupsen/logrus"

	"github.com/Artee VPNio/Artee VPN/util/embeddedroots"
)

func ClientQUICTLSConfig() *tls.Config {
	certPool, err := x509.SystemCertPool()
	if err != nil || certPool == nil {
		log.Debugf("System cert pool not available; falling back to embedded cert, error: %v", err)
		certPool = embeddedroots.Get()
	}

	return &tls.Config{
		NextProtos: []string{NBalpn},
		RootCAs:    certPool,
	}
}

