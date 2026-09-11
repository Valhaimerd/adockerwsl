# Classroom Presentation Runbook

## 1. Show the environment — 2 minutes

- Open the repository in VS Code through WSL.
- Show that Docker Desktop reports its engine as running.
- Run `docker version` and `docker compose version` in the VS Code WSL terminal.

## 2. Explain the Compose file — 3 minutes

- Point out the four independently built Laravel images.
- Point out the single official PostgreSQL image and named volume.
- Contrast internal names such as `postgres:5432` with host access such as `localhost:5433`.
- Explain that the initialization script creates four databases only when the named volume is empty.

## 3. Start the local build — 3 minutes

```bash
docker compose -f docker.yaml up --build -d
docker compose -f docker.yaml ps
```

Wait until all five services report `healthy`, then show the group in Docker Desktop.

## 4. Show the sites — 3 minutes

- Open `http://localhost:8080`.
- Open each available project card and use its Back to Unify link.
- Optionally open `http://localhost:8081`, `http://localhost:8082`, and `http://localhost:8083` directly.
- Refresh pgAdmin on `localhost:5433` and show the four databases.

## 5. Demonstrate container independence — 3 minutes

```bash
docker compose -f docker.yaml stop project2
```

- Wait at most five seconds and show the grey Project 2 card.
- Point out that Unify and the other projects are still running.

```bash
docker compose -f docker.yaml start project2
```

- Show the Project 2 card recover automatically.

## 6. Show staging images — 3 minutes

- Open the successful GitHub Actions Staging workflow.
- Show the four public GHCR packages.
- Stop the local stack, pull the staging tags, and start with `--no-build` using the commands in the README.

## 7. Finish cleanly — 1 minute

```bash
docker compose -f docker.yaml down
```

- Explain that the named PostgreSQL volume remains for the next run.
- Do not add `-v` unless the demonstration data should be intentionally reset.
