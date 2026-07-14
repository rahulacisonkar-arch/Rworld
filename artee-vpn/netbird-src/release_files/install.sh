# This code is based on the Artee VPN-installer contribution by physk on GitHub.
# Source: https://github.com/physk/Artee VPN-installer
set -e

CONFIG_FOLDER="/etc/Artee VPN"
CONFIG_FILE="$CONFIG_FOLDER/install.conf"

OWNER="Artee VPNio"
REPO="Artee VPN"
CLI_APP="Artee VPN"
UI_APP="Artee VPN-ui"

# Set default variable
OS_NAME=""
OS_TYPE=""
ARCH="$(uname -m)"
PACKAGE_MANAGER="bin"
INSTALL_DIR=""
SUDO=""


if command -v sudo > /dev/null && [ "$(id -u)" -ne 0 ]; then
    SUDO="sudo"
elif command -v doas > /dev/null && [ "$(id -u)" -ne 0 ]; then
    SUDO="doas"
fi

if [ -z ${Artee VPN_RELEASE+x} ]; then
    Artee VPN_RELEASE=latest
fi

TAG_NAME=""

get_release() {
    local RELEASE=$1
    if [ "$RELEASE" = "latest" ]; then
        local TAG="latest"
        local URL="https://pkgs.Artee VPN.io/releases/latest"
    else
        local TAG="tags/${RELEASE}"
        local URL="https://api.github.com/repos/${OWNER}/${REPO}/releases/${TAG}"
    fi
	OUTPUT=""
    if [ -n "$GITHUB_TOKEN" ]; then
          OUTPUT=$(curl -H  "Authorization: token ${GITHUB_TOKEN}" -s "${URL}")
    else
          OUTPUT=$(curl -s "${URL}") 
    fi
	TAG_NAME=$(echo ${OUTPUT} |  grep -Eo '\"tag_name\":\s*\"v([0-9]+\.){2}[0-9]+"' | tail -n 1)
	echo "${TAG_NAME}" | grep -oE 'v[0-9]+\.[0-9]+\.[0-9]+'
}

download_release_binary() {
    VERSION=$(get_release "$Artee VPN_RELEASE")
	echo "Using the following tag name for binary installation: ${TAG_NAME}"
    BASE_URL="https://github.com/${OWNER}/${REPO}/releases/download"
    BINARY_BASE_NAME="${VERSION#v}_${OS_TYPE}_${ARCH}.tar.gz"

    # for Darwin, download the signed Artee VPN-UI
    if [ "$OS_TYPE" = "darwin" ] && [ "$1" = "$UI_APP" ]; then
        BINARY_BASE_NAME="${VERSION#v}_${OS_TYPE}_${ARCH}_signed.zip"
    fi

    if [ "$1" = "$UI_APP" ]; then
       BINARY_NAME="$1-${OS_TYPE}_${BINARY_BASE_NAME}"
       if [ "$OS_TYPE" = "darwin" ]; then
         BINARY_NAME="$1_${BINARY_BASE_NAME}"
       fi
    else
       BINARY_NAME="$1_${BINARY_BASE_NAME}"
    fi

    DOWNLOAD_URL="${BASE_URL}/${VERSION}/${BINARY_NAME}"

    echo "Installing $1 from $DOWNLOAD_URL"
    if [ -n "$GITHUB_TOKEN" ]; then
      cd /tmp && curl -H  "Authorization: token ${GITHUB_TOKEN}" -LO "$DOWNLOAD_URL"
    else
      cd /tmp && curl -LO "$DOWNLOAD_URL" || curl -LO --dns-servers 8.8.8.8 "$DOWNLOAD_URL"
    fi


    if [ "$OS_TYPE" = "darwin" ] && [ "$1" = "$UI_APP" ]; then
        INSTALL_DIR="/Applications/Artee VPN UI.app"

        if test -d "$INSTALL_DIR" ; then
          echo "removing $INSTALL_DIR"
          rm -rfv "$INSTALL_DIR"
        fi

        # Unzip the app and move to INSTALL_DIR
        unzip -q -o "$BINARY_NAME"
        mv -v "Artee VPN_ui_${OS_TYPE}/" "$INSTALL_DIR/" || mv -v "Artee VPN_ui_${OS_TYPE}_${ARCH}/" "$INSTALL_DIR/"
    else
        ${SUDO} mkdir -p "$INSTALL_DIR"
        tar -xzvf "$BINARY_NAME"
        ${SUDO} mv "${1%_"${BINARY_BASE_NAME}"}" "$INSTALL_DIR/"
    fi
}

add_apt_repo() {
    ${SUDO} apt-get update
    ${SUDO} apt-get install ca-certificates curl gnupg -y

    # Remove old keys and repo source files
    ${SUDO} rm -f \
        /etc/apt/sources.list.d/Artee VPN.list \
        /etc/apt/sources.list.d/wiretrustee.list \
        /etc/apt/trusted.gpg.d/wiretrustee.gpg \
        /usr/share/keyrings/Artee VPN-archive-keyring.gpg \
        /usr/share/keyrings/wiretrustee-archive-keyring.gpg

    curl -sSL https://pkgs.Artee VPN.io/debian/public.key \
    | ${SUDO} gpg --dearmor -o /usr/share/keyrings/Artee VPN-archive-keyring.gpg

    # Explicitly set the file permission
    ${SUDO} chmod 0644 /usr/share/keyrings/Artee VPN-archive-keyring.gpg

    echo 'deb [signed-by=/usr/share/keyrings/Artee VPN-archive-keyring.gpg] https://pkgs.Artee VPN.io/debian stable main' \
    | ${SUDO} tee /etc/apt/sources.list.d/Artee VPN.list

    ${SUDO} apt-get update
}

add_rpm_repo() {
cat <<-EOF | ${SUDO} tee /etc/yum.repos.d/Artee VPN.repo
[Artee VPN]
name=Artee VPN
baseurl=https://pkgs.Artee VPN.io/yum/
enabled=1
gpgcheck=1
gpgkey=https://pkgs.Artee VPN.io/yum/repodata/repomd.xml.key
repo_gpgcheck=1
EOF
}

prepare_tun_module() {
  # Create the necessary file structure for /dev/net/tun
  if [ ! -c /dev/net/tun ]; then
    if [ ! -d /dev/net ]; then
      mkdir -m 755 /dev/net
    fi
    mknod /dev/net/tun c 10 200
    chmod 0755 /dev/net/tun
  fi

  # Load the tun module if not already loaded
  if ! lsmod | grep -q "^tun\s"; then
    insmod /lib/modules/tun.ko
  fi
}

install_native_binaries() {
    # Checks  for supported architecture
    case "$ARCH" in
        x86_64|amd64)
            ARCH="amd64"
        ;;
        i?86|x86)
            ARCH="386"
        ;;
        aarch64|arm64)
            ARCH="arm64"
        ;;
        *)
            echo "Architecture ${ARCH} not supported"
            exit 2
        ;;
    esac

    # download and copy binaries to INSTALL_DIR
    download_release_binary "$CLI_APP"
    if ! $SKIP_UI_APP; then
        download_release_binary "$UI_APP"
    fi
}

# Handle macOS .pkg installer
install_pkg() {
  case "$(uname -m)" in
    x86_64) ARCH="amd64" ;;
    arm64|aarch64) ARCH="arm64" ;;
    *) echo "Unsupported macOS arch: $(uname -m)" >&2; exit 1 ;;
  esac

  PKG_URL=$(curl -sIL -o /dev/null -w '%{url_effective}' "https://pkgs.Artee VPN.io/macos/${ARCH}")
  echo "Downloading Artee VPN macOS installer from https://pkgs.Artee VPN.io/macos/${ARCH}"
  curl -fsSL -o /tmp/Artee VPN.pkg "${PKG_URL}"
  ${SUDO} installer -pkg /tmp/Artee VPN.pkg -target /
  rm -f /tmp/Artee VPN.pkg
}

check_use_bin_variable() {
    if [ "${USE_BIN_INSTALL}-x" = "true-x" ]; then
      echo "The installation will be performed using binary files"
      return 0
    fi
    return 1
}

install_Artee VPN() {
    if [ -x "$(command -v Artee VPN)" ]; then
      status_output="$(Artee VPN status 2>&1 || true)"

      if echo "$status_output" | grep -q 'failed to connect to daemon error: context deadline exceeded'; then
          echo "Warning: could not reach Artee VPN daemon (timeout), proceeding anyway"
      else
          if echo "$status_output" | grep -q 'Management: Connected' && \
              echo "$status_output" | grep -q 'Signal: Connected'; then
              echo "Artee VPN service is running, please stop it before proceeding"
              exit 1
          fi

          if [ -n "$status_output" ]; then
              echo "Artee VPN seems to be installed already, please remove it before proceeding"
              exit 1
          fi
      fi
    fi

    # Run the installation, if a desktop environment is not detected
    # only the CLI will be installed
    case "$PACKAGE_MANAGER" in
    apt)
        add_apt_repo
        ${SUDO} apt-get install Artee VPN -y

        if ! $SKIP_UI_APP; then
            ${SUDO} apt-get install Artee VPN-ui -y
        fi
    ;;
    yum)
        add_rpm_repo
        ${SUDO} yum -y install Artee VPN
        if ! $SKIP_UI_APP; then
            ${SUDO} yum -y install Artee VPN-ui
        fi
    ;;
    dnf)
        add_rpm_repo
        ${SUDO} dnf -y install Artee VPN

        if ! $SKIP_UI_APP; then
            ${SUDO} dnf -y install Artee VPN-ui
        fi
    ;;
    rpm-ostree)
        add_rpm_repo
        ${SUDO} rpm-ostree -y install Artee VPN
        if ! $SKIP_UI_APP; then
            ${SUDO} rpm-ostree -y install Artee VPN-ui
        fi
        # ensure the service is started after install
         ${SUDO} Artee VPN service install || true
         ${SUDO} Artee VPN service start || true
    ;;
    pkg)
        # Check if the package is already installed
        if [ -f /Library/Receipts/Artee VPN.pkg ]; then
            echo "Artee VPN is already installed. Please remove it before proceeding."
            exit 1
        fi

        # Install the package
        install_pkg
    ;;
    brew)
        # Remove Artee VPN if it had been installed using Homebrew before
        if brew ls --versions Artee VPN >/dev/null 2>&1; then
            echo "Removing existing Artee VPN client"

            # Stop and uninstall daemon service:
            Artee VPN service stop
            Artee VPN service uninstall

            # Unlink the app
            brew unlink Artee VPN
        fi

        brew install Artee VPNio/tap/Artee VPN
        if ! $SKIP_UI_APP; then
            brew install --cask Artee VPNio/tap/Artee VPN-ui
        fi
    ;;
    *)
      if [ "$OS_NAME" = "nixos" ];then
        echo "Please add Artee VPN to your NixOS configuration.nix directly:"
			  echo ""
			  echo "services.Artee VPN.enable = true;"

        if ! $SKIP_UI_APP; then
          echo "environment.systemPackages = [ pkgs.Artee VPN-ui ];"
        fi

        echo "Build and apply new configuration:"
        echo ""
        echo "${SUDO} nixos-rebuild switch"
			  exit 0
      fi

        install_native_binaries
    ;;
    esac

    if [ "$OS_NAME" = "synology" ]; then
        prepare_tun_module
    fi

    # Add package manager to config
    ${SUDO} mkdir -p "$CONFIG_FOLDER"
    echo "package_manager=$PACKAGE_MANAGER" | ${SUDO} tee "$CONFIG_FILE" > /dev/null

    # Load and start Artee VPN service
    if [ "$PACKAGE_MANAGER" != "rpm-ostree" ] && [ "$PACKAGE_MANAGER" != "pkg" ]; then
        if ! ${SUDO} Artee VPN service install 2>&1; then
            echo "Artee VPN service has already been loaded"
        fi
        if ! ${SUDO} Artee VPN service start 2>&1; then
            echo "Artee VPN service has already been started"
        fi
    fi


    echo "Installation has been finished. To connect, you need to run Artee VPN by executing the following command:"
    echo ""
    echo "Artee VPN up"
}

version_greater_equal() {
    printf '%s\n%s\n' "$2" "$1" | sort -V -c
}

is_bin_package_manager() {
  if ${SUDO} test -f "$1" && ${SUDO} grep -q "package_manager=bin" "$1" ; then
    return 0
  else
    return 1
  fi
}

stop_running_Artee VPN_ui() {
  NB_UI_PROC=$(ps -ef | grep "[n]etbird-ui" | awk '{print $2}')
  if [ -n "$NB_UI_PROC" ]; then
    echo "Artee VPN UI is running with PID $NB_UI_PROC. Stopping it..."
    kill -9 "$NB_UI_PROC"
  fi
}

update_Artee VPN() {
  if is_bin_package_manager "$CONFIG_FILE"; then
    latest_release=$(get_release "latest")
    latest_version=${latest_release#v}
    installed_version=$(Artee VPN version)

    if [ "$latest_version" = "$installed_version" ]; then
      echo "Installed Artee VPN version ($installed_version) is up-to-date"
      exit 0
    fi

    if version_greater_equal "$latest_version" "$installed_version"; then
      echo "Artee VPN new version ($latest_version) available. Updating..."
      echo ""
      echo "Initiating Artee VPN update. This will stop the Artee VPN service and restart it after the update"

      ${SUDO} Artee VPN service stop || true
      ${SUDO} Artee VPN service uninstall || true
      stop_running_Artee VPN_ui
      install_native_binaries

      ${SUDO} Artee VPN service install
      ${SUDO} Artee VPN service start
    fi
  else
     echo "Artee VPN installation was done using a package manager. Please use your system's package manager to update"
  fi
}

# Checks if SKIP_UI_APP env is set
if [ -z "$SKIP_UI_APP" ]; then
    SKIP_UI_APP=false
else
    if $SKIP_UI_APP; then
      echo "SKIP_UI_APP has been set to true in the environment"
      echo "Artee VPN UI installation will be omitted based on your preference"
    fi
fi

# Identify OS name and default package manager
if type uname >/dev/null 2>&1; then
	case "$(uname)" in
        Linux)
          OS_TYPE="linux"
          UNAME_OUTPUT="$(uname -a)"
          if echo "$UNAME_OUTPUT" | grep -qi "synology"; then
            OS_NAME="synology"
            INSTALL_DIR="/usr/local/bin"
            PACKAGE_MANAGER="bin"
            SKIP_UI_APP=true
          else
            if [ -f /etc/os-release ]; then
              OS_NAME="$(. /etc/os-release && echo "$ID")"
              INSTALL_DIR="/usr/bin"

              # Allow Artee VPN UI installation for x64 arch only
              if [ "$ARCH" != "amd64" ] && [ "$ARCH" != "arm64" ] \
                  && [ "$ARCH" != "x86_64" ];then
                  SKIP_UI_APP=true
                  echo "Artee VPN UI installation will be omitted as $ARCH is not a compatible architecture"
              fi

              # Allow Artee VPN UI installation for linux running desktop environment
              if [ -z "$XDG_CURRENT_DESKTOP" ];then
                  SKIP_UI_APP=true
                  echo "Artee VPN UI installation will be omitted as Linux does not run desktop environment"
              fi

              # Check the availability of a compatible package manager
              if check_use_bin_variable; then
                  PACKAGE_MANAGER="bin"
              elif [ -e /run/ostree-booted ]; then
                  if [ -x "$(command -v rpm-ostree)" ]; then
                      PACKAGE_MANAGER="rpm-ostree"
                      echo "The installation will be performed using rpm-ostree package manager"
                  elif [ -x "$(command -v bootc)" ]; then
                      echo "Detected bootc system without rpm-ostree." >&2
                      echo "Artee VPN cannot be installed via package manager on this system." >&2
                      echo "Options:" >&2
                      echo "  1. Install via Distrobox (instructions in the installation docs)" >&2
                      echo "  2. Rebuild your base image with rpm-ostree included" >&2
                      echo "  3. Bake Artee VPN into your Containerfile" >&2
                      exit 1
                  else
                      echo "Detected ostree-booted system without rpm-ostree or bootc." >&2
                      echo "Artee VPN cannot be installed automatically on this atomic system." >&2
                      echo "Please install Artee VPN by rebuilding your base image or use a supported package manager." >&2
                      exit 1
                  fi
              elif [ -x "$(command -v apt-get)" ]; then
                  PACKAGE_MANAGER="apt"
                  echo "The installation will be performed using apt package manager"
              elif [ -x "$(command -v dnf)" ]; then
                  PACKAGE_MANAGER="dnf"
                  echo "The installation will be performed using dnf package manager"
              elif [ -x "$(command -v yum)" ]; then
                  PACKAGE_MANAGER="yum"
                  echo "The installation will be performed using yum package manager"
              fi
            else
              echo "Unable to determine OS type from /etc/os-release"
              exit 1
            fi
          fi


		;;
		Darwin)
            OS_NAME="macos"
			OS_TYPE="darwin"
            INSTALL_DIR="/usr/local/bin"

            # Check the availability of a compatible package manager
            if check_use_bin_variable; then
                PACKAGE_MANAGER="bin"
            else
              PACKAGE_MANAGER="pkg"
            fi
		;;
	esac
fi

UPDATE_FLAG=$1

if [ "${UPDATE_Artee VPN}-x" = "true-x" ]; then
  UPDATE_FLAG="--update"
fi

case "$UPDATE_FLAG" in
    --update)
      update_Artee VPN
    ;;
    *)
      install_Artee VPN
esac

