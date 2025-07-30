#!/bin/sh

echo "
███████ ████████ ██████  ███████  █████  ███    ███ ██ ███    ██  ██████      ██████  ██      ██    ██ ███████
██         ██    ██   ██ ██      ██   ██ ████  ████ ██ ████   ██ ██           ██   ██ ██      ██    ██ ██
███████    ██    ██████  █████   ███████ ██ ████ ██ ██ ██ ██  ██ ██   ███     ██████  ██      ██    ██ ███████
     ██    ██    ██   ██ ██      ██   ██ ██  ██  ██ ██ ██  ██ ██ ██    ██     ██      ██      ██    ██      ██
███████    ██    ██   ██ ███████ ██   ██ ██      ██ ██ ██   ████  ██████      ██      ███████  ██████  ███████
"

set -e
set -e
info() {
    { set +x; } 2> /dev/null
    echo '[INFO] ' "$@"
}
warning() {
    { set +x; } 2> /dev/null
    echo '[WARNING] ' "$@"
}
fatal() {
    { set +x; } 2> /dev/null
    echo '[ERROR] ' "$@" >&2
    exit 1
}

echo ""
echo "***********************************************************"
echo " Starting Streaming-Plus-Addon Docker Container            "
echo "***********************************************************"

#Pulisco le directory che devo ricreare
rm -rf $SP_DATA_PATH/app/sessions
rm -rf $SP_DATA_PATH/app/cache
rm -rf $SP_DATA_PATH/app/response
rm -rf $SP_DATA_PATH/app/logs
rm -rf $SP_DATA_PATH/nginx/logs

#Creo le directory che mi servono
info "-- Creating the necessary folders if they do not already exist"
mkdir -p $SP_DATA_PATH/app/sessions
mkdir -p $SP_DATA_PATH/app/cache
mkdir -p $SP_DATA_PATH/app/response
mkdir -p $SP_DATA_PATH/app/logs
mkdir -p $SP_DATA_PATH/nginx/logs

#Configurazione Laravel
info "-- Configuring the basic dependencies of the app"
if [ ! -f $SP_DATA_PATH/app/database.sqlite ]; then
  cp /var/www/database/database.sqlite $SP_DATA_PATH/app/database.sqlite
fi
composer install --no-interaction --prefer-dist --optimize-autoloader 2> /dev/null
php artisan migrate 2>&1 > /dev/null
#php artisan queue:clear 2>&1 > /dev/null

#Configurazione MediaFlowProxy
API_PASSWORD=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 32)
export API_PASSWORD="$API_PASSWORD"

#Configurazione Playwright
export PLAYWRIGHT_BROWSERS_PATH="$SP_DATA_PATH/playwright"
/var/python/venv/bin/playwright install --with-deps chromium 2>&1 > /dev/null

#Cambio i permessi nelle cartelle data
info "-- Changing permissions to folders and files"
chown -R $USER_NAME:$GROUP_NAME $SP_DATA_PATH

echo ""
echo "***********************************************************"
echo " Starting Streaming-Plus-Addon Services                    "
echo "***********************************************************"

## Start Supervisord
supervisord -c /etc/supervisor/supervisord.conf