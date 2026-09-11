#!/usr/bin/env bash

set -Eeuo pipefail

deploy_dir="${ADOCKERWSL_DEPLOY_DIR:-/opt/adockerwsl}"
cd "$deploy_dir"

if [[ ! -f .env ]]; then
    echo "Missing $deploy_dir/.env. Follow docs/kali-vm-staging-handoff.md first." >&2
    exit 1
fi

compose=(docker compose -f docker.yaml)
"${compose[@]}" config --quiet

if "${compose[@]}" config --images | grep -q '^local/'; then
    echo 'Refusing deployment because IMAGE_PREFIX still resolves to local.' >&2
    exit 1
fi

"${compose[@]}" pull unify project1 project2 project3 postgres
"${compose[@]}" up --no-build --detach --wait --wait-timeout 180

for port in 8080 8081 8082 8083; do
    curl --fail --silent --show-error "http://localhost:${port}/health" >/dev/null
done

status_json="$(curl --fail --silent --show-error http://localhost:8080/api/projects/status)"
overview_json="$(curl --fail --silent --show-error http://localhost:8080/api/school/overview)"

python3 - "$status_json" "$overview_json" <<'PY'
import json
import sys

statuses = json.loads(sys.argv[1])["projects"]
sections = json.loads(sys.argv[2])["sections"]

assert len(statuses) == 3 and all(project["available"] for project in statuses)
assert [section["title"] for section in sections] == ["Students", "Faculty", "Courses"]
assert all(section["available"] for section in sections)
PY

"${compose[@]}" ps
echo 'Kali staging deployment is healthy.'
