#!/bin/bash
#
# Upload a local pg_dump custom-format file to a Swarm host and restore it into
# the stack's Postgres service, without requiring rsync/scp.
#
# Usage:
#   infra/scripts/push-dump-and-restore.sh \
#     --host ubuntu@37.187.145.200 \
#     --stack uned-activities-f4ilt5 \
#     --dump ./uned_activities.dump
#
# Notes:
# - Requires passwordless sudo for docker on the remote host (as in your setup).
# - Scales backend/harvester to 0 during restore to avoid traffic errors.
#
set -euo pipefail

HOST=""
STACK=""
DUMP=""
DB_NAME="uned_activities"
DB_USER="postgres"

usage() {
  echo "Usage: $0 --host user@ip --stack STACK_NAME --dump /path/to.dump [--db-name NAME] [--db-user USER]" >&2
}

while [ $# -gt 0 ]; do
  case "$1" in
    --host) HOST="${2:-}"; shift 2;;
    --stack) STACK="${2:-}"; shift 2;;
    --dump) DUMP="${2:-}"; shift 2;;
    --db-name) DB_NAME="${2:-}"; shift 2;;
    --db-user) DB_USER="${2:-}"; shift 2;;
    -h|--help) usage; exit 0;;
    *) echo "Unknown arg: $1" >&2; usage; exit 1;;
  esac
done

if [ -z "$HOST" ] || [ -z "$STACK" ] || [ -z "$DUMP" ]; then
  usage
  exit 1
fi

if [ ! -f "$DUMP" ]; then
  echo "Dump file not found: $DUMP" >&2
  exit 1
fi

REMOTE_DUMP="/tmp/${STACK}-uned_activities.dump"

echo "1) Uploading dump to $HOST:$REMOTE_DUMP"
ssh "$HOST" "cat > '$REMOTE_DUMP'" < "$DUMP"
ssh "$HOST" "ls -lh '$REMOTE_DUMP'"

echo "2) Detecting current replicas"
backend_repl="$(ssh "$HOST" "sudo docker service inspect ${STACK}_backend --format '{{.Spec.Mode.Replicated.Replicas}}' 2>/dev/null || true")"
harvester_repl="$(ssh "$HOST" "sudo docker service inspect ${STACK}_harvester --format '{{.Spec.Mode.Replicated.Replicas}}' 2>/dev/null || true")"
backend_repl="${backend_repl:-0}"
harvester_repl="${harvester_repl:-0}"
echo "   backend replicas=$backend_repl"
echo "   harvester replicas=$harvester_repl"

echo "3) Scaling down backend/harvester during restore"
ssh "$HOST" "sudo docker service scale ${STACK}_backend=0 ${STACK}_harvester=0 >/dev/null 2>&1 || true"

echo "4) Finding Postgres container"
db_cid="$(ssh "$HOST" "sudo docker ps --format '{{.ID}} {{.Names}}' | awk '\$2 ~ /^${STACK}_db\\.1\\./ {print \$1; exit}'")"
if [ -z "$db_cid" ]; then
  echo "Could not find db container for stack '${STACK}'. Is it deployed and running?" >&2
  exit 1
fi
echo "   db container id=$db_cid"

echo "5) Waiting for Postgres readiness"
ssh "$HOST" "sudo docker exec '$db_cid' pg_isready -U '$DB_USER' -d '$DB_NAME' >/dev/null 2>&1 || true"
ssh "$HOST" "for i in \$(seq 1 60); do sudo docker exec '$db_cid' pg_isready -U '$DB_USER' -d '$DB_NAME' >/dev/null 2>&1 && exit 0; sleep 1; done; exit 1"

echo "6) Copy dump into the Postgres container"
ssh "$HOST" "sudo docker cp '$REMOTE_DUMP' '$db_cid:/tmp/uned_activities.dump'"

echo "7) Restoring (pg_restore --clean --if-exists)"
ssh "$HOST" "sudo docker exec -i '$db_cid' pg_restore -U '$DB_USER' -d '$DB_NAME' --clean --if-exists /tmp/uned_activities.dump"

echo "8) Scaling backend/harvester back up"
ssh "$HOST" "sudo docker service scale ${STACK}_backend=${backend_repl} ${STACK}_harvester=${harvester_repl} >/dev/null 2>&1 || true"

echo "9) Done"
echo "   Tip: check logs with:"
echo "     ssh $HOST \"sudo docker service logs --tail 200 ${STACK}_backend\""
