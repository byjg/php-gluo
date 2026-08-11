// Runtime configuration for the Gluo frontend.
//
// In a container this file is REPLACED at start-up by
// docker/static-html-entrypoint.sh, which writes the API_BASE_URL environment
// variable into it. That lets a single built image be pointed at any API host
// without rebuilding the bundle.
//
// It is intentionally left without an API_BASE_URL here so local `npm run dev`
// and a plain `npm run build` fall back to the build-time VITE_API_BASE_URL
// (from .env). Do not hard-code a URL in this file.
window.__GLUO_CONFIG__ = window.__GLUO_CONFIG__ || {};
