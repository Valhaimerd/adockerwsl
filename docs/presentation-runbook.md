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
- Open Students, Faculty, and Courses from their renamed cards.
- Add one record in each app and demonstrate editing one record.
- Return to Unify and show the three databases combined in the read-only School data overview, then optionally demonstrate delete.
- Optionally open `http://localhost:8081`, `http://localhost:8082`, and `http://localhost:8083` directly.
- Refresh pgAdmin on `localhost:5433` and show the four databases.

## 5. Demonstrate container independence — 3 minutes

```bash
docker compose -f docker.yaml stop project2
```

- Wait at most five seconds and show the grey Faculty card and its offline overview section.
- Point out that Unify, Students, and Courses are still running.

```bash
docker compose -f docker.yaml start project2
```

- Show the Faculty card and overview recover automatically.

## 6. Show staging images — 3 minutes

- Open the successful GitHub Actions Staging workflow.
- Show the four public GHCR packages.
- If the optional Kali host is configured, show that the final workflow job automatically updated its five containers after publishing succeeded.
- Otherwise, stop the local stack, pull the staging tags, and start with `--no-build` using the commands in the README.
- Emphasize that a failed verification or publish job never reaches the Kali deployment job.

## 7. Finish cleanly — 1 minute

```bash
docker compose -f docker.yaml down
```

- Explain that the named PostgreSQL volume remains for the next run.
- Do not add `-v` unless the demonstration data should be intentionally reset.
