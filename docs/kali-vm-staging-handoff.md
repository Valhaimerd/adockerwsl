# Kali VM Staging Host Handoff

> Copy this entire document into the VS Code agent chat running inside the Kali VM, or open this file after cloning the repository and ask the agent to follow it. These instructions apply to the Kali deployment host, not the WSL development environment.

## Mission

Prepare this isolated Kali Linux VMware guest as the staged deployment host for:

```text
https://github.com/Valhaimerd/adockerwsl
```

GitHub-hosted runners will test the applications and publish four `:staging` images to GHCR. A repository-level self-hosted runner on this VM will receive only the final deployment job, update `/opt/adockerwsl`, pull the verified images, and recreate the five-container Compose application.

Do not redesign the applications or add services. The final runtime must remain `unify`, `project1`, `project2`, `project3`, and `postgres` using the repository's single `docker.yaml`.

## Safety boundaries

- Treat this as a disposable classroom VM, not real production.
- The GitHub repository is public. Keep personal files, SSH keys, cloud credentials, and unrelated secrets off this VM.
- Do not mount Windows or WSL development folders into these containers.
- Do not commit the Kali `.env` or paste its generated secrets into chat or workflow logs.
- Do not run broad Docker cleanup commands such as `docker system prune`.
- Do not run `docker compose down -v`; the `-v` flag deletes the staged PostgreSQL data.
- Do not enable automatic deployment until the runner, deployment checkout, `.env`, and network have all been verified.
- If a prerequisite fails, diagnose it and report the exact command/output instead of weakening TLS or permissions.

## Target layout

```text
/opt/adockerwsl/        dedicated staging repository checkout and private .env
/opt/actions-runner/    repository-level GitHub Actions runner
```

This is intentionally separate from `/home/valhaimerd/projects` in WSL. The Kali VM has its own Docker Engine, containers, network, and named PostgreSQL volume.

Kali needs Docker Engine and the Docker Compose plugin; Docker Desktop is not required inside the Linux VM.

## Phase 1 — Inspect and verify prerequisites

Before changing anything, report:

```bash
cat /etc/os-release
uname -m
git --version
curl --version
docker version
docker compose version
python3 --version
ip -brief address
```

Required results:

- Linux architecture is `x86_64`/`amd64` for the runner instructions below.
- Docker Engine answers without `sudo` for the account that will run GitHub Actions.
- `docker compose version` uses Compose v2.
- Git, curl, and Python 3 are present.
- The VM has a stable address reachable from the Windows host.

If Docker is missing, install Docker Engine and the Compose plugin using an appropriate current Debian/Kali method. Do not install a second PostgreSQL server; PostgreSQL runs in Compose. Add only the dedicated runner account to the `docker` group, then log out and back in before retesting Docker access.

Take a VMware snapshot before registering the public-repository runner.

## Phase 2 — Create the dedicated deployment checkout

Run as the normal runner user, using `sudo` only to prepare `/opt`:

```bash
sudo mkdir -p /opt/adockerwsl
sudo chown "$USER:$USER" /opt/adockerwsl
git clone https://github.com/Valhaimerd/adockerwsl.git /opt/adockerwsl
cd /opt/adockerwsl
git status --short --branch
```

If the directory is already a clone, do not delete it. Inspect it, preserve `.env`, and update with a fast-forward-only pull:

```bash
git -C /opt/adockerwsl pull --ff-only
```

The checkout must remain clean except for the ignored `.env`.

## Phase 3 — Configure the Kali-only environment

Create the private environment file:

```bash
cd /opt/adockerwsl
cp .env.example .env
chmod 600 .env
```

Determine the stable VM address shown by `ip -brief address`. Replace `KALI_VM_IP` below with that address and edit `.env` so these values are present:

```dotenv
COMPOSE_PROJECT_NAME=adockerwsl
IMAGE_PREFIX=ghcr.io/valhaimerd
IMAGE_TAG=staging

UNIFY_PUBLIC_URL=http://KALI_VM_IP:8080
PROJECT1_PUBLIC_URL=http://KALI_VM_IP:8081
PROJECT2_PUBLIC_URL=http://KALI_VM_IP:8082
PROJECT3_PUBLIC_URL=http://KALI_VM_IP:8083
```

Generate separate local database passwords and four Laravel application keys. Never reuse the WSL development passwords. A Laravel key can be generated without installing PHP on Kali:

```bash
docker run --rm php:8.4-cli-alpine php -r 'echo "base64:", base64_encode(random_bytes(32)), PHP_EOL;'
```

Run that command four times and place one result in each `*_APP_KEY` entry. Use `openssl rand -hex 24` to generate each database password. Keep the variable names from `.env.example` unchanged.

Validate without printing the resolved configuration because it contains secrets:

```bash
cd /opt/adockerwsl
docker compose -f docker.yaml config --quiet
docker compose -f docker.yaml config --images
```

The application image names must start with `ghcr.io/valhaimerd/`, and none may start with `local/`.

Do not run `scripts/deploy-staging.sh` until the current main branch has been promoted to `staging` and GHCR contains the School CRUD images.

## Phase 4 — Prepare VMware networking

Use a VMware network mode that lets the Windows host reach the guest, preferably bridged networking or a stable host-only/NAT configuration. From Kali, note the address:

```bash
hostname -I
```

When the stack is later running, Windows should reach:

```text
http://KALI_VM_IP:8080  Unify
http://KALI_VM_IP:8081  Students
http://KALI_VM_IP:8082  Faculty
http://KALI_VM_IP:8083  Courses
```

If a firewall is active, allow TCP ports `8080` through `8083` only on the classroom/private network. Port `5433` is optional and should be opened only if Windows pgAdmin must inspect the Kali PostgreSQL container.

## Phase 5 — Register the isolated GitHub Actions runner

Because this is a public repository, use this runner only in the disposable VM and remove it after the demonstration.

In GitHub, open:

```text
Repository → Settings → Actions → Runners → New self-hosted runner
```

Choose Linux and x64. GitHub will display current download, verification, and registration commands with a short-lived token. Install into `/opt/actions-runner`, register it at repository scope, and give it the custom label:

```text
adockerwsl-kali
```

Use a descriptive runner name such as `adockerwsl-kali-demo`. Install and start it as a system service using the `svc.sh` commands GitHub displays. Do not run the service as root. Confirm GitHub reports the runner as **Idle** and that its labels include:

```text
self-hosted, Linux, X64, adockerwsl-kali
```

Also confirm the runner service account can run both commands without `sudo`:

```bash
docker version
docker compose version
```

Do not paste the runner registration token into this repository or any Markdown file.

## Phase 6 — Enable deployment only when ready

The workflow's deployment job is guarded by a repository variable and remains skipped by default.

After Phases 1–5 pass, create this GitHub repository variable:

```text
Repository → Settings → Secrets and variables → Actions → Variables
Name: KALI_DEPLOY_ENABLED
Value: true
```

No deployment password or SSH key is needed: the job executes locally on the Kali self-hosted runner, and the GHCR staging images are public.

Tell the user the host is ready. The development environment must then promote `main` to `staging`. Do not perform that promotion from Kali unless the user explicitly asks.

## Phase 7 — Expected automatic deployment

After a push to `staging`, the workflow must execute in this order:

1. GitHub-hosted runner tests all Laravel applications and builds the Compose stack.
2. GitHub-hosted matrix jobs publish the four GHCR images.
3. Only if all publishing jobs succeed, the Kali deployment job starts.
4. Kali fast-forwards `/opt/adockerwsl` to `origin/staging`.
5. `scripts/deploy-staging.sh` pulls images and runs Compose with `--no-build`.
6. The script checks all health endpoints plus the Unify status and school overview endpoints.

If verification or publishing fails, the deployment job must not run, and the previous Kali containers must continue serving.

## Phase 8 — Acceptance checks

After the first successful deployment, run:

```bash
cd /opt/adockerwsl
docker compose -f docker.yaml ps
docker compose -f docker.yaml images
curl --fail --silent http://localhost:8080/health
curl --fail --silent http://localhost:8080/api/projects/status
curl --fail --silent http://localhost:8080/api/school/overview
```

Verify:

- Exactly five services are running and healthy.
- Application images use `ghcr.io/valhaimerd/...:staging`.
- The Windows browser reaches all four VM URLs.
- Unify's cards navigate to the Kali VM, not `localhost` on Windows.
- CRUD records survive a later application-image deployment because PostgreSQL uses the named volume.
- Stopping `project2` greys the Faculty card and marks its overview offline; restarting it restores both.

Use this outage demonstration:

```bash
docker compose -f docker.yaml stop project2
docker compose -f docker.yaml start project2
```

Do not delete the volume during this test.

## Completion report

Report back with:

- Kali version and architecture
- Docker and Compose versions
- VM IP and VMware network mode
- Runner name, labels, and online status (never its token)
- Whether `/opt/adockerwsl/.env` exists with mode `600` (never its contents)
- The five-container health summary
- The GitHub Actions run URL for the first automatic deployment
- Any deviations or remaining blockers
