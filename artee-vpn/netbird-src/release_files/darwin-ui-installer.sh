#!/bin/sh

export PATH=$PATH:/usr/local/bin:/opt/homebrew/bin

# check if Artee VPN is installed
NB_BIN=$(which Artee VPN)
if [ -n "$NB_BIN" ]
then
  echo "Stopping and uninstalling Artee VPN daemon"
  Artee VPN service stop || true
  Artee VPN service uninstall || true
fi

# check if Artee VPN is installed
NB_BIN=$(which Artee VPN)
if [ -z "$NB_BIN" ]
then
  echo "Artee VPN daemon is not installed. Please run: brew install Artee VPNio/tap/Artee VPN"
  exit 1
fi
NB_UI_VERSION=$1
NB_VERSION=$(Artee VPN version)
if [ "X-$NB_UI_VERSION" != "X-$NB_VERSION" ]
then
  echo "Artee VPN's daemon is running with a different version than the Artee VPN's UI:"
  echo "Artee VPN UI Version: $NB_UI_VERSION"
  echo "Artee VPN Daemon Version: $NB_VERSION"
  echo "Please run: brew install Artee VPNio/tap/Artee VPN"
  echo "to update it"
fi

if [ -n "$NB_BIN" ]
then
  echo "Stopping Artee VPN daemon"
  osascript -e 'quit app "Artee VPN UI"' 2> /dev/null || true
  Artee VPN service stop 2> /dev/null || true
fi

# start Artee VPN daemon service
echo "Starting Artee VPN daemon"
Artee VPN service install 2> /dev/null || true
Artee VPN service start || true

# start app
open /Applications/Artee VPN\ UI.app

