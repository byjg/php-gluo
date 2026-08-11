// Resolve the API base URL. Runtime config (window.__GLUO_CONFIG__.API_BASE_URL,
// injected by /config.js at container start) wins so one built image can target
// any API host; if it is absent we fall back to the build-time VITE_API_BASE_URL
// (.env), then to the local docker-compose default. An explicit empty string in
// the runtime config means "same origin as the page".
function resolveBaseUrl() {
  const runtime = typeof window !== 'undefined' ? window.__GLUO_CONFIG__ : undefined;
  if (runtime && Object.prototype.hasOwnProperty.call(runtime, 'API_BASE_URL')) {
    return String(runtime.API_BASE_URL);
  }
  return import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080';
}

export const BASE_URL = resolveBaseUrl();

function decodeJwtPayload(t) {
  try {
    const payload = t.split('.')[1];
    if (!payload) return null;
    const base64 = payload.replace(/-/g, '+').replace(/_/g, '/');
    return JSON.parse(atob(base64));
  } catch {
    return null;
  }
}

async function parseResponseBody(res) {
  const text = await res.text();
  if (!text || res.status === 204) return null;

  const contentType = res.headers.get('content-type') || '';
  if (contentType.includes('application/json')) {
    return JSON.parse(text);
  }
  return text;
}

function getResponseMessage(data, fallback) {
  if (typeof data === 'string') return data;
  return data?.error?.message || data?.message || fallback;
}

export const token = {
  get: () => sessionStorage.getItem('jwt_token'),
  set: (t) => sessionStorage.setItem('jwt_token', t),
  clear: () => sessionStorage.removeItem('jwt_token'),
  isExpired: () => {
    const t = sessionStorage.getItem('jwt_token');
    if (!t) return true;
    const payload = decodeJwtPayload(t);
    if (!payload?.exp) return true;
    return payload.exp < Date.now() / 1000;
  },
  expiresInSec: () => {
    const t = sessionStorage.getItem('jwt_token');
    if (!t) return 0;
    const payload = decodeJwtPayload(t);
    if (!payload?.exp) return 0;
    return payload.exp - Date.now() / 1000;
  },
};

// Injected once at startup (see App) so the requester can surface cross-cutting
// auth failures (expired session / insufficient role) without every page repeating it.
let onAuthError = null;
export function setAuthErrorHandler(fn) {
  onAuthError = fn;
}

async function apiFetch(path, options = {}) {
  const { skipAuthError, ...fetchOptions } = options;
  const headers = { ...(fetchOptions.headers || {}) };
  let authenticated = false;

  const currentToken = token.get();
  if (currentToken && !token.isExpired()) {
    // Transparently refresh when less than 5 minutes remain.
    if (token.expiresInSec() < 300) {
      try {
        const refreshRes = await fetch(`${BASE_URL}/refreshtoken`, {
          method: 'POST',
          headers: { Authorization: `Bearer ${currentToken}`, 'Content-Type': 'application/json' },
        });
        if (refreshRes.ok) {
          const data = await refreshRes.json();
          if (data.token) token.set(data.token);
        }
      } catch {
        // Ignore refresh errors; proceed with the current token.
      }
    }
    headers['Authorization'] = `Bearer ${token.get()}`;
    authenticated = true;
  }

  const res = await fetch(`${BASE_URL}${path}`, { ...fetchOptions, headers });

  // Centralised auth-error capture. 403 always means "insufficient role";
  // 401 on an authenticated request means the session expired (a 401 on the
  // login/refresh calls themselves has no token attached and is left to the caller).
  if (onAuthError && !skipAuthError) {
    if (res.status === 403) onAuthError(403, res);
    else if (res.status === 401 && authenticated) onAuthError(401, res);
  }

  return res;
}

async function request(path, options = {}) {
  const res = await apiFetch(path, options);
  const data = await parseResponseBody(res);
  if (!res.ok) {
    throw new Error(getResponseMessage(data, `Request failed with status ${res.status}`));
  }
  return data;
}

function jsonOptions(method, body, opts = {}) {
  return {
    ...opts,
    method,
    headers: { 'Content-Type': 'application/json', ...(opts.headers || {}) },
    body: JSON.stringify(body),
  };
}

export const api = {
  request,
  postJson: (path, body, opts) => request(path, jsonOptions('POST', body, opts)),
  putJson: (path, body, opts) => request(path, jsonOptions('PUT', body, opts)),
  post: (path, body, opts) => apiFetch(path, jsonOptions('POST', body, opts)),
  get: (path, opts) => apiFetch(path, { method: 'GET', ...opts }),
  put: (path, body, opts) => apiFetch(path, jsonOptions('PUT', body, opts)),
  del: (path, opts) => apiFetch(path, { method: 'DELETE', ...opts }),
};
