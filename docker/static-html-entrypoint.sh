#!/bin/sh
# Generate the runtime config consumed by index.html (via /config.js) BEFORE the
# static server starts. This lets a single built image be pointed at any API host
# by setting API_BASE_URL at container start, with no rebuild.
#
# API_BASE_URL unset/empty => same origin as the page (SPA and API behind one host).
set -e

ROOT_DIR="${ROOT_DIR:-/static}"

cat > "$ROOT_DIR/config.js" <<CONFIG
// Generated at container start by docker/static-html-entrypoint.sh
window.__GLUO_CONFIG__ = { API_BASE_URL: "${API_BASE_URL:-}" };
CONFIG

echo "gluo: wrote $ROOT_DIR/config.js (API_BASE_URL=\"${API_BASE_URL:-}\")"

exec "$@"
