#!/bin/sh
# Generate the runtime config consumed by index.html (via /config.js) BEFORE the
# static server starts. This lets a single built image be pointed at any API host
# by setting API_BASE_URL at container start, with no rebuild.
#
# API_BASE_URL unset/empty => same origin as the page (SPA and API behind one host).
#
# API_BASE_URL_HTTPS, when set, is used instead while the page itself is loaded over
# HTTPS. A browser refuses an HTTPS page that calls an HTTP API (mixed content), so a
# container serving both schemes needs an address for each.
set -e

ROOT_DIR="${ROOT_DIR:-/static}"

if [ -n "${API_BASE_URL_HTTPS:-}" ]; then
    API_BASE_URL_VALUE="location.protocol === 'https:' ? \"${API_BASE_URL_HTTPS}\" : \"${API_BASE_URL:-}\""
else
    API_BASE_URL_VALUE="\"${API_BASE_URL:-}\""
fi

cat > "$ROOT_DIR/config.js" <<CONFIG
// Generated at container start by docker/static-html-entrypoint.sh
window.__GLUO_CONFIG__ = { API_BASE_URL: ${API_BASE_URL_VALUE} };
CONFIG

echo "gluo: wrote $ROOT_DIR/config.js (API_BASE_URL=\"${API_BASE_URL:-}\", API_BASE_URL_HTTPS=\"${API_BASE_URL_HTTPS:-}\")"

exec "$@"
