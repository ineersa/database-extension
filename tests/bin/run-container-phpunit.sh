#!/usr/bin/env bash

set -euo pipefail

: "${SYMFONY_CONSTRAINT:?SYMFONY_CONSTRAINT must be set}"

RUN_COMMAND="${RUN_COMMAND:-test}"

WORKDIR="/tmp/database-extension-tests"
rm -rf "${WORKDIR}"
mkdir -p "${WORKDIR}"

cp -R /app/. "${WORKDIR}/"
rm -rf "${WORKDIR}/.git"

cd "${WORKDIR}"

composer update --no-interaction --prefer-dist --no-progress --with-all-dependencies "symfony/*:${SYMFONY_CONSTRAINT}"

case "${RUN_COMMAND}" in
    test)
        vendor/bin/phpunit
        ;;
    coverage)
        COVERAGE_OUTPUT_DIR="${COVERAGE_OUTPUT_DIR:-/app/coverage}"
        mkdir -p "${COVERAGE_OUTPUT_DIR}"
        rm -rf "${COVERAGE_OUTPUT_DIR:?}"/*
        XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text --coverage-html "${COVERAGE_OUTPUT_DIR}" --coverage-clover "${COVERAGE_OUTPUT_DIR}/clover.xml"
        ;;
    lint)
        composer lint:local
        ;;
    fix)
        composer fix:local
        ;;
    *)
        echo "Unsupported RUN_COMMAND: ${RUN_COMMAND}" >&2
        exit 1
        ;;
esac
