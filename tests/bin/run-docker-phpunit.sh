#!/usr/bin/env bash

set -euo pipefail

COMPOSE_FILE="docker-compose.test.yaml"
MODE="${1:-matrix}"

cleanup() {
    docker compose -f "${COMPOSE_FILE}" down --remove-orphans
}

trap cleanup EXIT

docker compose -f "${COMPOSE_FILE}" up -d mysql postgres

case "${MODE}" in
    php82)
        docker compose -f "${COMPOSE_FILE}" build php82-sf73
        docker compose -f "${COMPOSE_FILE}" run --rm -e RUN_COMMAND=test php82-sf73
        ;;
    php84)
        docker compose -f "${COMPOSE_FILE}" build php84-sf80
        docker compose -f "${COMPOSE_FILE}" run --rm -e RUN_COMMAND=test php84-sf80
        ;;
    matrix)
        docker compose -f "${COMPOSE_FILE}" build php82-sf73 php84-sf80
        docker compose -f "${COMPOSE_FILE}" run --rm -e RUN_COMMAND=test php82-sf73
        docker compose -f "${COMPOSE_FILE}" run --rm -e RUN_COMMAND=test php84-sf80
        ;;
    *)
        echo "Unknown mode '${MODE}'. Use php82, php84, or matrix." >&2
        exit 1
        ;;
esac
