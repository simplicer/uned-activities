#!/bin/bash

set -euo pipefail

LOG_FILE="/data/logs/harvest.log"
MAX_RETRIES=5
INITIAL_DELAY=5
MAX_DELAY=3600

mkdir -p "$(dirname "$LOG_FILE")"

log_message() {
    local level=$1
    local message=$2
    echo "[$(date -u +'%Y-%m-%dT%H:%M:%SZ')] [$level] $message" | tee -a "$LOG_FILE"
}

seconds_until_next_run() {
    # BusyBox `date` on Alpine doesn't support GNU `-d`, so do pure arithmetic.
    # HARVEST_RUN_TIME_UTC is HH:MM (UTC).
    local run_time_utc="${HARVEST_RUN_TIME_UTC:-03:00}"
    local run_h="${run_time_utc%%:*}"
    local run_m="${run_time_utc##*:}"

    local now_h now_m now_s
    now_h="$(date -u +%H)"
    now_m="$(date -u +%M)"
    now_s="$(date -u +%S)"

    # Force base-10 to avoid leading-zero octal.
    local now_sec=$((10#$now_h * 3600 + 10#$now_m * 60 + 10#$now_s))
    local target_sec=$((10#$run_h * 3600 + 10#$run_m * 60))

    if [ "$target_sec" -le "$now_sec" ]; then
        echo $((86400 - now_sec + target_sec))
    else
        echo $((target_sec - now_sec))
    fi
}

retry_harvest() {
    local attempt=1
    local delay=$INITIAL_DELAY

    while [ $attempt -le $MAX_RETRIES ]; do
        log_message "INFO" "Harvest attempt $attempt/$MAX_RETRIES"

        if php /app/apps/CliJobs/bin/harvest.php --max-pages=50 >> "$LOG_FILE" 2>&1; then
            log_message "INFO" "Harvest completed successfully"
            return 0
        fi

        if [ $attempt -lt $MAX_RETRIES ]; then
            log_message "WARN" "Harvest failed, retrying in ${delay}s (attempt $attempt/$MAX_RETRIES)"
            sleep "$delay"

            # Exponential backoff: min 5s, max 1h
            delay=$((delay * 2))
            if [ $delay -gt $MAX_DELAY ]; then
                delay=$MAX_DELAY
            fi
        fi

        attempt=$((attempt + 1))
    done

    log_message "ERROR" "Harvest failed after $MAX_RETRIES attempts"
    return 1
}

main() {
    log_message "INFO" "Harvest loop started (daily schedule)"

    while true; do
        local sleep_seconds
        sleep_seconds="$(seconds_until_next_run)"
        log_message "INFO" "Next harvest run in ${sleep_seconds}s at ${HARVEST_RUN_TIME_UTC:-03:00} UTC"
        sleep "$sleep_seconds"

        if retry_harvest; then
            # Notifications digest runs right after a successful harvest so
            # saved-search owners learn about fresh activities. The digest job
            # is otherwise never scheduled in the stack (audit finding
            # Notifications.DigestJob:unscheduled-digest).
            if [ "${DIGEST_ENABLED:-true}" != "false" ]; then
                log_message "INFO" "Running notifications digest"
                php /app/apps/CliJobs/bin/digest.php >> "$LOG_FILE" 2>&1 \
                    || log_message "ERROR" "Digest failed (non-fatal)"
            fi
        else
            log_message "ERROR" "Giving up on this cycle"
        fi
    done
}

main
