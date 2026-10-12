---
sidebar_position: 190
title: Frontend (Vite)
---

# Frontend (Vite + React)

A Gluo project can ship an optional single-page frontend in the `frontend/` folder. It is a
**React 19 + Vite 6 + Tailwind** app that talks to the API over JWT, with login,
password-reset, dashboard and profile screens already wired.

The frontend is installed when you answer **yes** to *Install Frontend* during
`composer create-project` (or set `"install_frontend": true` in `setup.json`). Answer no
and the `frontend/` folder, its Docker image and the `frontend` compose service are all removed —
leaving a pure API project.

## Layout

```
frontend/
├── package.json  vite.config.js  tailwind.config.js
├── .env.example                     # VITE_API_BASE_URL (build-time)
├── public/config.js                 # runtime API_BASE_URL (replaced in-container)
└── src/
    ├── lib/api.js                   # fetch wrapper: JWT storage + auto-refresh
    ├── context/AuthContext.jsx      # auth state (sessionStorage)
    ├── components/                   # AppNav + small UI primitives
    └── pages/
        ├── auth/Login.jsx
        ├── auth/ForgotPassword.jsx   # 3-step reset flow
        ├── dashboard/Dashboard.jsx
        ├── dashboard/Profile.jsx     # GET/PUT /profile
        └── examples/                 # gated by install_examples
            ├── ProjectsList.jsx      ProjectDetail.jsx   NotesWidget.jsx
```

## Local development

```bash
cd frontend
cp .env.example .env      # point VITE_API_BASE_URL at your API (default http://localhost:8080)
npm install
npm run dev               # Vite dev server on http://localhost:3000
```

The API's dev CORS allowlist (`CORS_SERVERS` in `config/dev/credentials.env`) already
permits the Vite origin. In **production**, set `CORS_SERVERS` to the exact origin the SPA
is served from.

## Configuring the API URL

There are two ways to tell the SPA where the API lives, for two different moments:

| | Where | When | Used by |
|---|---|---|---|
| `VITE_API_BASE_URL` | `frontend/.env` | **build** (baked into the bundle) | `npm run dev`, `npm run build` |
| `API_BASE_URL` | container env | **container start** (written to `/config.js`) | the Docker image |
| `API_BASE_URL_HTTPS` | container env | **container start** (written to `/config.js`) | the Docker image, when the page is loaded over HTTPS |

The Docker image (`docker/Dockerfile-html`) is built **once** and configured **per
environment**. Its entrypoint (`docker/static-html-entrypoint.sh`) writes the
`API_BASE_URL` environment variable into `/static/config.js` before the static server
starts; `index.html` loads that `/config.js` before the app boots, and `src/lib/api.js`
prefers it over the build-time value. So the same image can point at any API host without
rebuilding:

```bash
docker run -e API_BASE_URL=https://api.example.com -p 7080:8080 gluo-frontend:dev
```

In `docker-compose.yml` the `frontend` service sets `API_BASE_URL: http://localhost:8080` (the
host-reachable API). Leave `API_BASE_URL` empty to serve the API from the **same origin**
as the SPA (e.g. behind one reverse proxy). Resolution order in `src/lib/api.js`:
runtime `/config.js` → build-time `VITE_API_BASE_URL` → `http://localhost:8080`.

`API_BASE_URL_HTTPS` is optional. When it is set, the SPA uses it instead of
`API_BASE_URL` whenever its own page was loaded over HTTPS, so a container that serves
both schemes can give each the matching API address. The compose file sets it to
`https://localhost:8443`; see [HTTPS](#https).

## How auth works

- `src/lib/api.js` keeps the JWT in `sessionStorage` and attaches `Authorization: Bearer …`
  to every request. When fewer than 5 minutes remain it transparently calls
  `POST /refreshtoken` and swaps the token.
- `AuthContext` exposes `login/logout/isAuthenticated`; protected routes redirect to
  `/login` when the token is missing or expired.

The screens map onto the API endpoints: `POST /login`, `POST /refreshtoken`,
`POST /login/resetrequest` → `/login/confirmcode` → `/login/resetpassword`, and
`GET`/`PUT /profile`.

## Running with Docker Compose

`docker compose up -d` builds `docker/Dockerfile-html`: a Node stage runs `npm run build`,
then the static `dist/` output is served by [`byjg/static-httpserver`](https://github.com/byjg/docker-static-httpserver)
in SPA mode. The SPA is published on **http://localhost:7080** (the API stays on
`:8080`).

> `VITE_API_BASE_URL` is baked in at **build** time. For a production image, build with the
> real API URL (e.g. `--build-arg` or an `.env` committed to your deploy pipeline).

### HTTPS

The same container also serves the SPA over HTTPS, published on
**https://localhost:7443**. `byjg/static-httpserver` generates a self-signed certificate
when it starts, so nothing has to be configured; the browser shows its usual warning for
a certificate it does not know.

```bash
curl -k https://localhost:7443/
```

To use your own certificate, mount a directory with `cert.pem` and `key.pem` at `/certs`:

```yaml
  frontend:
    volumes:
      - ./certs:/certs:ro
```

See the [static-httpserver TLS guide](https://opensource.byjg.com/docs/devops/docker-static-httpserver/tls)
for other file names (Let's Encrypt's `fullchain.pem` / `privkey.pem`) and for terminating
TLS in front of the API.

#### The API over HTTPS

An HTTPS page may not call an HTTP API: browsers block it as mixed content, with
`localhost` as the only exception. So the API is published over HTTPS as well, on
**https://localhost:8443**, and the SPA uses that address when its own page is HTTPS:

| Page | API it calls |
|---|---|
| http://localhost:7080 | http://localhost:8080 (`API_BASE_URL`) |
| https://localhost:7443 | https://localhost:8443 (`API_BASE_URL_HTTPS`) |

The API does not get a certificate of its own. The static server keeps the one it
generates in the `certs` volume, the API container mounts the same volume, and nginx is
pointed at it:

```yaml
  rest:
    ports:
      - "8443:443"
    volumes:
      - certs:/certs:ro
    environment:
      - NGINX_SSL_CERT=/certs/selfsigned-cert.pem
      - NGINX_SSL_CERT_KEY=/certs/selfsigned-key.pem
    depends_on:
      - frontend
```

Both addresses therefore present the same certificate, and there is one file to trust:
copy it out with `docker compose cp frontend:/certs/selfsigned-cert.pem .` and add it
to your browser or system store to get rid of the warning on both.

Things to know:

- **Accept the certificate for the API too.** A browser shows its warning page for the
  address you open, not for the calls a page makes in the background. If login fails
  on the HTTPS page with a network error, open https://localhost:8443 once and accept
  the certificate there, or trust the file as described above.
- **The two containers must run as the same user.** The static server writes the key
  readable by its owner only, uid 1000. `docker/Dockerfile` maps the API's user to
  `USER_ID`, which defaults to 1000; if you build with another `USER_ID`, nginx cannot
  read the key and the API container does not start.
- **The frontend starts first**, because nginx reads the certificate when it starts.
  If the API container exits on a very first `docker compose up`, the certificate was
  not written yet: run `docker compose up -d` again.
- **nginx keeps the certificate it started with.** The static server writes a new one
  when the old one is close to expiring or its addresses changed; run
  `docker compose restart rest` after that.
- **To use your own certificate**, mount `cert.pem` and `key.pem` in the volume and point
  `NGINX_SSL_CERT` / `NGINX_SSL_CERT_KEY` at those two names.

All of this is part of the `frontend` service's setup. A project created without the
frontend has no certificate to share, and its API is published over HTTP only.

## Removing the example screens

The Projects/Tasks/Notes pages under `src/pages/examples/` are removed when
`install_examples=false`; their imports, routes and nav entries are wrapped in
`{/* >>> examples */}` … `{/* <<< examples */}` markers so the generator can strip them
cleanly, leaving just the auth + dashboard + profile shell.

## Not included

Public self-registration is intentionally out of scope for the starter shell (the API has
no `/register` endpoint by default). Add it as your first real feature — a controller plus
the matching frontend page — following the [REST Controllers](rest-controllers.md) guide.
