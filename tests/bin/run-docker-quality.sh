#!/usr/bin/env bash

set -euo pipefail

COMPOSE_FILE="docker-compose.test.yaml"
COMMAND="${1:-lint}"

case "${COMMAND}" in
    lint|fix)
        ;;
    *)
        echo "Unknown command '${COMMAND}'. Use lint or fix." >&2
        exit 1
        ;;
esac

docker compose -f "${COMPOSE_FILE}" build php82-sf73
docker compose -f "${COMPOSE_FILE}" run --rm --no-deps \
    -e RUN_COMMAND="${COMMAND}" \
    php82-sf73
