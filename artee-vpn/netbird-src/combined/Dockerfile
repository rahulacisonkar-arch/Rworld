FROM ubuntu:24.04
RUN apt update && apt install -y ca-certificates && rm -fr /var/cache/apt
ENTRYPOINT [ "/go/bin/Artee VPN-server" ]
CMD ["--config", "/etc/Artee VPN/config.yaml"]
ARG TARGETPLATFORM
COPY ${TARGETPLATFORM}/Artee VPN-server /go/bin/Artee VPN-server

