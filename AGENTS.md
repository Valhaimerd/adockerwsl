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
- Keep the school demo simple: project1 owns Students CRUD, project2 owns Faculty CRUD, and project3 owns Courses CRUD.
- Keep Unify read-only: it aggregates the three project data endpoints and preserves automatic health-card behavior.
- Keep WSL development and the optional Kali staging host separate; staging must pull GHCR images and use `--no-build`.

## Working rules

- Follow the approved tasks and contracts in `docs/adockerwsl-implementation-plan.md`.
- Use `docker compose -f docker.yaml` in documented commands.
- Do not use the Windows PostgreSQL server as an application dependency.
- Do not add services, authentication, cross-database relations, or unrelated infrastructure without approval.
- Never commit `.env`, credentials, Composer `vendor`, npm `node_modules`, or built Vite assets.
- Do not run Docker cleanup commands that can affect unrelated projects.
- Keep automatic Kali deployment disabled unless `KALI_DEPLOY_ENABLED=true` and the labeled isolated runner is ready.
- Use test-driven development for application behavior and make the smallest task-scoped change.

## Minimal skill set

- Use `superpowers:brainstorming` when requirements or architecture change.
- Use `superpowers:test-driven-development` when implementing or changing application behavior.
- Use `superpowers:verification-before-completion` before reporting implementation work complete.

## Verification

- Run Laravel tests in the disposable `composer:2` container because WSL PHP 8.3 cannot execute the PHP 8.4.1+ lockfiles and the production application images intentionally omit development dependencies. Use the exact bind-mounted test commands in the implementation plan.
- Run `npm run build` after changing Vue, Vite, Tailwind, or daisyUI files.
- Run `docker compose -f docker.yaml config` after changing Compose configuration.
- For integration changes, verify every `/health` endpoint and Unify's `/api/projects/status` endpoint.
- Before completion, stop one project service, verify its Unify status becomes unavailable, restart it, and verify recovery.
