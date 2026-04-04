#!/usr/bin/env bash

set -euo pipefail

COMPOSE_FILE="docker-compose.test.yaml"

cleanup() {
    docker compose -f "${COMPOSE_FILE}" down --remove-orphans
}

trap cleanup EXIT

mkdir -p coverage

docker compose -f "${COMPOSE_FILE}" up -d mysql postgres
docker compose -f "${COMPOSE_FILE}" build php84-sf80
docker compose -f "${COMPOSE_FILE}" run --rm \
    -e RUN_COMMAND=coverage \
    -e COVERAGE_OUTPUT_DIR=/app/coverage \
    php84-sf80
