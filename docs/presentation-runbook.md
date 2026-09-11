# ADockerWSL Classroom Presentation Runbook

This runbook presents one application moving through two deliberately separate environments:

```text
WSL development → GitHub Actions verification → GHCR images → Kali staged deployment
```

Target presentation time: approximately 20–25 minutes, plus any time spent waiting for the live GitHub Actions run.

## Environment labels used below

- **WSL terminal:** VS Code connected to `/home/valhaimerd/projects`.
- **Windows:** Docker Desktop, browser, GitHub, and optionally pgAdmin.
- **Kali terminal:** VMware guest using `/opt/adockerwsl`.
- **Development:** locally built images tagged `local/...:development`.
- **Staging:** published images tagged `ghcr.io/valhaimerd/...:staging`.

Do not interchange commands between the WSL and Kali terminals. Both machines use the same service names and ports, but they have separate Docker Engines, containers, networks, and PostgreSQL volumes.

---

## Before class — required preflight

Complete this before the audience arrives. Do not attempt the live deployment if any required item fails.

### 1. Verify and prebuild WSL development

Run from the repository root:

```bash
cd /home/valhaimerd/projects
git status --short --branch
git log -1 --oneline
docker version
docker compose version
docker compose -f docker.yaml config --quiet
docker compose -f docker.yaml build
docker compose -f docker.yaml up -d --wait --wait-timeout 180
docker compose -f docker.yaml ps
```

Confirm all five services are healthy:

```bash
for port in 8080 8081 8082 8083; do
  curl --fail --silent "http://localhost:${port}/health"
  printf '\n'
done
```

Open the three CRUD pages once and make sure the presentation values `mika@school.test`, `lina@school.test`, and `CS101` do not already exist. If they remain from a rehearsal, delete them through the application UI; do not erase the entire database volume.

After the check, either leave the stack running or stop it so the class can watch it start:

```bash
docker compose -f docker.yaml down
```

This preserves `adockerwsl_postgres_data`. Never add `-v` unless the development data should be deliberately erased.

### 2. Confirm a releasable branch state

The working tree must be clean, `main` must contain the intended demonstration, and `staging` must not contain unrelated commits:

```bash
git fetch origin
git status --short --branch
git log --oneline origin/staging..main
git merge-base --is-ancestor origin/staging main && echo "staging can fast-forward"
```

If the final command fails, do not force-push during the presentation. Resolve the branch history beforehand.

### 3. Verify Kali is deployment-ready

Complete Phases 1–6 of [the Kali staging host handoff](kali-vm-staging-handoff.md). Before class, confirm in the Kali terminal:

```bash
git -C /opt/adockerwsl status --short --branch
stat -c '%a %n' /opt/adockerwsl/.env
docker version
docker compose version
hostname -I
```

Required state:

- `/opt/adockerwsl/.env` exists with mode `600`.
- Its image prefix is `ghcr.io/valhaimerd` and tag is `staging`.
- Its four public URLs use the stable Kali VM address.
- The repository-level runner is **Idle** with labels `self-hosted`, `Linux`, `X64`, and `adockerwsl-kali`.
- Repository variable `KALI_DEPLOY_ENABLED` is `true`.
- The runner user can execute Docker without `sudo`.
- Kali can reach GitHub and `ghcr.io` over HTTPS.

Do not display `.env` during the presentation because it contains passwords and application keys.

### 4. Verify Windows-to-Kali networking

Record the Kali VM address as `KALI_VM_IP`, then test it from Windows:

```powershell
ping KALI_VM_IP
```

If ICMP is blocked, that alone does not prove the web ports are unavailable. After the first deployment, test `http://KALI_VM_IP:8080` in the browser.

### 5. Prepare presentation tabs

Open these before presenting:

- Docker Desktop → Containers
- `http://localhost:8080`
- GitHub repository → Actions → Staging
- GitHub repository → Packages
- A Kali terminal in `/opt/adockerwsl`
- Optionally Windows pgAdmin connected to development PostgreSQL at `localhost:5433`

Keep the Kali VM powered on and prevent both Windows and Kali from sleeping during the workflow.

---

## Live presentation

## 1. Introduce the architecture — 2 minutes

Explain the five runtime services:

| Service | Purpose | Development address | Database |
|---|---|---|---|
| `unify` | Read-only school overview and service status | `http://localhost:8080` | `unify_db` |
| `project1` | Students CRUD | `http://localhost:8081` | `project1_db` |
| `project2` | Faculty CRUD | `http://localhost:8082` | `project2_db` |
| `project3` | Courses CRUD | `http://localhost:8083` | `project3_db` |
| `postgres` | One PostgreSQL server hosting four logical databases | `localhost:5433` | All four |

Key talking points:

- Compose coordinates five containers from one `docker.yaml`.
- Each Laravel application has its own image and database credentials.
- One PostgreSQL process can safely host multiple logical databases.
- Containers use Compose DNS names such as `postgres`, `project1`, and `project2`, never fixed container IP addresses.
- Browser traffic uses published host ports; container-to-container traffic uses internal port `80` or PostgreSQL port `5432`.

## 2. Show WSL and Docker Desktop — 2 minutes

In the WSL terminal:

```bash
cd /home/valhaimerd/projects
docker version
docker compose version
docker compose -f docker.yaml config --services
```

Open `docker.yaml` and point out:

- Four independently built Laravel images
- The official `postgres:17-bookworm` image
- Health checks
- Internal service URLs such as `http://project1/health`
- Host ports `8080–8083` and `5433`
- The named `postgres_data` volume

## 3. Start the development environment — 2 minutes

Because images were prebuilt, startup should be quick:

```bash
docker compose -f docker.yaml up -d --wait --wait-timeout 180
docker compose -f docker.yaml ps
```

Show the `adockerwsl` group containing exactly five healthy containers in Docker Desktop.

Explain that normal development after a code change uses:

```bash
docker compose -f docker.yaml up --build -d
```

## 4. Demonstrate the three CRUD applications — 5 minutes

Open `http://localhost:8080`. Point out the Students, Faculty, and Courses cards and their green availability states.

Use these repeatable sample records:

### Students

Open the Students card and add:

```text
Name: Mika Santos
Email: mika@school.test
Program: BS Computer Science
```

### Faculty

Return to Unify, open Faculty, and add:

```text
Name: Dr. Lina Cruz
Email: lina@school.test
Department: Computing
```

### Courses

Return to Unify, open Courses, and add:

```text
Code: CS101
Title: Introduction to Computing
Instructor: Dr. Lina Cruz
```

Demonstrate editing the student program to `BS Information Technology`, then return to Unify. Within five seconds, the School data overview should show all three records.

Explain that:

- Each CRUD page writes only to its assigned database.
- The course instructor is intentionally plain text; there is no cross-database foreign key.
- Unify is read-only and retrieves each service's list endpoint over the Compose network.
- Authentication and roles are intentionally omitted to keep the Docker lesson focused.

Optionally demonstrate deleting and recreating one record.

## 5. Show PostgreSQL isolation — 2 minutes

In Windows pgAdmin, connect to the development database server:

```text
Host: localhost
Port: 5433
Maintenance database: postgres
Username: postgres
Password: POSTGRES_PASSWORD from the ignored WSL .env
```

Expand Databases and show:

```text
unify_db
project1_db
project2_db
project3_db
postgres
```

Explain that this is the PostgreSQL container, not the separately installed Windows PostgreSQL server. The initialization script creates the four application databases only when the named volume is empty.

## 6. Demonstrate an application outage — 3 minutes

Keep Unify open. In the WSL terminal:

```bash
docker compose -f docker.yaml stop project2
```

Within approximately five seconds:

- The Faculty card becomes grey and non-navigable.
- The Faculty overview section reports `Offline`.
- Students, Courses, Unify, and PostgreSQL remain available.

Optionally show the server-side status contract:

```bash
curl --silent http://localhost:8080/api/projects/status | python3 -m json.tool
curl --silent http://localhost:8080/api/school/overview | python3 -m json.tool
```

Recover Faculty:

```bash
docker compose -f docker.yaml start project2
docker compose -f docker.yaml ps
```

Wait for `project2` to become healthy. Unify should automatically restore its Faculty card and data on the next poll.

## 7. Contrast development with staging — 2 minutes

In WSL, show the locally built images:

```bash
docker compose -f docker.yaml images
```

Explain the separation:

```text
WSL development
  Uses local/...:development images
  Builds from source with --build
  Own PostgreSQL volume and records

Kali staging
  Uses ghcr.io/valhaimerd/...:staging images
  Never builds application source
  Own PostgreSQL volume and records
```

The environments share Git history and image artifacts, but they do not share running containers or database data.

## 8. Trigger the staging release — approximately 3–8 minutes

Only run this after the preflight confirms that the Kali runner is online and the branch can fast-forward.

In WSL:

```bash
git status --short --branch
git push origin main:staging
```

If Git reports `Everything up-to-date` because this release was rehearsed, open Actions → Staging → Run workflow and select the `staging` branch.

In GitHub Actions, narrate the job sequence:

1. `verify` installs development dependencies, runs all Laravel tests, builds the images, starts the stack, and checks health and aggregation.
2. `publish` builds and publishes four application images in parallel to GHCR.
3. `deploy` runs only after every publish job succeeds and only when `KALI_DEPLOY_ENABLED=true`.
4. The Kali runner fast-forwards `/opt/adockerwsl`, pulls the new images, recreates changed containers, waits for health, and verifies Unify.

Important safety point: if `verify` or any `publish` job fails, `deploy` never runs and Kali keeps its previous working containers.

While waiting, show the four GHCR package names:

```text
adockerwsl-unify
adockerwsl-project1
adockerwsl-project2
adockerwsl-project3
```

## 9. Show the automatic Kali deployment — 3 minutes

After the GitHub `deploy` job succeeds, switch to the Kali terminal:

```bash
cd /opt/adockerwsl
git log -1 --oneline
docker compose -f docker.yaml ps
docker compose -f docker.yaml images
curl --fail --silent http://localhost:8080/api/projects/status | python3 -m json.tool
```

Confirm:

- Exactly five Kali services are healthy.
- The four application images begin with `ghcr.io/valhaimerd/` and use the `staging` tag.
- The Git commit matches the promoted staging revision.
- The deployment used `--no-build`.

Open these from the Windows browser, replacing `KALI_VM_IP`:

```text
http://KALI_VM_IP:8080  Unify
http://KALI_VM_IP:8081  Students
http://KALI_VM_IP:8082  Faculty
http://KALI_VM_IP:8083  Courses
```

Add one record in Kali Students and return to Kali Unify. Point out that the WSL sample record is absent: staging has a separate PostgreSQL volume. Also confirm the cards navigate to the Kali IP rather than Windows `localhost`.

Docker Desktop normally shows only the WSL Docker Engine. Use the Kali terminal to show Kali's separate containers unless a remote Docker context was intentionally configured.

## 10. Finish cleanly — 1 minute

Stop the WSL development stack while preserving its data:

```bash
cd /home/valhaimerd/projects
docker compose -f docker.yaml down
```

Leave Kali running if it is serving as the staged environment. If it should also be stopped:

```bash
cd /opt/adockerwsl
docker compose -f docker.yaml down
```

Never add `-v` unless that environment's PostgreSQL data should be intentionally deleted.

Conclude with the lifecycle:

```text
Code in WSL
  → automated tests
  → immutable container images
  → registry
  → automatic deployment on a separate Linux machine
  → persistent data survives application replacement
```

---

## Troubleshooting during the presentation

### A GitHub deployment job is queued

Check that the Kali runner is powered on, connected, and labeled correctly:

```text
self-hosted, Linux, X64, adockerwsl-kali
```

Do not change `runs-on` to a GitHub-hosted runner; it cannot deploy to the private Kali Docker Engine.

### The deployment job is skipped

Confirm the repository variable exists:

```text
KALI_DEPLOY_ENABLED=true
```

The variable is intentionally absent or false until Kali is ready.

### Kali deploys but cards point to localhost

Correct the four `*_PUBLIC_URL` values in `/opt/adockerwsl/.env` to use `KALI_VM_IP`, then rerun:

```bash
cd /opt/adockerwsl
./scripts/deploy-staging.sh
```

Do not display the `.env` file on the projector.

### Windows cannot open the Kali URLs

From Kali, verify local service access:

```bash
curl --fail http://localhost:8080/health
hostname -I
```

Then check VMware NAT/bridged networking and the Kali firewall for TCP ports `8080–8083`.

### A service is unhealthy

Inspect only this Compose project:

```bash
docker compose -f docker.yaml ps
docker compose -f docker.yaml logs --tail=100 SERVICE_NAME
```

Do not use broad Docker cleanup commands.

### The database password appears wrong after editing `.env`

PostgreSQL initialization variables apply only when its data volume is first created. Do not automatically delete the volume. For the presentation, restore the password originally used by that environment or deliberately reset the demonstration data before class.

---

## Post-class runner cleanup

Because the repository is public, do not leave the self-hosted runner attached indefinitely.

1. Set `KALI_DEPLOY_ENABLED=false` or delete the repository variable.
2. Remove the runner from Repository → Settings → Actions → Runners.
3. Use GitHub's displayed removal command/token to uninstall the runner service from Kali.
4. Shut down or revert the disposable VMware snapshot.

Stopping containers without `-v` preserves staged demonstration data if the VM will be reused.
