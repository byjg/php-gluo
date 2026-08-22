---
sidebar_position: 305
title: Repository Layout
---

# Repository Layout

A Gluo project holds **two deployable applications** in one repository:

- the **PHP REST API**, whose application root is the repository root;
- an optional **Vite + React SPA** in `frontend/`, with its own `package.json` and container.

```
my-api/
├── composer.json         # the single manifest — require, autoload, scripts
├── vendor/
├── src/                  # Model, Repository, Service, Controller
├── config/               # dev / test / staging / prod + ConfigBootstrap.php
├── db/                   # migrations + base.sql
├── public/               # the API docroot: app.php, index.html, docs/
├── templates/            # email + scriptify templates
├── tests/
├── builder/              # code generator + migration entry points
├── phpunit.xml.dist
├── psalm.xml
│
├── frontend/             # the SPA — its own package.json, node_modules, build
│   ├── src/
│   └── public/           # Vite static passthrough (NOT a docroot)
│
├── docker/               # Dockerfile (API) + Dockerfile-html (frontend)
├── docker-compose.yml    # API :8080, frontend :7080, MySQL :3306
└── docs/
```

## Why the PHP app sits at the root

There is exactly one `composer.json`, at the repository root, and it is the real one:
`require`, `require-dev`, the PSR-4 autoload (`src/`, `builder/`, `tests/`) and every
script. This is the same shape a Laravel project has, so `composer test`,
`vendor/bin/phpunit` and `vendor/bin/psalm` all run from the root with no
`--working-dir` indirection.

It is also where Composer needs it: `composer create-project byjg/gluo` reads the
manifest from the **root** of the package, so the root manifest and the application
manifest being the same file is what keeps the starter installable.

## The two `public/` directories

They are not the same kind of thing, and the distinction matters:

| | `public/` | `frontend/public/` |
|---|---|---|
| Role | the **API docroot** — nginx serves it (`NGINX_ROOT=/srv/public`) | **Vite static passthrough** — copied verbatim into the build |
| Served directly? | yes | no — the served root is the built `dist/`, mounted at `/static` |
| Holds | `app.php` (front controller), `index.html`, `robots.txt`, `docs/` (Swagger UI) | `config.js`, rewritten at container start |

If you are looking for "the web root", it is `public/`. `frontend/public/` is a build
input belonging to the Vite project.

## Two containers, two front doors

`docker-compose.yml` runs the API on **:8080** and the SPA on **:7080**. Each has its own
`index.html` — `public/index.html` is a landing page for the API, `frontend/index.html`
is the SPA shell — and on the default two-container setup they never collide.

They *would* collide if you served both from one host. The frontend image supports that
(`API_BASE_URL=""` means "same origin as the page"), so if you deploy that way, decide
which `index.html` owns `/`, and note that `public/robots.txt` is `Disallow: /` — correct
for an API, but it would deindex a same-origin SPA.

## Removing the frontend

Answering **no** to *Install Frontend* during `composer create-project` removes `frontend/`,
its Dockerfile and entrypoint, this guide, and the marker-wrapped `frontend` service in
`docker-compose.yml`, leaving a pure API project whose layout is unchanged.
