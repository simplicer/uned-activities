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
    log_message "INFO" "Harvest loop started"

    while true; do
        retry_harvest || log_message "ERROR" "Giving up on this cycle"

        # Sleep 1 hour before next harvest attempt
        log_message "INFO" "Sleeping 1 hour until next harvest cycle"
        sleep 3600
    done
}

main
