#!/bin/bash
set -e

if ! which curl >/dev/null 2>&1; then
  echo "This script uses curl fetch OpenID configuration from IDP."
  echo "Please install curl and re-run the script https://curl.se/"
  echo ""
  exit 1
fi

if ! which jq >/dev/null 2>&1; then
  echo "This script uses jq to load OpenID configuration from IDP."
  echo "Please install jq and re-run the script https://stedolan.github.io/jq/"
  echo ""
  exit 1
fi

source setup.env
source base.setup.env

if ! which envsubst >/dev/null 2>&1; then
  echo "envsubst is needed to run this script"
  if [[ $(uname) == "Darwin" ]]; then
    echo "you can install it with homebrew (https://brew.sh):"
    echo "brew install gettext"
  else
    if which apt-get >/dev/null 2>&1; then
      echo "you can install it by running"
      echo "apt-get update && apt-get install gettext-base"
    else
      echo "you can install it by installing the package gettext with your package manager"
    fi
  fi
  exit 1
fi

if [[ "x-$Artee VPN_DOMAIN" == "x-" ]]; then
  echo Artee VPN_DOMAIN is not set, please update your setup.env file
  echo If you are migrating from old versions, you might need to update your variables prefixes from
  echo WIRETRUSTEE_.. TO Artee VPN_
  exit 1
fi

# Check if PostgreSQL is set as the store engine
if [[ "$Artee VPN_STORE_CONFIG_ENGINE" == "postgres" ]]; then
  # Exit if 'Artee VPN_STORE_ENGINE_POSTGRES_DSN' is not set
  if [[ -z "$Artee VPN_STORE_ENGINE_POSTGRES_DSN" ]]; then
    echo "Warning: Artee VPN_STORE_CONFIG_ENGINE=postgres but Artee VPN_STORE_ENGINE_POSTGRES_DSN is not set."
    echo "Please add the following line to your setup.env file:"
    echo 'Artee VPN_STORE_ENGINE_POSTGRES_DSN="host=<PG_HOST> user=<PG_USER> password=<PG_PASSWORD> dbname=<PG_DB_NAME> port=<PG_PORT>"'
    exit 1
  fi
  export Artee VPN_STORE_ENGINE_POSTGRES_DSN
fi

# Check if MySQL is set as the store engine
if [[ "$Artee VPN_STORE_CONFIG_ENGINE" == "mysql" ]]; then
  # Exit if 'Artee VPN_STORE_ENGINE_MYSQL_DSN' is not set
  if [[ -z "$Artee VPN_STORE_ENGINE_MYSQL_DSN" ]]; then
    echo "Warning: Artee VPN_STORE_CONFIG_ENGINE=mysql but Artee VPN_STORE_ENGINE_MYSQL_DSN is not set."
    echo "Please add the following line to your setup.env file:"
    echo 'Artee VPN_STORE_ENGINE_MYSQL_DSN="<username>:<password>@tcp(127.0.0.1:3306)/<database>"'
    exit 1
  fi
  export Artee VPN_STORE_ENGINE_MYSQL_DSN
fi

# local development or tests
if [[ $Artee VPN_DOMAIN == "localhost" || $Artee VPN_DOMAIN == "127.0.0.1" ]]; then
  export Artee VPN_MGMT_SINGLE_ACCOUNT_MODE_DOMAIN="Artee VPN.selfhosted"
  export Artee VPN_MGMT_API_ENDPOINT=http://$Artee VPN_DOMAIN:$Artee VPN_MGMT_API_PORT
  unset Artee VPN_MGMT_API_CERT_FILE
  unset Artee VPN_MGMT_API_CERT_KEY_FILE
fi

# if not provided, we generate a turn password
if [[ "x-$TURN_PASSWORD" == "x-" ]]; then
  export TURN_PASSWORD=$(openssl rand -base64 32 | sed 's/=//g')
fi

TURN_EXTERNAL_IP_CONFIG="#"

if [[ "x-$Artee VPN_TURN_EXTERNAL_IP" == "x-" ]]; then
  echo "discovering server's public IP"
  IP=$(curl -s -4 https://jsonip.com | jq -r '.ip')
  if [[ "x-$IP" != "x-" ]]; then
    TURN_EXTERNAL_IP_CONFIG="external-ip=$IP"
  else
    echo "unable to discover server's public IP"
  fi
else
  echo "${Artee VPN_TURN_EXTERNAL_IP}"| egrep '([0-9]{1,3}\.){3}[0-9]{1,3}$' > /dev/null
  if [[ $? -eq 0 ]]; then
    echo "using provided server's public IP"
    TURN_EXTERNAL_IP_CONFIG="external-ip=$Artee VPN_TURN_EXTERNAL_IP"
  else
    echo "provided Artee VPN_TURN_EXTERNAL_IP $Artee VPN_TURN_EXTERNAL_IP is invalid, please correct it and try again"
    exit 1
  fi
fi

export TURN_EXTERNAL_IP_CONFIG

# if not provided, we generate a relay auth secret
if [[ "x-$Artee VPN_RELAY_AUTH_SECRET" == "x-" ]]; then
  export Artee VPN_RELAY_AUTH_SECRET=$(openssl rand -base64 32 | sed 's/=//g')
fi

artifacts_path="./artifacts"
mkdir -p $artifacts_path

MGMT_VOLUMENAME="${VOLUME_PREFIX}${MGMT_VOLUMESUFFIX}"
SIGNAL_VOLUMENAME="${VOLUME_PREFIX}${SIGNAL_VOLUMESUFFIX}"
LETSENCRYPT_VOLUMENAME="${VOLUME_PREFIX}${LETSENCRYPT_VOLUMESUFFIX}"
# if volume with wiretrustee- prefix already exists, use it, else create new with Artee VPN-
OLD_PREFIX='wiretrustee-'
if docker volume ls | grep -q "${OLD_PREFIX}${MGMT_VOLUMESUFFIX}"; then
  MGMT_VOLUMENAME="${OLD_PREFIX}${MGMT_VOLUMESUFFIX}"
fi
if docker volume ls | grep -q "${OLD_PREFIX}${SIGNAL_VOLUMESUFFIX}"; then
  SIGNAL_VOLUMENAME="${OLD_PREFIX}${SIGNAL_VOLUMESUFFIX}"
fi
if docker volume ls | grep -q "${OLD_PREFIX}${LETSENCRYPT_VOLUMESUFFIX}"; then
  LETSENCRYPT_VOLUMENAME="${OLD_PREFIX}${LETSENCRYPT_VOLUMESUFFIX}"
fi

export MGMT_VOLUMENAME
export SIGNAL_VOLUMENAME
export LETSENCRYPT_VOLUMENAME

#backwards compatibility after migrating to generic OIDC with Auth0
if [[ -z "${Artee VPN_AUTH_OIDC_CONFIGURATION_ENDPOINT}" ]]; then

  if [[ -z "${Artee VPN_AUTH0_DOMAIN}" ]]; then
    # not a backward compatible state
    echo "Artee VPN_AUTH_OIDC_CONFIGURATION_ENDPOINT property must be set in the setup.env file"
    exit 1
  fi

  echo "It seems like you provided an old setup.env file."
  echo "Since the release of v0.8.10, we introduced a new set of properties."
  echo "The script is backward compatible and will continue automatically."
  echo "In the future versions it will be deprecated. Please refer to the documentation to learn about the changes http://Artee VPN.io/docs/getting-started/self-hosting"

  export Artee VPN_AUTH_OIDC_CONFIGURATION_ENDPOINT="https://${Artee VPN_AUTH0_DOMAIN}/.well-known/openid-configuration"
  export Artee VPN_USE_AUTH0="true"
  export Artee VPN_AUTH_AUDIENCE=${Artee VPN_AUTH0_AUDIENCE}
  export Artee VPN_AUTH_CLIENT_ID=${Artee VPN_AUTH0_CLIENT_ID}
fi

echo "loading OpenID configuration from ${Artee VPN_AUTH_OIDC_CONFIGURATION_ENDPOINT} to the openid-configuration.json file"
curl "${Artee VPN_AUTH_OIDC_CONFIGURATION_ENDPOINT}" -q -o ${artifacts_path}/openid-configuration.json

export Artee VPN_AUTH_AUTHORITY=$(jq -r '.issuer' ${artifacts_path}/openid-configuration.json)
export Artee VPN_AUTH_JWT_CERTS=$(jq -r '.jwks_uri' ${artifacts_path}/openid-configuration.json)
export Artee VPN_AUTH_TOKEN_ENDPOINT=$(jq -r '.token_endpoint' ${artifacts_path}/openid-configuration.json)
export Artee VPN_AUTH_DEVICE_AUTH_ENDPOINT=$(jq -r '.device_authorization_endpoint' ${artifacts_path}/openid-configuration.json)
export Artee VPN_AUTH_PKCE_AUTHORIZATION_ENDPOINT=$(jq -r '.authorization_endpoint' ${artifacts_path}/openid-configuration.json)

if [[ ! -z "${Artee VPN_AUTH_DEVICE_AUTH_CLIENT_ID}" ]]; then
  # user enabled Device Authorization Grant feature
  export Artee VPN_AUTH_DEVICE_AUTH_PROVIDER="hosted"
fi

if [ "$Artee VPN_TOKEN_SOURCE" = "idToken" ]; then
    export Artee VPN_AUTH_PKCE_USE_ID_TOKEN=true
fi

# Check if letsencrypt was disabled
if [[ "$Artee VPN_DISABLE_LETSENCRYPT" == "true" ]]; then
  export Artee VPN_DASHBOARD_ENDPOINT="https://$Artee VPN_DOMAIN:443"
  export Artee VPN_SIGNAL_ENDPOINT="https://$Artee VPN_DOMAIN:$Artee VPN_SIGNAL_PORT"
  export Artee VPN_RELAY_ENDPOINT="rels://$Artee VPN_DOMAIN:$Artee VPN_RELAY_PORT/relay"

  echo "Letsencrypt was disabled, the Https-endpoints cannot be used anymore"
  echo " and a reverse-proxy with Https needs to be placed in front of Artee VPN!"
  echo "The following forwards have to be setup:"
  echo "- $Artee VPN_DASHBOARD_ENDPOINT -http-> dashboard:80"
  echo "- $Artee VPN_MGMT_API_ENDPOINT/api -http-> management:$Artee VPN_MGMT_API_PORT"
  echo "- $Artee VPN_MGMT_API_ENDPOINT/management.ManagementService/ -grpc-> management:$Artee VPN_MGMT_API_PORT"
  echo "- $Artee VPN_SIGNAL_ENDPOINT/signalexchange.SignalExchange/ -grpc-> signal:80"
  echo "- $Artee VPN_RELAY_ENDPOINT/ -http-> relay:33080"
  echo "You most likely also have to change Artee VPN_MGMT_API_ENDPOINT in base.setup.env and port-mappings in docker-compose.yml.tmpl and rerun this script."
  echo " The target of the forwards depends on your setup. Beware of the gRPC protocol instead of http for management and signal!"
  echo "You are also free to remove any occurrences of the Letsencrypt-volume $LETSENCRYPT_VOLUMENAME"
  echo ""

  unset Artee VPN_LETSENCRYPT_DOMAIN
  unset Artee VPN_MGMT_API_CERT_FILE
  unset Artee VPN_MGMT_API_CERT_KEY_FILE
fi

if [[ -n "$Artee VPN_MGMT_API_CERT_FILE" && -n "$Artee VPN_MGMT_API_CERT_KEY_FILE" ]]; then
  export Artee VPN_SIGNAL_PROTOCOL="https"
fi

# Check if management identity provider is set
if [ -n "$Artee VPN_MGMT_IDP" ]; then
  EXTRA_CONFIG={}

  # extract extra config from all env prefixed with Artee VPN_IDP_MGMT_EXTRA_
  for var in ${!Artee VPN_IDP_MGMT_EXTRA_*}; do
    # convert key snake case to camel case
    key=$(
      echo "${var#Artee VPN_IDP_MGMT_EXTRA_}" | awk -F "_" \
        '{for (i=1; i<=NF; i++) {output=output substr($i,1,1) tolower(substr($i,2))} print output}'
    )
    value="${!var}"

   echo "$var"
    EXTRA_CONFIG=$(jq --arg k "$key" --arg v "$value" '.[$k] = $v' <<<"$EXTRA_CONFIG")
  done

  export Artee VPN_MGMT_IDP
  export Artee VPN_IDP_MGMT_CLIENT_ID
  export Artee VPN_IDP_MGMT_CLIENT_SECRET
  export Artee VPN_IDP_MGMT_EXTRA_CONFIG=$EXTRA_CONFIG
else
  export Artee VPN_IDP_MGMT_EXTRA_CONFIG={}
fi

IFS=',' read -r -a REDIRECT_URL_PORTS <<< "$Artee VPN_AUTH_PKCE_REDIRECT_URL_PORTS"
REDIRECT_URLS=""
for port in "${REDIRECT_URL_PORTS[@]}"; do
    REDIRECT_URLS+="\"http://localhost:${port}\","
done

export Artee VPN_AUTH_PKCE_REDIRECT_URLS=${REDIRECT_URLS%,}

# Remove audience for providers that do not support it
if [ "$Artee VPN_DASH_AUTH_USE_AUDIENCE" = "false" ]; then
    export Artee VPN_DASH_AUTH_AUDIENCE=none
    export Artee VPN_AUTH_PKCE_AUDIENCE=
fi

# Read the encryption key
if test -f 'management.json'; then
    encKey=$(jq -r  ".DataStoreEncryptionKey" management.json)
    if [[ "$encKey" != "null" ]]; then
        export Artee VPN_DATASTORE_ENC_KEY=$encKey

    fi
fi

env | grep Artee VPN

bkp_postfix="$(date +%s)"
if test -f "${artifacts_path}/docker-compose.yml"; then
    cp $artifacts_path/docker-compose.yml "${artifacts_path}/docker-compose.yml.bkp.${bkp_postfix}"
fi

if test -f "${artifacts_path}/management.json"; then
    cp $artifacts_path/management.json "${artifacts_path}/management.json.bkp.${bkp_postfix}"
fi

if test -f "${artifacts_path}/turnserver.conf"; then
    cp ${artifacts_path}/turnserver.conf "${artifacts_path}/turnserver.conf.bkp.${bkp_postfix}"
fi
envsubst <docker-compose.yml.tmpl >$artifacts_path/docker-compose.yml
envsubst <management.json.tmpl | jq . >$artifacts_path/management.json
envsubst <turnserver.conf.tmpl >$artifacts_path/turnserver.conf

