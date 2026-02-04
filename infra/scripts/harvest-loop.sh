#!/bin/sh
set -e

MAX_PAGES="${HARVEST_MAX_PAGES:-50}"
INTERVAL_SECONDS="${HARVEST_INTERVAL_SECONDS:-604800}"

while true; do
  echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] Starting harvest (max pages: ${MAX_PAGES})"
  php /app/apps/CliJobs/bin/harvest.php --max-pages="${MAX_PAGES}"
  echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] Harvest complete. Sleeping ${INTERVAL_SECONDS}s"
  sleep "${INTERVAL_SECONDS}"
done
