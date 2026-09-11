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
