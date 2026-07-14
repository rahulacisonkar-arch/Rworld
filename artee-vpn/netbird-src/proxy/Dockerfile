FROM golang:1.25-alpine AS builder
WORKDIR /app

RUN echo "Artee VPN:x:1000:1000:Artee VPN:/var/lib/Artee VPN:/sbin/nologin" > /tmp/passwd && \
    echo "Artee VPN:x:1000:Artee VPN" > /tmp/group && \
    mkdir -p /tmp/var/lib/Artee VPN && \
    mkdir -p /tmp/certs

FROM gcr.io/distroless/base:debug
ARG TARGETPLATFORM
COPY ${TARGETPLATFORM}/Artee VPN-proxy /go/bin/Artee VPN-proxy
COPY --from=builder /tmp/passwd /etc/passwd
COPY --from=builder /tmp/group /etc/group
COPY --from=builder --chown=1000:1000 /tmp/var/lib/Artee VPN /var/lib/Artee VPN
COPY --from=builder --chown=1000:1000 --chmod=755 /tmp/certs /certs
USER Artee VPN:Artee VPN
ENV HOME=/var/lib/Artee VPN
ENV NB_PROXY_ADDRESS=":8443"
EXPOSE 8443
ENTRYPOINT ["/go/bin/Artee VPN-proxy"]

