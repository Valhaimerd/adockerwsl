# ADockerWSL

ADockerWSL is a small classroom demonstration of Docker Desktop and WSL 2. One Docker Compose project runs a Vue-powered Laravel portal named Unify, three independent school CRUD applications, and one PostgreSQL server containing four isolated application databases. Unify checks the other containers and combines their read-only data every five seconds, so stopping one project greys out only that project's card and overview.

## Architecture

| Service | Image | Host access | Internal dependencies | Database |
|---|---|---|---|---|
| `unify` | `adockerwsl-unify` | `http://localhost:8080` | `postgres`, project health URLs | `unify_db` |
| `project1` | `adockerwsl-project1` | `http://localhost:8081` | `postgres` | `project1_db` |
| `project2` | `adockerwsl-project2` | `http://localhost:8082` | `postgres` | `project2_db` |
| `project3` | `adockerwsl-project3` | `http://localhost:8083` | `postgres` | `project3_db` |
| `postgres` | `postgres:17-bookworm` | `localhost:5433` | None | Hosts all four databases |

Browser traffic uses the published `localhost` ports. Container traffic uses Compose DNS: Unify calls project URLs such as `http://project1/health` and `http://project1/api/students`, and every Laravel container connects to `postgres:5432`.

## Prerequisites

- Windows with WSL 2 and a Linux distribution such as Ubuntu
- Docker Desktop with its engine running and WSL integration enabled for that distribution
- VS Code opened in WSL and Git installed in WSL
- Optional: pgAdmin on Windows for viewing the containerized databases

Confirm Docker works in the VS Code WSL terminal:

```bash
docker version
docker compose version
```

## First run

Run these commands from the repository root in WSL:

```bash
cp .env.example .env

generate_key() {
  docker run --rm php:8.4-cli-alpine php -r 'echo "base64:", base64_encode(random_bytes(32)), PHP_EOL;'
}

sed -i "s|^UNIFY_APP_KEY=.*|UNIFY_APP_KEY=$(generate_key)|" .env
sed -i "s|^PROJECT1_APP_KEY=.*|PROJECT1_APP_KEY=$(generate_key)|" .env
sed -i "s|^PROJECT2_APP_KEY=.*|PROJECT2_APP_KEY=$(generate_key)|" .env
sed -i "s|^PROJECT3_APP_KEY=.*|PROJECT3_APP_KEY=$(generate_key)|" .env
unset -f generate_key

docker compose -f docker.yaml config --quiet
docker compose -f docker.yaml up --build -d
docker compose -f docker.yaml ps
```

The first build downloads the base images and can take several minutes. Wait until all five services show `healthy`.

The committed `.env.example` contains demonstration-only database passwords. The copied `.env` is ignored by Git and contains the generated Laravel keys.

## Local addresses

| Component | Address |
|---|---|
| Unify | `http://localhost:8080` |
| Students | `http://localhost:8081` |
| Faculty | `http://localhost:8082` |
| Courses | `http://localhost:8083` |
| PostgreSQL from Windows/WSL | `localhost:5433` |

For pgAdmin on Windows, register a server with:

- Host: `localhost`
- Port: `5433`
- Maintenance database: `postgres`
- Username: `postgres`
- Password: the `POSTGRES_PASSWORD` value in the ignored root `.env` (`local-postgres-admin-password` when copied unchanged)

The Windows PostgreSQL service is separate and is not used by this project.

## Useful demonstration commands

```bash
# Container state
docker compose -f docker.yaml ps

# Follow all logs, or only Unify's logs
docker compose -f docker.yaml logs -f
docker compose -f docker.yaml logs -f unify

# Make Faculty unavailable, then recover it
docker compose -f docker.yaml stop project2
docker compose -f docker.yaml start project2

# Stop containers and the network while preserving PostgreSQL data
docker compose -f docker.yaml down
```

The PostgreSQL initialization script creates the four databases only when `postgres_data` is empty. Running `docker compose -f docker.yaml down -v` deletes this demonstration's PostgreSQL data; use it only when you intentionally want a complete database reset.

## Run staging images locally

After the staging workflow has published the four public GHCR packages, authenticate the GitHub CLI and run:

```bash
GITHUB_OWNER="$(gh api user --jq .login | tr '[:upper:]' '[:lower:]')"
export IMAGE_PREFIX="ghcr.io/${GITHUB_OWNER}"
export IMAGE_TAG=staging
docker compose -f docker.yaml pull unify project1 project2 project3 postgres
docker compose -f docker.yaml up --no-build -d
```

The local `.env` still supplies application keys and database passwords; `IMAGE_PREFIX` and `IMAGE_TAG` select the published images.

For the live sequence, see the [classroom presentation runbook](docs/presentation-runbook.md).
