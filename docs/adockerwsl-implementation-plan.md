# ADockerWSL Laravel Demonstration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a classroom-ready Docker Desktop and WSL demonstration consisting of a Laravel/Vue portal named Unify, three minimal Laravel/Vue project sites, and four isolated databases hosted by one PostgreSQL container.

**Architecture:** One root `docker.yaml` manages five runtime services: `unify`, `project1`, `project2`, `project3`, and `postgres`. Each Laravel application is built as its own multi-stage PHP/Apache image; Vue and daisyUI are compiled during the image build, so Node is not a runtime service. Unify checks each project's internal `/health` endpoint and exposes status to its browser UI, while each application connects to its own logical database inside the shared PostgreSQL server.

**Tech Stack:** Docker Desktop with WSL 2 integration, Docker Compose, Laravel 13, PHP 8.4 with Apache, Vue 3, Vite, Tailwind CSS, daisyUI, PostgreSQL 17, Git, GitHub Actions, and GitHub Container Registry (GHCR).

**Spec:** `docs/adockerwsl-implementation-plan.md#design-specification`

## Global Constraints

- Use exactly one root Compose file named `docker.yaml`.
- Keep exactly five runtime services: `unify`, `project1`, `project2`, `project3`, and `postgres`.
- Publish Unify on `localhost:8080`, Project 1 on `localhost:8081`, Project 2 on `localhost:8082`, Project 3 on `localhost:8083`, and PostgreSQL on `localhost:5433`.
- Do not modify, uninstall, or depend on the PostgreSQL server installed on Windows.
- Windows pgAdmin connects to the containerized database through `localhost:5433`.
- Laravel containers connect to PostgreSQL through the Compose service name `postgres` and container port `5432`.
- Use one PostgreSQL server with four databases and four non-superuser application roles: `unify_db`, `project1_db`, `project2_db`, and `project3_db`.
- Keep the sites navigation-only. Do not add project-to-project APIs, authentication, queues, Redis, WebSockets, or business workflows.
- Every project must expose `GET /health`; the response must verify both Laravel and that project's database connection.
- Unify must stay usable when any project container is stopped or unhealthy.
- Unify must refresh project availability every five seconds and render unavailable cards grey and non-navigable.
- Use PHP 8.4 inside application images. Do not depend on the currently broken WSL `laravel` global command.
- Run pre-image Artisan commands through `composer:2`; WSL PHP 8.3.6 cannot execute the generated lockfiles, whose Symfony 8.1 packages require PHP 8.4.1 or newer.
- Use Laravel 13, Vue 3, Vite, Tailwind CSS, and daisyUI in all four applications.
- Do not run Docker cleanup commands that can affect unrelated containers, images, networks, or volumes.
- Publish the GitHub repository as public under the name `adockerwsl`.
- Publish public staging images to GHCR only after tests and the local Compose verification pass.

---

## Design Specification

### Teaching objective

The finished repository demonstrates these Docker concepts without hiding them behind a large framework-specific development environment:

1. One Compose project can coordinate several independently built applications.
2. Containers find one another through Compose DNS service names rather than fixed IP addresses.
3. A single database server container can own multiple isolated logical databases.
4. Named volumes preserve database data when containers are recreated.
5. Host ports and container ports serve different audiences.
6. Container health can affect another application's UI without stopping that application.
7. A CI workflow can build immutable images which are later pulled and run locally.

### Runtime map

| Service | Image | Host access | Internal dependencies | Database |
|---|---|---|---|---|
| `unify` | `adockerwsl-unify` | `http://localhost:8080` | `postgres`, project health URLs | `unify_db` |
| `project1` | `adockerwsl-project1` | `http://localhost:8081` | `postgres` | `project1_db` |
| `project2` | `adockerwsl-project2` | `http://localhost:8082` | `postgres` | `project2_db` |
| `project3` | `adockerwsl-project3` | `http://localhost:8083` | `postgres` | `project3_db` |
| `postgres` | `postgres:17-bookworm` | `localhost:5433` | None | Hosts all four databases |

The browser navigates to published `localhost` ports. Containers never use those host ports for internal communication: Unify checks `http://project1/health`, `http://project2/health`, and `http://project3/health`, while every Laravel application uses `postgres:5432` for its database connection.

### Application behavior

Each project page is a Vue single-page presentation mounted inside one Laravel Blade shell. The fixed page copy is:

- `Project 1 — Future Work`
- `Project 2 — Future Work`
- `Project 3 — Future Work`

Each page contains one small inline SVG icon, its title, a short Docker demonstration sentence, and a link back to `http://localhost:8080`. No project stores or exchanges business data.

Unify renders one card per project. Its browser requests `GET /api/projects/status` from Unify on initial load and every five seconds. The endpoint returns this stable JSON shape:

```json
{
  "projects": [
    {
      "id": "project1",
      "name": "Project 1",
      "subtitle": "Future Work",
      "icon": "briefcase",
      "url": "http://localhost:8081",
      "available": true
    }
  ]
}
```

Unify performs health requests on the server side, uses a one-second timeout, treats exceptions and non-2xx responses as unavailable, and always returns a successful status-list response to the browser. A failed project card uses disabled styling, `aria-disabled="true"`, has no active navigation target, and displays `Unavailable`. A recovered project becomes active on the next poll.

Every Laravel application exposes this successful health contract:

```json
{
  "status": "ok",
  "service": "project1",
  "database": "connected"
}
```

The `service` value changes for each application. If its database query fails, the endpoint returns HTTP `503` with:

```json
{
  "status": "error",
  "service": "project1",
  "database": "unavailable"
}
```

### Database isolation

The PostgreSQL image runs one server process backed by the named volume `postgres_data`. Its first-start initialization script creates these role-to-database ownership mappings:

| Role | Owned database |
|---|---|
| `unify_app` | `unify_db` |
| `project1_app` | `project1_db` |
| `project2_app` | `project2_db` |
| `project3_app` | `project3_db` |

Each Laravel service receives only its own role, password, and database name. The application roles are not PostgreSQL superusers and receive no ownership of the other applications' databases. PostgreSQL initialization scripts run only when the data volume is empty; the presentation runbook must call this out explicitly.

### Image design

Each application owns a Dockerfile with three stages:

1. A Composer stage installs production PHP dependencies and creates optimized autoload files.
2. A Node stage installs locked npm dependencies and builds Vue/Tailwind/daisyUI assets.
3. A `php:8.4-apache-bookworm` stage installs `pdo_pgsql`, enables Apache rewrite support, copies the application and compiled assets, and runs a small entrypoint.

The entrypoint waits for the assigned database, runs `php artisan migrate --force`, caches Laravel configuration, and then starts Apache. Apache serves only the Laravel `public/` directory. Route caching is intentionally omitted because the minimal applications use closure routes.

### Local and staging modes

Every application service contains both `build` and `image` fields. The image prefix and tag use Compose environment interpolation:

```yaml
image: ${IMAGE_PREFIX:-local}/adockerwsl-unify:${IMAGE_TAG:-development}
```

Local mode builds before starting:

```bash
docker compose -f docker.yaml up --build -d
```

Staging mode exports the authenticated GitHub account's lowercase namespace, pulls public GHCR images, and forbids local builds:

```bash
GITHUB_OWNER="$(gh api user --jq .login | tr '[:upper:]' '[:lower:]')"
export IMAGE_PREFIX="ghcr.io/${GITHUB_OWNER}"
export IMAGE_TAG=staging
docker compose -f docker.yaml pull unify project1 project2 project3 postgres
docker compose -f docker.yaml up --no-build -d
```

The `staging` branch triggers `.github/workflows/staging.yml`. A verification job builds the Compose project, runs the four Laravel test suites, starts the stack, and checks all pages and health endpoints. Only then does a matrix job publish four `:staging` images plus immutable `:sha-<commit>` tags to GHCR.

### Repository policy

The current Python HTTP-chain prototype is intentionally removed before the first application commit. Git is initialized locally with `main` as the default branch. The public remote is created later as `adockerwsl` after the application passes local verification. The WSL GitHub CLI is currently absent, so remote creation has an explicit user authentication checkpoint.

---

## Planned File Structure

```text
.
├── .env.example
├── .github/
│   └── workflows/
│       └── staging.yml
├── .gitignore
├── AGENTS.md
├── README.md
├── docker.yaml
├── docker/
│   └── postgres/
│       └── init-databases.sh
├── docs/
│   ├── adockerwsl-implementation-plan.md
│   └── presentation-runbook.md
├── unify/
│   ├── Dockerfile
│   ├── docker/
│   │   ├── apache.conf
│   │   └── entrypoint.sh
│   ├── app/Http/Controllers/ProjectStatusController.php
│   ├── app/Services/ProjectStatusService.php
│   ├── config/projects.php
│   ├── resources/css/app.css
│   ├── resources/js/app.js
│   ├── resources/js/components/App.vue
│   ├── resources/views/app.blade.php
│   ├── routes/web.php
│   └── tests/Feature/ProjectStatusTest.php
├── project1/
│   ├── Dockerfile
│   ├── docker/apache.conf
│   ├── docker/entrypoint.sh
│   ├── config/project.php
│   ├── resources/css/app.css
│   ├── resources/js/app.js
│   ├── resources/js/components/App.vue
│   ├── resources/views/app.blade.php
│   ├── routes/web.php
│   └── tests/Feature/ProjectPageTest.php
├── project2/
│   ├── Dockerfile
│   ├── docker/apache.conf
│   ├── docker/entrypoint.sh
│   ├── config/project.php
│   ├── resources/css/app.css
│   ├── resources/js/app.js
│   ├── resources/js/components/App.vue
│   ├── resources/views/app.blade.php
│   ├── routes/web.php
│   └── tests/Feature/ProjectPageTest.php
└── project3/
    ├── Dockerfile
    ├── docker/apache.conf
    ├── docker/entrypoint.sh
    ├── config/project.php
    ├── resources/css/app.css
    ├── resources/js/app.js
    ├── resources/js/components/App.vue
    ├── resources/views/app.blade.php
    ├── routes/web.php
    └── tests/Feature/ProjectPageTest.php
```

Laravel-generated framework files remain inside each application but are omitted from this map. Tasks 2–5 list every custom file that replaces or extends the generated framework.

---

### Task 1: Retire the Python prototype and establish repository guidance

**Files:**
- Delete: `project1/app.py`
- Delete: `project1/test_app.py`
- Delete: `project1/Dockerfile`
- Delete: `project2/app.py`
- Delete: `project2/test_app.py`
- Delete: `project2/Dockerfile`
- Delete: `project3/app.py`
- Delete: `project3/test_app.py`
- Delete: `project3/Dockerfile`
- Delete: old Python `__pycache__` files under `project1`, `project2`, and `project3`
- Replace: `docker.yaml`
- Replace: `.env`
- Create: `.gitignore`
- Replace: `AGENTS.md`

**Interfaces:**
- Consumes: The approved design specification in this document.
- Produces: A clean repository root, a safe ignore policy, and project-specific agent guidance used by every later task.

- [x] **Step 1: Stop only the existing Compose project**

Run:

```bash
docker compose -f docker.yaml down
```

Expected: the three `docker-http-chain-*` containers and their Compose network are removed. Do not add `--rmi`, `--volumes`, or run a global Docker prune.

- [x] **Step 2: Remove the three Python application directories and obsolete root configuration**

Resolve and verify the exact targets first:

```bash
pwd
find project1 project2 project3 -maxdepth 2 -type f -print | sort
```

Expected working directory: `/home/valhaimerd/projects`. Delete only `project1`, `project2`, `project3`, the old `docker.yaml`, and the old root `.env`; preserve `docs/adockerwsl-implementation-plan.md`.

- [x] **Step 3: Create the repository ignore policy**

Create `.gitignore` with:

```gitignore
.env
.env.*
!.env.example

**/vendor/
**/node_modules/
**/.env
**/.env.*
!**/.env.example
**/public/build/
**/storage/*.key
**/storage/framework/cache/data/*
**/storage/framework/sessions/*
**/storage/framework/views/*
**/storage/logs/*

.idea/
.vscode/
.worktrees/
.DS_Store
Thumbs.db
```

- [x] **Step 4: Rewrite repository guidance**

Replace `AGENTS.md` with:

```markdown
# ADockerWSL Project Guidance

## Purpose

- This is a classroom demonstration of Docker Desktop, WSL 2, Docker Compose, Laravel, Vue, daisyUI, PostgreSQL, GitHub Actions, and GHCR.
- Read `docs/adockerwsl-implementation-plan.md` before changing architecture or behavior.
- Keep the demonstration small, visible in Docker Desktop, and runnable from a WSL terminal.

## Architecture

- Use one root Compose file named `docker.yaml`.
- Keep five runtime services: `unify`, `project1`, `project2`, `project3`, and `postgres`.
- Preserve host ports 8080, 8081, 8082, 8083, and 5433.
- Use Compose service names for internal traffic; never use fixed container IP addresses.
- Keep four separate databases and application roles inside the single PostgreSQL container.
- Keep Project 1–3 navigation-only and preserve Unify's automatic health-card behavior.

## Working rules

- Follow the approved tasks and contracts in `docs/adockerwsl-implementation-plan.md`.
- Use `docker compose -f docker.yaml` in documented commands.
- Do not use the Windows PostgreSQL server as an application dependency.
- Do not add services, authentication, APIs between projects, or unrelated infrastructure without approval.
- Never commit `.env`, credentials, Composer `vendor`, npm `node_modules`, or built Vite assets.
- Do not run Docker cleanup commands that can affect unrelated projects.
- Use test-driven development for application behavior and make the smallest task-scoped change.

## Verification

- Run Laravel tests through Docker because WSL PHP 8.3 cannot execute the PHP 8.4.1+ lockfiles. Before application images exist, use the `composer:2` commands in the implementation plan; afterward use its exact `docker compose exec` commands.
- Run `npm run build` after changing Vue, Vite, Tailwind, or daisyUI files.
- Run `docker compose -f docker.yaml config` after changing Compose configuration.
- For integration changes, verify every `/health` endpoint and Unify's `/api/projects/status` endpoint.
- Before completion, stop one project service, verify its Unify status becomes unavailable, restart it, and verify recovery.
```

- [x] **Step 5: Verify cleanup boundaries and guidance**

Run:

```bash
test -f docs/adockerwsl-implementation-plan.md
test -f AGENTS.md
test ! -e project1
test ! -e project2
test ! -e project3
rg -n "docker-http-chain|python app.py" AGENTS.md .gitignore || true
```

Expected: the first five checks succeed and the search prints no obsolete implementation references outside this historical plan.

- [x] **Step 6: Initialize Git and commit the clean planning baseline**

Run:

```bash
git init -b main
git add .gitignore AGENTS.md docs/adockerwsl-implementation-plan.md
git commit -m "docs: define ADockerWSL implementation plan"
```

Expected: Git creates the `main` branch and records only documentation and repository guidance.

---

### Task 2: Scaffold four consistent Laravel applications

**Files:**
- Create: `unify/` as a Laravel 13 application
- Create: `project1/` as a Laravel 13 application
- Create: `project2/` as a Laravel 13 application
- Create: `project3/` as a Laravel 13 application
- Modify: `unify/package.json`
- Modify: `project1/package.json`
- Modify: `project2/package.json`
- Modify: `project3/package.json`
- Modify: each application's `vite.config.js`
- Modify: each application's `resources/css/app.css`
- Modify: each application's `resources/js/app.js`
- Create: each application's `resources/js/components/App.vue`

**Interfaces:**
- Consumes: Four empty application paths from Task 1.
- Produces: Four Laravel 13 codebases with identical Vue 3, Tailwind, daisyUI, and Vite foundations.

- [x] **Step 1: Scaffold Laravel without using the broken global installer**

Run from the repository root:

```bash
for app in unify project1 project2 project3; do
  docker run --rm \
    --user "$(id -u):$(id -g)" \
    --volume "$PWD:/workspace" \
    --workdir /workspace \
    composer:2 create-project laravel/laravel:^13.0 "$app" --no-interaction
done
```

Expected: all four directories contain `artisan`, `composer.json`, and a Laravel 13 `composer.lock`.

- [x] **Step 2: Verify framework versions before customization**

Run:

```bash
for app in unify project1 project2 project3; do
  printf '%s: ' "$app"
  docker run --rm \
    --user "$(id -u):$(id -g)" \
    --volume "$PWD/$app:/app" \
    --workdir /app \
    --entrypoint php \
    composer:2 artisan --version
done
```

Expected: every line reports Laravel Framework 13.x. The command uses each project's dependencies, not the global `laravel` executable.

- [x] **Step 3: Install the common frontend dependencies**

Run:

```bash
for app in unify project1 project2 project3; do
  npm --prefix "$app" install vue
  npm --prefix "$app" install --save-dev @vitejs/plugin-vue tailwindcss @tailwindcss/vite daisyui
done
```

Expected: each `package.json` declares Vue and the three styling/build dependencies, and each application has a lockfile.

- [x] **Step 4: Configure Vite identically in all four applications**

Replace each application's `vite.config.js` with:

```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
});
```

- [x] **Step 5: Configure Tailwind and daisyUI identically in all four applications**

Replace each application's `resources/css/app.css` with:

```css
@import "tailwindcss";
@plugin "daisyui";

html {
    min-height: 100%;
    background: oklch(97% 0.014 254.604);
}

body {
    min-height: 100vh;
    margin: 0;
}
```

Replace each application's `resources/js/app.js` with:

```javascript
import '../css/app.css';
import { createApp } from 'vue';
import App from './components/App.vue';

createApp(App).mount('#app');
```

Create `resources/js/components/App.vue` in each application with this buildable baseline:

```vue
<template>
    <main class="grid min-h-screen place-items-center bg-base-200">
        <p class="text-lg text-base-content">Laravel container application</p>
    </main>
</template>
```

- [x] **Step 6: Verify all four framework and frontend baselines**

Run:

```bash
for app in unify project1 project2 project3; do
  docker run --rm \
    --user "$(id -u):$(id -g)" \
    --volume "$PWD/$app:/app" \
    --workdir /app \
    --entrypoint php \
    composer:2 artisan test
  npm --prefix "$app" run build
done
```

Expected: every generated Laravel suite passes and every Vite build exits successfully.

- [x] **Step 7: Commit the consistent application skeletons**

Run:

```bash
git add unify project1 project2 project3
git commit -m "chore: scaffold Laravel Vue applications"
```

Expected: source files and lockfiles are committed; ignored dependency and generated-asset directories are absent from the commit.

---

### Task 3: Implement the three minimal project pages and health contracts

**Files:**
- Create: `project1/config/project.php`
- Create: `project2/config/project.php`
- Create: `project3/config/project.php`
- Replace: each project's `routes/web.php`
- Create: each project's `resources/views/app.blade.php`
- Replace: each project's `resources/js/components/App.vue`
- Create: each project's `tests/Feature/ProjectPageTest.php`
- Delete: each project's generated `tests/Feature/ExampleTest.php`

**Interfaces:**
- Consumes: The Laravel/Vue foundations from Task 2 and a PostgreSQL connection named `pgsql`.
- Produces: `GET /` HTML shells and `GET /health` JSON endpoints used by Unify and Compose.

- [x] **Step 1: Write Project 1's failing feature tests**

Create `project1/tests/Feature/ProjectPageTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ProjectPageTest extends TestCase
{
    public function test_home_page_exposes_project_copy_to_vue(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Project 1')
            ->assertSee('Future Work');
    }

    public function test_health_reports_the_service_and_database(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'service' => 'project1',
                'database' => 'connected',
            ]);
    }

    public function test_health_returns_503_when_the_database_is_unavailable(): void
    {
        DB::shouldReceive('select')->once()->andThrow(new RuntimeException('offline'));

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertExactJson([
                'status' => 'error',
                'service' => 'project1',
                'database' => 'unavailable',
            ]);
    }
}
```

Create all three test files from the shown test contract using these exact values; no other assertions differ:

| File | Visible name | Health service value |
|---|---|---|
| `project1/tests/Feature/ProjectPageTest.php` | `Project 1` | `project1` |
| `project2/tests/Feature/ProjectPageTest.php` | `Project 2` | `project2` |
| `project3/tests/Feature/ProjectPageTest.php` | `Project 3` | `project3` |

- [x] **Step 2: Run the focused tests to verify they fail**

Run:

```bash
for app in project1 project2 project3; do
  docker run --rm \
    --user "$(id -u):$(id -g)" \
    --volume "$PWD/$app:/app" \
    --workdir /app \
    --entrypoint php \
    composer:2 artisan test --filter=ProjectPageTest
done
```

Expected: failures because the project configuration, page, and health contracts do not exist.

- [x] **Step 3: Create the exact per-project configuration**

Create `project1/config/project.php`:

```php
<?php

return [
    'id' => 'project1',
    'name' => 'Project 1',
    'subtitle' => 'Future Work',
    'description' => 'A minimal Laravel application running in its own Docker container.',
    'unify_url' => env('UNIFY_PUBLIC_URL', 'http://localhost:8080'),
];
```

Create `project2/config/project.php` and `project3/config/project.php` with IDs and names changed to `project2` / `Project 2` and `project3` / `Project 3`; keep the other values identical.

- [x] **Step 4: Implement the page and database-aware health routes in every project**

Replace `project1/routes/web.php` with:

```php
<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app', ['project' => config('project')]);

Route::get('/health', function () {
    try {
        DB::select('SELECT 1');

        return response()->json([
            'status' => 'ok',
            'service' => config('project.id'),
            'database' => 'connected',
        ]);
    } catch (\Throwable) {
        return response()->json([
            'status' => 'error',
            'service' => config('project.id'),
            'database' => 'unavailable',
        ], 503);
    }
});
```

Use this exact route file in `project2` and `project3` as well; the service identity comes from each application's configuration.

- [x] **Step 5: Create the Blade shell in every project**

Create the same `resources/views/app.blade.php` in all three projects:

```blade
<!doctype html>
<html lang="en" data-theme="corporate">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="project-name" content="{{ $project['name'] }}">
    <meta name="project-subtitle" content="{{ $project['subtitle'] }}">
    <meta name="project-description" content="{{ $project['description'] }}">
    <meta name="unify-url" content="{{ $project['unify_url'] }}">
    <title>{{ $project['name'] }} — {{ $project['subtitle'] }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
```

- [x] **Step 6: Create the minimal Vue project page in every project**

Create the same `resources/js/components/App.vue` in all three projects:

```vue
<script setup>
const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content ?? '';

const project = {
    name: meta('project-name'),
    subtitle: meta('project-subtitle'),
    description: meta('project-description'),
    unifyUrl: meta('unify-url'),
};
</script>

<template>
    <main class="hero min-h-screen bg-base-200 px-6">
        <section class="hero-content text-center">
            <div class="max-w-xl">
                <div class="mx-auto mb-6 grid size-20 place-items-center rounded-2xl bg-primary text-primary-content shadow-lg">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-10 fill-none stroke-current" stroke-width="1.8">
                        <path d="M4 19V8a2 2 0 0 1 2-2h3l2-2h7a2 2 0 0 1 2 2v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1Z" />
                        <path d="M8 11h8M8 15h5" />
                    </svg>
                </div>
                <p class="mb-2 text-sm font-semibold uppercase tracking-[0.25em] text-primary">{{ project.name }}</p>
                <h1 class="text-5xl font-bold text-base-content">{{ project.subtitle }}</h1>
                <p class="py-6 text-lg text-base-content/70">{{ project.description }}</p>
                <a :href="project.unifyUrl" class="btn btn-primary">Back to Unify</a>
            </div>
        </section>
    </main>
</template>
```

- [x] **Step 7: Run application and frontend verification**

Run:

```bash
for app in project1 project2 project3; do
  docker run --rm \
    --user "$(id -u):$(id -g)" \
    --volume "$PWD/$app:/app" \
    --workdir /app \
    --entrypoint php \
    composer:2 artisan test --filter=ProjectPageTest
  npm --prefix "$app" run build
done
```

Expected: all nine feature assertions pass across the three projects and all three Vite builds exit successfully.

- [x] **Step 8: Commit the three presentation sites**

Run:

```bash
git add project1 project2 project3
git commit -m "feat: add minimal project presentation sites"
```

---

### Task 4: Implement Unify status aggregation and automatic card states

**Files:**
- Create: `unify/config/projects.php`
- Create: `unify/app/Services/ProjectStatusService.php`
- Create: `unify/app/Http/Controllers/ProjectStatusController.php`
- Replace: `unify/routes/web.php`
- Create: `unify/resources/views/app.blade.php`
- Replace: `unify/resources/js/components/App.vue`
- Create: `unify/tests/Feature/ProjectStatusTest.php`
- Delete: `unify/tests/Feature/ExampleTest.php`

**Interfaces:**
- Consumes: Each project's `GET /health` JSON endpoint and the three public browser URLs.
- Produces: `GET /api/projects/status` with the stable `projects[]` contract and a Vue dashboard that refreshes every 5000 milliseconds.

- [x] **Step 1: Write failing Unify status tests**

Create `unify/tests/Feature/ProjectStatusTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ProjectStatusTest extends TestCase
{
    public function test_status_endpoint_reports_healthy_projects(): void
    {
        Http::fake([
            'http://project1/health' => Http::response(['status' => 'ok'], 200),
            'http://project2/health' => Http::response(['status' => 'ok'], 200),
            'http://project3/health' => Http::response(['status' => 'ok'], 200),
        ]);

        $this->getJson('/api/projects/status')
            ->assertOk()
            ->assertJsonPath('projects.0.id', 'project1')
            ->assertJsonPath('projects.0.available', true)
            ->assertJsonPath('projects.1.available', true)
            ->assertJsonPath('projects.2.available', true);
    }

    public function test_status_endpoint_marks_a_failed_project_unavailable(): void
    {
        Http::fake([
            'http://project1/health' => Http::response(['status' => 'ok'], 200),
            'http://project2/health' => Http::response(['status' => 'error'], 503),
            'http://project3/health' => Http::response(['status' => 'ok'], 200),
        ]);

        $this->getJson('/api/projects/status')
            ->assertOk()
            ->assertJsonPath('projects.1.id', 'project2')
            ->assertJsonPath('projects.1.available', false);
    }

    public function test_dashboard_page_loads_when_projects_are_offline(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Unify');
    }

    public function test_health_reports_unify_and_its_database(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'service' => 'unify',
                'database' => 'connected',
            ]);
    }

    public function test_health_returns_503_when_unify_database_is_unavailable(): void
    {
        DB::shouldReceive('select')->once()->andThrow(new RuntimeException('offline'));

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertExactJson([
                'status' => 'error',
                'service' => 'unify',
                'database' => 'unavailable',
            ]);
    }
}
```

- [x] **Step 2: Run the focused tests to verify they fail**

Run:

```bash
docker run --rm \
  --user "$(id -u):$(id -g)" \
  --volume "$PWD/unify:/app" \
  --workdir /app \
  --entrypoint php \
  composer:2 artisan test --filter=ProjectStatusTest
```

Expected: failures because the service, controller, routes, and dashboard do not exist.

- [x] **Step 3: Define the project registry**

Create `unify/config/projects.php`:

```php
<?php

return [
    [
        'id' => 'project1',
        'name' => 'Project 1',
        'subtitle' => 'Future Work',
        'icon' => 'briefcase',
        'health_url' => env('PROJECT1_HEALTH_URL', 'http://project1/health'),
        'public_url' => env('PROJECT1_PUBLIC_URL', 'http://localhost:8081'),
    ],
    [
        'id' => 'project2',
        'name' => 'Project 2',
        'subtitle' => 'Future Work',
        'icon' => 'layers',
        'health_url' => env('PROJECT2_HEALTH_URL', 'http://project2/health'),
        'public_url' => env('PROJECT2_PUBLIC_URL', 'http://localhost:8082'),
    ],
    [
        'id' => 'project3',
        'name' => 'Project 3',
        'subtitle' => 'Future Work',
        'icon' => 'rocket',
        'health_url' => env('PROJECT3_HEALTH_URL', 'http://project3/health'),
        'public_url' => env('PROJECT3_PUBLIC_URL', 'http://localhost:8083'),
    ],
];
```

- [x] **Step 4: Implement status aggregation with failure isolation**

Create `unify/app/Services/ProjectStatusService.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class ProjectStatusService
{
    public function all(): array
    {
        return collect(config('projects'))->map(function (array $project): array {
            try {
                $available = Http::acceptJson()
                    ->connectTimeout(1)
                    ->timeout(1)
                    ->get($project['health_url'])
                    ->successful();
            } catch (Throwable) {
                $available = false;
            }

            return [
                'id' => $project['id'],
                'name' => $project['name'],
                'subtitle' => $project['subtitle'],
                'icon' => $project['icon'],
                'url' => $project['public_url'],
                'available' => $available,
            ];
        })->all();
    }
}
```

- [x] **Step 5: Implement the controller and routes**

Create `unify/app/Http/Controllers/ProjectStatusController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\ProjectStatusService;
use Illuminate\Http\JsonResponse;

class ProjectStatusController extends Controller
{
    public function __invoke(ProjectStatusService $statuses): JsonResponse
    {
        return response()->json(['projects' => $statuses->all()]);
    }
}
```

Replace `unify/routes/web.php` with:

```php
<?php

use App\Http\Controllers\ProjectStatusController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app');
Route::get('/api/projects/status', ProjectStatusController::class);

Route::get('/health', function () {
    try {
        DB::select('SELECT 1');

        return response()->json([
            'status' => 'ok',
            'service' => 'unify',
            'database' => 'connected',
        ]);
    } catch (\Throwable) {
        return response()->json([
            'status' => 'error',
            'service' => 'unify',
            'database' => 'unavailable',
        ], 503);
    }
});
```

- [x] **Step 6: Create the Unify Blade shell**

Create `unify/resources/views/app.blade.php`:

```blade
<!doctype html>
<html lang="en" data-theme="corporate">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unify</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
```

- [x] **Step 7: Create the polling Vue dashboard**

Create `unify/resources/js/components/App.vue`:

```vue
<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const projects = ref([]);
const checking = ref(true);
let timer;

async function refreshStatuses() {
    try {
        const response = await fetch('/api/projects/status', {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json();
        projects.value = payload.projects;
    } finally {
        checking.value = false;
    }
}

onMounted(async () => {
    await refreshStatuses();
    timer = window.setInterval(refreshStatuses, 5000);
});

onBeforeUnmount(() => window.clearInterval(timer));
</script>

<template>
    <main class="min-h-screen bg-base-200 px-6 py-16">
        <section class="mx-auto max-w-5xl">
            <div class="mb-10 text-center">
                <div class="badge badge-primary badge-outline mb-4">Docker + WSL demonstration</div>
                <h1 class="text-5xl font-bold text-base-content">Unify</h1>
                <p class="mt-4 text-base-content/70">Choose an available project container.</p>
            </div>

            <p v-if="checking" class="text-center text-base-content/60">Checking project containers…</p>

            <div v-else class="grid gap-6 md:grid-cols-3">
                <a
                    v-for="project in projects"
                    :key="project.id"
                    :href="project.available ? project.url : undefined"
                    :aria-disabled="String(!project.available)"
                    class="card border bg-base-100 shadow-xl transition"
                    :class="project.available
                        ? 'border-primary/20 hover:-translate-y-1 hover:shadow-2xl'
                        : 'cursor-not-allowed border-base-300 bg-base-300 opacity-50 grayscale'"
                    @click="!project.available && $event.preventDefault()"
                >
                    <div class="card-body">
                        <div class="mb-3 grid size-12 place-items-center rounded-xl bg-primary text-primary-content">
                            <span aria-hidden="true" class="text-xl">◆</span>
                        </div>
                        <h2 class="card-title">{{ project.name }}</h2>
                        <p>{{ project.subtitle }}</p>
                        <div class="card-actions mt-4 justify-between">
                            <span
                                class="badge"
                                :class="project.available ? 'badge-success' : 'badge-ghost'"
                            >
                                {{ project.available ? 'Available' : 'Unavailable' }}
                            </span>
                            <span v-if="project.available" class="text-sm font-semibold text-primary">Open →</span>
                        </div>
                    </div>
                </a>
            </div>
        </section>
    </main>
</template>
```

- [x] **Step 8: Run Unify verification**

Run:

```bash
docker run --rm \
  --user "$(id -u):$(id -g)" \
  --volume "$PWD/unify:/app" \
  --workdir /app \
  --entrypoint php \
  composer:2 artisan test --filter=ProjectStatusTest
npm --prefix unify run build
```

Expected: all five feature tests pass and Vite builds the dashboard successfully.

- [x] **Step 9: Commit Unify**

Run:

```bash
git add unify
git commit -m "feat: add health-aware Unify dashboard"
```

---

### Task 5: Build production-oriented application images

**Files:**
- Create: `unify/Dockerfile`
- Create: `project1/Dockerfile`
- Create: `project2/Dockerfile`
- Create: `project3/Dockerfile`
- Create: each application's `.dockerignore`
- Create: each application's `docker/apache.conf`
- Create: each application's `docker/entrypoint.sh`

**Interfaces:**
- Consumes: A complete Laravel/Vue application directory as its Docker build context.
- Produces: Four independently buildable Apache/PHP images listening on container port `80`.

- [x] **Step 1: Add the same Docker ignore policy to every application**

Create `.dockerignore` in each application:

```dockerignore
.git
.env
vendor
node_modules
public/build
storage/logs/*
```

- [x] **Step 2: Add the same Apache virtual host to every application**

Create `docker/apache.conf` in each application:

```apache
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/html/public

    <Directory /var/www/html/public>
        AllowOverride All
        Require all granted
        Options FollowSymLinks
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

- [x] **Step 3: Add the same database-aware entrypoint to every application**

Create `docker/entrypoint.sh` in each application and mark it executable:

```sh
#!/bin/sh
set -eu

attempt=0
until php -r '
$dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT"), getenv("DB_DATABASE"));
new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD"));
' >/dev/null 2>&1; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 30 ]; then
        echo "Database did not become ready after 30 attempts" >&2
        exit 1
    fi
    sleep 1
done

php artisan migrate --force
php artisan config:cache

exec "$@"
```

Run:

```bash
chmod +x unify/docker/entrypoint.sh project1/docker/entrypoint.sh project2/docker/entrypoint.sh project3/docker/entrypoint.sh
```

- [x] **Step 4: Add the same multi-stage Dockerfile to every application**

Create `Dockerfile` in each application:

```dockerfile
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction

FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM php:8.4-apache-bookworm AS runtime
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/app-entrypoint

EXPOSE 80
ENTRYPOINT ["app-entrypoint"]
CMD ["apache2-foreground"]
```

- [x] **Step 5: Build every image independently**

Run:

```bash
docker build --tag local/adockerwsl-unify:development unify
docker build --tag local/adockerwsl-project1:development project1
docker build --tag local/adockerwsl-project2:development project2
docker build --tag local/adockerwsl-project3:development project3
```

Expected: all four builds succeed and each final image contains Apache, `pdo_pgsql`, optimized Composer dependencies, and Vite's `public/build/manifest.json`.

- [x] **Step 6: Inspect the final image rather than trusting the build log**

Run:

```bash
docker run --rm --entrypoint php local/adockerwsl-unify:development -m | rg '^pdo_pgsql$'
docker run --rm --entrypoint test local/adockerwsl-unify:development -f /var/www/html/public/build/manifest.json
```

Expected: `pdo_pgsql` is printed and the manifest check exits successfully.

- [x] **Step 7: Commit container definitions**

Run:

```bash
git add unify project1 project2 project3
git commit -m "build: add Laravel application images"
```

---

### Task 6: Add PostgreSQL isolation and the five-service Compose topology

**Files:**
- Create: `docker/postgres/init-databases.sh`
- Create: `.env.example`
- Create locally but do not commit: `.env`
- Create: `docker.yaml`

**Interfaces:**
- Consumes: Four application images listening on port `80`.
- Produces: Compose DNS names, database credentials, health checks, host-port mappings, and the `postgres_data` named volume.

- [x] **Step 1: Create the PostgreSQL initialization script**

Create `docker/postgres/init-databases.sh` and mark it executable:

```sh
#!/bin/sh
set -eu

create_app_database() {
    database_name="$1"
    role_name="$2"
    role_password="$3"

    psql --set ON_ERROR_STOP=1 \
        --username "$POSTGRES_USER" \
        --dbname postgres \
        --set database_name="$database_name" \
        --set role_name="$role_name" \
        --set role_password="$role_password" <<'SQL'
SELECT format('CREATE ROLE %I LOGIN PASSWORD %L', :'role_name', :'role_password')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'role_name') \gexec

SELECT format('CREATE DATABASE %I OWNER %I', :'database_name', :'role_name')
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = :'database_name') \gexec
SQL
}

create_app_database unify_db unify_app "$UNIFY_DB_PASSWORD"
create_app_database project1_db project1_app "$PROJECT1_DB_PASSWORD"
create_app_database project2_db project2_app "$PROJECT2_DB_PASSWORD"
create_app_database project3_db project3_app "$PROJECT3_DB_PASSWORD"
```

Run:

```bash
chmod +x docker/postgres/init-databases.sh
sh -n docker/postgres/init-databases.sh
```

Expected: the shell syntax check succeeds.

- [x] **Step 2: Define safe local demonstration defaults**

Create `.env.example`:

```dotenv
COMPOSE_PROJECT_NAME=adockerwsl
IMAGE_PREFIX=local
IMAGE_TAG=development

POSTGRES_PASSWORD=local-postgres-admin-password
UNIFY_DB_PASSWORD=local-unify-password
PROJECT1_DB_PASSWORD=local-project1-password
PROJECT2_DB_PASSWORD=local-project2-password
PROJECT3_DB_PASSWORD=local-project3-password

UNIFY_APP_KEY=
PROJECT1_APP_KEY=
PROJECT2_APP_KEY=
PROJECT3_APP_KEY=
```

Copy it to the ignored local environment file:

```bash
cp .env.example .env
```

Generate four keys using the already-built images, then put their complete `base64:` values into the matching variables in the ignored `.env` file:

```bash
docker run --rm --entrypoint php local/adockerwsl-unify:development artisan key:generate --show
docker run --rm --entrypoint php local/adockerwsl-project1:development artisan key:generate --show
docker run --rm --entrypoint php local/adockerwsl-project2:development artisan key:generate --show
docker run --rm --entrypoint php local/adockerwsl-project3:development artisan key:generate --show
```

Expected: `.env` contains four non-empty keys and remains ignored by Git.

- [x] **Step 3: Create the complete Compose file**

Create `docker.yaml`:

```yaml
name: adockerwsl

x-laravel-environment: &laravel-environment
  APP_ENV: production
  APP_DEBUG: "false"
  LOG_CHANNEL: stderr
  DB_CONNECTION: pgsql
  DB_HOST: postgres
  DB_PORT: "5432"

x-laravel-service: &laravel-service
  restart: unless-stopped
  depends_on:
    postgres:
      condition: service_healthy
  healthcheck:
    test: ["CMD", "curl", "--fail", "--silent", "http://localhost/health"]
    interval: 5s
    timeout: 2s
    retries: 6
    start_period: 20s

services:
  unify:
    <<: *laravel-service
    image: ${IMAGE_PREFIX:-local}/adockerwsl-unify:${IMAGE_TAG:-development}
    build:
      context: ./unify
    environment:
      <<: *laravel-environment
      APP_NAME: Unify
      APP_KEY: ${UNIFY_APP_KEY}
      APP_URL: http://localhost:8080
      DB_DATABASE: unify_db
      DB_USERNAME: unify_app
      DB_PASSWORD: ${UNIFY_DB_PASSWORD}
      PROJECT1_HEALTH_URL: http://project1/health
      PROJECT2_HEALTH_URL: http://project2/health
      PROJECT3_HEALTH_URL: http://project3/health
      PROJECT1_PUBLIC_URL: http://localhost:8081
      PROJECT2_PUBLIC_URL: http://localhost:8082
      PROJECT3_PUBLIC_URL: http://localhost:8083
    ports:
      - "8080:80"

  project1:
    <<: *laravel-service
    image: ${IMAGE_PREFIX:-local}/adockerwsl-project1:${IMAGE_TAG:-development}
    build:
      context: ./project1
    environment:
      <<: *laravel-environment
      APP_NAME: Project 1
      APP_KEY: ${PROJECT1_APP_KEY}
      APP_URL: http://localhost:8081
      UNIFY_PUBLIC_URL: http://localhost:8080
      DB_DATABASE: project1_db
      DB_USERNAME: project1_app
      DB_PASSWORD: ${PROJECT1_DB_PASSWORD}
    ports:
      - "8081:80"

  project2:
    <<: *laravel-service
    image: ${IMAGE_PREFIX:-local}/adockerwsl-project2:${IMAGE_TAG:-development}
    build:
      context: ./project2
    environment:
      <<: *laravel-environment
      APP_NAME: Project 2
      APP_KEY: ${PROJECT2_APP_KEY}
      APP_URL: http://localhost:8082
      UNIFY_PUBLIC_URL: http://localhost:8080
      DB_DATABASE: project2_db
      DB_USERNAME: project2_app
      DB_PASSWORD: ${PROJECT2_DB_PASSWORD}
    ports:
      - "8082:80"

  project3:
    <<: *laravel-service
    image: ${IMAGE_PREFIX:-local}/adockerwsl-project3:${IMAGE_TAG:-development}
    build:
      context: ./project3
    environment:
      <<: *laravel-environment
      APP_NAME: Project 3
      APP_KEY: ${PROJECT3_APP_KEY}
      APP_URL: http://localhost:8083
      UNIFY_PUBLIC_URL: http://localhost:8080
      DB_DATABASE: project3_db
      DB_USERNAME: project3_app
      DB_PASSWORD: ${PROJECT3_DB_PASSWORD}
    ports:
      - "8083:80"

  postgres:
    image: postgres:17-bookworm
    restart: unless-stopped
    environment:
      POSTGRES_USER: postgres
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
      POSTGRES_DB: postgres
      UNIFY_DB_PASSWORD: ${UNIFY_DB_PASSWORD}
      PROJECT1_DB_PASSWORD: ${PROJECT1_DB_PASSWORD}
      PROJECT2_DB_PASSWORD: ${PROJECT2_DB_PASSWORD}
      PROJECT3_DB_PASSWORD: ${PROJECT3_DB_PASSWORD}
    ports:
      - "5433:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./docker/postgres/init-databases.sh:/docker-entrypoint-initdb.d/10-init-databases.sh:ro
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U postgres -d postgres"]
      interval: 5s
      timeout: 3s
      retries: 10
      start_period: 10s

volumes:
  postgres_data:
```

- [x] **Step 4: Validate the resolved topology and secrets boundary**

Run:

```bash
docker compose -f docker.yaml config --quiet
docker compose -f docker.yaml config --services
git check-ignore .env
git diff --check
```

Expected services, in order: `unify`, `project1`, `project2`, `project3`, and `postgres`. `.env` is reported as ignored and no whitespace errors are found.

- [x] **Step 5: Commit database and Compose configuration**

Run:

```bash
git add .env.example docker.yaml docker/postgres/init-databases.sh
git commit -m "feat: orchestrate Laravel apps and PostgreSQL"
```

---

### Task 7: Verify the complete local failure-and-recovery demonstration

**Files:**
- Modify only if verification exposes a defect: task-scoped Laravel, Docker, or Compose files.

**Interfaces:**
- Consumes: The five-service Compose topology.
- Produces: Evidence that pages, database isolation, health aggregation, stopping, and recovery all work from WSL and Docker Desktop.

- [x] **Step 1: Build and start the entire application**

Run:

```bash
docker compose -f docker.yaml up --build -d
docker compose -f docker.yaml ps
```

Expected: all five services reach `Up (healthy)` and Docker Desktop lists them under the `adockerwsl` Compose application.

- [x] **Step 2: Verify all public pages and local health endpoints**

Run:

```bash
curl --fail --silent http://localhost:8080/ | rg 'id="app"'
curl --fail --silent http://localhost:8081/ | rg 'Project 1'
curl --fail --silent http://localhost:8082/ | rg 'Project 2'
curl --fail --silent http://localhost:8083/ | rg 'Project 3'
curl --fail --silent http://localhost:8080/health
curl --fail --silent http://localhost:8081/health
curl --fail --silent http://localhost:8082/health
curl --fail --silent http://localhost:8083/health
curl --fail --silent http://localhost:8080/api/projects/status
```

Expected: pages return HTML, health responses report their exact service names and `database: connected`, and all three status records have `available: true`.

- [x] **Step 3: Verify four databases and owners from inside PostgreSQL**

Run:

```bash
docker compose -f docker.yaml exec -T postgres \
  psql -U postgres -d postgres -Atc \
  "SELECT datname || ':' || pg_get_userbyid(datdba) FROM pg_database WHERE datname IN ('unify_db','project1_db','project2_db','project3_db') ORDER BY datname;"
```

Expected:

```text
project1_db:project1_app
project2_db:project2_app
project3_db:project3_app
unify_db:unify_app
```

- [x] **Step 4: Prove automatic Unify degradation when a project stops**

Run:

```bash
docker compose -f docker.yaml stop project2
python3 - <<'PY'
import json
import time
import urllib.request

for _ in range(10):
    with urllib.request.urlopen('http://localhost:8080/api/projects/status') as response:
        projects = json.load(response)['projects']
    status = next(project for project in projects if project['id'] == 'project2')
    if status['available'] is False:
        print('project2 unavailable: verified')
        break
    time.sleep(1)
else:
    raise SystemExit('project2 did not become unavailable')
PY
```

Expected: the script prints `project2 unavailable: verified`. In a browser, its card becomes grey and disabled within one five-second polling interval.

- [x] **Step 5: Prove automatic recovery**

Run:

```bash
docker compose -f docker.yaml start project2
python3 - <<'PY'
import json
import time
import urllib.request

for _ in range(30):
    with urllib.request.urlopen('http://localhost:8080/api/projects/status') as response:
        projects = json.load(response)['projects']
    status = next(project for project in projects if project['id'] == 'project2')
    if status['available'] is True:
        print('project2 available: verified')
        break
    time.sleep(1)
else:
    raise SystemExit('project2 did not recover')
PY
```

Expected: the script prints `project2 available: verified`, and the browser card returns to its active state.

- [x] **Step 6: Run all application test suites in the disposable Composer container**

Run:

```bash
for app in unify project1 project2 project3; do
  docker run --rm \
    --user "$(id -u):$(id -g)" \
    --volume "$PWD/$app:/app" \
    --workdir /app \
    --entrypoint php \
    composer:2 artisan test
done
```

Expected: all Laravel tests pass. The disposable test container uses each application's development dependencies; the production images remain smaller and do not include PHPUnit.

- [x] **Step 7: Stop cleanly while preserving database data**

Run:

```bash
docker compose -f docker.yaml down
```

Expected: the five containers and project network stop; the `adockerwsl_postgres_data` named volume remains.

- [x] **Step 8: Commit any verified corrections**

If verification required changes, run the affected focused tests again, then:

```bash
git add AGENTS.md .env.example docker.yaml docker unify project1 project2 project3
git commit -m "fix: complete local Docker verification"
```

If there were no corrections, do not create an empty commit.

---

### Task 8: Add the classroom README and presentation runbook

**Files:**
- Create: `README.md`
- Create: `docs/presentation-runbook.md`

**Interfaces:**
- Consumes: The verified local commands and endpoints from Task 7.
- Produces: A reproducible installation guide and a short live-presentation sequence.

- [x] **Step 1: Write the README with exact audience-facing sections**

Create `README.md` containing:

1. A one-paragraph purpose statement.
2. A five-service architecture table copied from the design specification.
3. Prerequisites: Windows, WSL 2, Docker Desktop WSL integration, Git, and optional pgAdmin.
4. First-run commands: copy `.env.example`, generate four app keys, validate Compose, then `up --build -d`.
5. URLs for Unify, three projects, and PostgreSQL.
6. pgAdmin connection values: host `localhost`, port `5433`, maintenance database `postgres`, username `postgres`, and the local password from the ignored `.env`.
7. Commands for logs, status, stopping one project, restarting it, and stopping the stack.
8. A warning that `docker compose down -v` deletes this demonstration's PostgreSQL data and should be used only for an intentional reset.
9. Staging-image pull commands from the design specification.
10. A link to `docs/presentation-runbook.md`.

- [x] **Step 2: Write the timed classroom runbook**

Create `docs/presentation-runbook.md` with this sequence:

```markdown
# Classroom Presentation Runbook

## 1. Show the environment

- Open the repository in VS Code through WSL.
- Show that Docker Desktop reports its engine as running.
- Run `docker version` and `docker compose version` in the VS Code WSL terminal.

## 2. Explain the Compose file

- Point out the four independently built Laravel images.
- Point out the single official PostgreSQL image and named volume.
- Contrast internal names such as `postgres:5432` with host access such as `localhost:5433`.

## 3. Start the local build

```bash
docker compose -f docker.yaml up --build -d
docker compose -f docker.yaml ps
```

## 4. Show the sites

- Open `http://localhost:8080`.
- Open each available project card and use its Back to Unify link.
- Refresh pgAdmin and show the four databases.

## 5. Demonstrate container independence

```bash
docker compose -f docker.yaml stop project2
```

- Wait at most five seconds and show the grey Project 2 card.
- Point out that Unify and the other projects are still running.

```bash
docker compose -f docker.yaml start project2
```

- Show the Project 2 card recover automatically.

## 6. Show staging images

- Open the successful GitHub Actions staging workflow.
- Show the four public GHCR packages.
- Stop the local stack, pull the staging tags, and start with `--no-build`.

## 7. Finish cleanly

```bash
docker compose -f docker.yaml down
```

- Explain that the named PostgreSQL volume remains for the next run.
```

- [x] **Step 3: Verify every documented command and link**

Run the non-destructive commands from both documents, confirm every local link matches the Compose ports, and run:

```bash
rg -n "8080|8081|8082|8083|5433|postgres:5432" README.md docs/presentation-runbook.md
git diff --check
```

Expected: all six networking values appear where relevant and no formatting errors are reported.

- [x] **Step 4: Commit documentation**

Run:

```bash
git add README.md docs/presentation-runbook.md
git commit -m "docs: add Docker classroom runbook"
```

---

### Task 9: Add tested staging image publication

**Files:**
- Create: `.github/workflows/staging.yml`

**Interfaces:**
- Consumes: Four Docker build contexts and the verified `docker.yaml`.
- Produces: Public `:staging` and immutable `:sha-<commit>` GHCR image tags after verification succeeds on the `staging` branch.

- [x] **Step 1: Create the staging workflow**

Create `.github/workflows/staging.yml`:

```yaml
name: Staging

on:
  push:
    branches:
      - staging
  workflow_dispatch:

permissions:
  contents: read
  packages: write

jobs:
  verify:
    runs-on: ubuntu-latest
    steps:
      - name: Check out repository
        uses: actions/checkout@v6

      - name: Create CI environment
        run: |
          cp .env.example .env
          sed -i 's|^UNIFY_APP_KEY=$|UNIFY_APP_KEY=base64:bG9jYWwtY2ktdW5pZnktYXBwLWtleS0zMmJ5dGVzISE=|' .env
          sed -i 's|^PROJECT1_APP_KEY=$|PROJECT1_APP_KEY=base64:bG9jYWwtY2ktcHJvamVjdDEta2V5LTMyYnl0ZXMhISE=|' .env
          sed -i 's|^PROJECT2_APP_KEY=$|PROJECT2_APP_KEY=base64:bG9jYWwtY2ktcHJvamVjdDIta2V5LTMyYnl0ZXMhISE=|' .env
          sed -i 's|^PROJECT3_APP_KEY=$|PROJECT3_APP_KEY=base64:bG9jYWwtY2ktcHJvamVjdDMta2V5LTMyYnl0ZXMhISE=|' .env

      - name: Validate Compose
        run: docker compose -f docker.yaml config --quiet

      - name: Install dependencies and run Laravel tests
        run: |
          for app in unify project1 project2 project3; do
            docker run --rm \
              --user "$(id -u):$(id -g)" \
              --env COMPOSER_CACHE_DIR=/tmp/composer-cache \
              --volume "$PWD/$app:/app" \
              --workdir /app \
              composer:2 install --no-interaction --no-progress
            docker run --rm \
              --user "$(id -u):$(id -g)" \
              --volume "$PWD/$app:/app" \
              --workdir /app \
              --entrypoint php \
              composer:2 artisan test
          done

      - name: Build application images
        run: docker compose -f docker.yaml build

      - name: Start application
        run: docker compose -f docker.yaml up -d

      - name: Wait for all services
        run: |
          for _ in $(seq 1 60); do
            if [ "$(docker compose -f docker.yaml ps --status running --services | wc -l)" -eq 5 ] \
              && curl --fail --silent http://localhost:8080/health >/dev/null \
              && curl --fail --silent http://localhost:8081/health >/dev/null \
              && curl --fail --silent http://localhost:8082/health >/dev/null \
              && curl --fail --silent http://localhost:8083/health >/dev/null; then
              exit 0
            fi
            sleep 2
          done
          docker compose -f docker.yaml ps
          docker compose -f docker.yaml logs
          exit 1

      - name: Verify Unify aggregation
        run: |
          curl --fail --silent http://localhost:8080/api/projects/status > statuses.json
          python3 -c "import json; data=json.load(open('statuses.json')); assert len(data['projects']) == 3; assert all(p['available'] for p in data['projects'])"

      - name: Show diagnostics on failure
        if: failure()
        run: |
          docker compose -f docker.yaml ps
          docker compose -f docker.yaml logs

      - name: Stop application
        if: always()
        run: docker compose -f docker.yaml down --volumes

  publish:
    needs: verify
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        app:
          - unify
          - project1
          - project2
          - project3
    steps:
      - name: Check out repository
        uses: actions/checkout@v6

      - name: Prepare lowercase image name
        id: image
        shell: bash
        run: echo "name=ghcr.io/${GITHUB_REPOSITORY_OWNER,,}/adockerwsl-${{ matrix.app }}" >> "$GITHUB_OUTPUT"

      - name: Log in to GHCR
        uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      - name: Build and publish image
        uses: docker/build-push-action@v6
        with:
          context: ./${{ matrix.app }}
          push: true
          tags: |
            ${{ steps.image.outputs.name }}:staging
            ${{ steps.image.outputs.name }}:sha-${{ github.sha }}
          labels: |
            org.opencontainers.image.source=${{ github.server_url }}/${{ github.repository }}
            org.opencontainers.image.revision=${{ github.sha }}
          cache-from: type=gha,scope=${{ matrix.app }}
          cache-to: type=gha,mode=max,scope=${{ matrix.app }}
```

- [x] **Step 2: Review workflow boundaries**

Confirm:

- The workflow triggers only from `staging` pushes or manual dispatch.
- `publish` has `needs: verify`.
- The workflow uses only the repository-scoped `GITHUB_TOKEN`.
- The PostgreSQL image is pulled from Docker Hub and is not republished.
- CI uses `down --volumes` only on its disposable GitHub runner.
- Image source labels link all four packages to the public repository.

- [x] **Step 3: Validate YAML and repository formatting locally**

Run:

```bash
docker compose -f docker.yaml config --quiet
git diff --check
git status --short
```

Expected: Compose validation succeeds, the diff is clean, and only the workflow is untracked or modified.

- [x] **Step 4: Commit the workflow**

Run:

```bash
git add .github/workflows/staging.yml
git commit -m "ci: publish verified staging images"
```

---

### Task 10: Create the public GitHub repository and verify pulled staging images

**Files:**
- No source files are expected to change.

**Interfaces:**
- Consumes: A complete local `main` history and `.github/workflows/staging.yml`.
- Produces: The public `adockerwsl` GitHub repository, a `staging` branch, four public GHCR packages, and a proven local image-pull workflow.

- [ ] **Step 1: Install and authenticate GitHub CLI in WSL at the user checkpoint**

The WSL environment currently reports `gh: not found`. Follow GitHub CLI's official Debian/Ubuntu installation instructions, then run:

```bash
gh auth login --web --git-protocol https
gh auth status
```

Expected: `gh auth status` reports an authenticated GitHub account. Browser authentication is completed by the user; credentials are never written into this repository.

- [ ] **Step 2: Create and push the public repository**

Run:

```bash
gh repo create adockerwsl --public --source=. --remote=origin --push
git remote -v
```

Expected: `origin` points to the authenticated account's public `adockerwsl` repository and `main` is pushed.

- [ ] **Step 3: Create and push the staging branch**

Run:

```bash
git switch -c staging
git push --set-upstream origin staging
```

Expected: the Staging workflow starts in GitHub Actions.

- [ ] **Step 4: Wait for and inspect the workflow**

Run:

```bash
gh run list --workflow Staging --limit 1
gh run watch --exit-status
```

Expected: both `verify` and all four `publish` matrix jobs complete successfully.

- [ ] **Step 5: Confirm anonymous staging pulls**

First make each GHCR package public in GitHub's package settings if it did not inherit public visibility. Then log Docker out of GHCR for the anonymous-pull check and derive the namespace from `origin`:

```bash
docker logout ghcr.io || true
GITHUB_OWNER="$(gh api user --jq .login | tr '[:upper:]' '[:lower:]')"
docker pull "ghcr.io/${GITHUB_OWNER}/adockerwsl-unify:staging"
docker pull "ghcr.io/${GITHUB_OWNER}/adockerwsl-project1:staging"
docker pull "ghcr.io/${GITHUB_OWNER}/adockerwsl-project2:staging"
docker pull "ghcr.io/${GITHUB_OWNER}/adockerwsl-project3:staging"
```

Expected: all four pulls succeed without registry authentication.

- [ ] **Step 6: Run only the published application images locally**

Run:

```bash
GITHUB_OWNER="$(gh api user --jq .login | tr '[:upper:]' '[:lower:]')"
export IMAGE_PREFIX="ghcr.io/${GITHUB_OWNER}"
export IMAGE_TAG=staging
docker compose -f docker.yaml up --no-build -d
docker compose -f docker.yaml ps
curl --fail --silent http://localhost:8080/api/projects/status
```

Expected: Compose starts the four pulled GHCR images plus the official PostgreSQL image, all services become healthy, and all project statuses are available.

- [ ] **Step 7: Repeat failure recovery against staging images**

Run:

```bash
docker compose -f docker.yaml stop project2
curl --silent http://localhost:8080/api/projects/status
docker compose -f docker.yaml start project2
```

Expected: Project 2 changes to unavailable after the backend timeout and returns to available after its health check recovers.

- [ ] **Step 8: Finish on the main branch**

Run:

```bash
docker compose -f docker.yaml down
git switch main
git status --short --branch
```

Expected: containers stop without deleting PostgreSQL data, the working tree is clean, and `main` is checked out.

---

## Final Acceptance Checklist

- [ ] Docker Desktop displays exactly five containers in the `adockerwsl` Compose application.
- [ ] Unify loads at `http://localhost:8080` and shows exactly three cards.
- [ ] Project pages load at ports `8081`, `8082`, and `8083`.
- [ ] Windows pgAdmin connects to the Docker PostgreSQL server at `localhost:5433`.
- [ ] PostgreSQL contains four databases owned by four distinct non-superuser roles.
- [ ] All four Laravel `/health` endpoints confirm their assigned database connection.
- [ ] Stopping any project greys only its card within five seconds.
- [ ] Restarting that project restores its card automatically.
- [ ] No project communicates with another project.
- [ ] `docker compose -f docker.yaml config --quiet` succeeds.
- [ ] All four `php artisan test` suites pass in the disposable Composer test container.
- [ ] All four frontend production builds succeed.
- [ ] The GitHub repository is public and named `adockerwsl`.
- [ ] The Staging workflow verifies the stack before publishing images.
- [ ] Four public GHCR `:staging` images can be pulled anonymously.
- [ ] The pulled staging images reproduce the complete local presentation.
- [ ] `.env`, application keys, database passwords, `vendor`, `node_modules`, and Vite build output are absent from Git history.
