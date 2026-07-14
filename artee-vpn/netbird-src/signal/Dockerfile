FROM gcr.io/distroless/base:debug
ENTRYPOINT [ "/go/bin/Artee VPN-signal","run" ]
CMD ["--log-file", "console"]
ARG TARGETPLATFORM
COPY ${TARGETPLATFORM}/Artee VPN-signal /go/bin/Artee VPN-signal

