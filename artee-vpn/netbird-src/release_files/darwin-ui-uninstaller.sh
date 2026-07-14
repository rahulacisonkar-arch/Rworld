#!/bin/sh

export PATH=$PATH:/usr/local/bin

# check if Artee VPN is installed
NB_BIN=$(which Artee VPN)
if [ -z "$NB_BIN" ]
then
  exit 0
fi
# start Artee VPN daemon service
echo "Artee VPN daemon service still running. You can uninstall it by running: "
echo "sudo Artee VPN service stop"
echo "sudo Artee VPN service uninstall"

