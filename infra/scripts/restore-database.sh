#!/usr/bin/env bash
# Database restore script for UNED Extension Finder
# Restores PostgreSQL backup from backup file

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"
BACKUP_DIR="${PROJECT_DIR}/infra/backups"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}🔄 UNED Database Restore${NC}"
echo "=================================="

# Check if backup file is provided
if [ $# -eq 0 ]; then
    echo -e "${RED}❌ Error: No backup file specified${NC}"
    echo ""
    echo -e "${YELLOW}Usage:${NC}"
    echo "  $0 <backup_file.sql.gz>"
    echo ""
    echo -e "${YELLOW}Available backups:${NC}"
    if [ -d "${BACKUP_DIR}" ]; then
        ls -lht "${BACKUP_DIR}"/*.sql.gz 2>/dev/null || echo "  (no backups found)"
    else
        echo "  (no backup directory found)"
    fi
    exit 1
fi

BACKUP_FILE="$1"

# Check if file exists
if [ ! -f "${BACKUP_FILE}" ]; then
    echo -e "${RED}❌ Backup file not found: ${BACKUP_FILE}${NC}"
    exit 1
fi

# Check if container is running
if ! docker ps --format '{{.Names}}' | grep -q "uned-postgres-qa"; then
    echo -e "${RED}❌ PostgreSQL container is not running${NC}"
    echo "Start it with: docker-compose -f infra/compose.qa.yaml up -d db"
    exit 1
fi

echo -e "${YELLOW}⚠️  WARNING: This will delete ALL existing data!${NC}"
echo -e "Backup file: ${BACKUP_FILE}"
echo ""
read -p "Are you sure you want to continue? (yes/no): " -r
echo

if [[ ! $REPLY =~ ^[Yy]es$ ]]; then
    echo -e "${YELLOW}Restore cancelled${NC}"
    exit 0
fi

echo -e "${YELLOW}📥 Restoring backup...${NC}"

# Decompress if needed
TEMP_FILE="${BACKUP_FILE}"
if [[ "${BACKUP_FILE}" == *.gz ]]; then
    TEMP_FILE="${BACKUP_FILE%.gz}"
    gunzip -c "${BACKUP_FILE}" > "${TEMP_FILE}"
fi

# Restore backup using psql
docker exec -i uned-postgres-qa psql \
    -U postgres \
    < "${TEMP_FILE}"

# Clean up temp file
if [[ "${BACKUP_FILE}" == *.gz ]]; then
    rm "${TEMP_FILE}"
fi

echo -e "${GREEN}✅ Restore completed!${NC}"
echo ""
echo -e "${GREEN}💡 Verify the restore:${NC}"
echo "   docker exec -it uned-postgres-qa psql -U postgres -d uned_activities -c '\\dt harvest.*'"
echo "   docker exec -it uned-postgres-qa psql -U postgres -d uned_activities -c 'SELECT COUNT(*) FROM harvest.activities;'"
