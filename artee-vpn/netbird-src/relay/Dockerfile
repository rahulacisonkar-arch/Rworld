FROM gcr.io/distroless/base:debug
ENTRYPOINT [ "/go/bin/Artee VPN-relay" ]
ENV NB_LOG_FILE=console
ARG TARGETPLATFORM
COPY ${TARGETPLATFORM}/Artee VPN-relay /go/bin/Artee VPN-relay

