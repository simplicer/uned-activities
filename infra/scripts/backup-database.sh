#!/usr/bin/env bash
# Database backup script for UNED Extension Finder
# Creates full PostgreSQL backup with schema and data

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"
BACKUP_DIR="${PROJECT_DIR}/infra/backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/uned_backup_${TIMESTAMP}.sql"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}🗄️  UNED Database Backup${NC}"
echo "=================================="

# Create backup directory
mkdir -p "${BACKUP_DIR}"

# Check if container is running
if ! docker ps --format '{{.Names}}' | grep -q "uned-postgres-qa"; then
    echo -e "${RED}❌ PostgreSQL container is not running${NC}"
    echo "Start it with: docker-compose -f infra/compose.qa.yaml up -d db"
    exit 1
fi

echo -e "${YELLOW}📦 Creating backup...${NC}"

# Create backup using pg_dump
docker exec uned-postgres-qa pg_dump \
    -U postgres \
    --clean \
    --if-exists \
    --create \
    --format=plain \
    --schema=harvest \
    --schema=query \
    --schema=users \
    --schema=notifications \
    --schema=public \
    uned_activities \
    > "${BACKUP_FILE}"

# Compress backup
gzip "${BACKUP_FILE}"
BACKUP_FILE="${BACKUP_FILE}.gz"

# Get file size
FILE_SIZE=$(du -h "${BACKUP_FILE}" | cut -f1)

echo -e "${GREEN}✅ Backup completed!${NC}"
echo ""
echo "📄 Backup file: ${BACKUP_FILE}"
echo "📊 File size: ${FILE_SIZE}"
echo ""

# Show latest backups
echo -e "${YELLOW}📚 Latest backups:${NC}"
ls -lht "${BACKUP_DIR}" | head -n 6

echo ""
echo -e "${GREEN}💡 To restore this backup:${NC}"
echo "   ./infra/scripts/restore-database.sh ${BACKUP_FILE}"
