# build & run locally with:
#   cd "$(git rev-parse --show-toplevel)"
#   CGO_ENABLED=0 go build -o Artee VPN ./client
#   sudo podman build -t localhost/Artee VPN:latest -f client/Dockerfile --ignorefile .dockerignore-client .
#   sudo podman run --rm -it --cap-add={BPF,NET_ADMIN,NET_RAW} localhost/Artee VPN:latest

FROM alpine:3.24
# iproute2: busybox doesn't display ip rules properly
RUN apk add --no-cache \
    bash \
    ca-certificates \
    ip6tables \
    iproute2 \
    iptables

ENV \
    Artee VPN_BIN="/usr/local/bin/Artee VPN" \
    NB_LOG_FILE="console,/var/log/Artee VPN/client.log" \
    NB_DAEMON_ADDR="unix:///var/run/Artee VPN.sock" \
    NB_ENABLE_CAPTURE="false" \
    NB_ENTRYPOINT_SERVICE_TIMEOUT="30"

ENTRYPOINT [ "/usr/local/bin/Artee VPN-entrypoint.sh" ]
ARG TARGETPLATFORM
ARG Artee VPN_BINARY=$TARGETPLATFORM/Artee VPN
COPY client/Artee VPN-entrypoint.sh /usr/local/bin/Artee VPN-entrypoint.sh
COPY "${Artee VPN_BINARY}"  /usr/local/bin/Artee VPN

