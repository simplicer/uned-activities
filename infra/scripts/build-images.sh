#!/bin/bash
# Script para construir y subir imágenes Docker para deployment en producción

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"

usage() {
    echo "Uso: ./build-images.sh [version] [--push]"
    echo "Ejemplo: ./build-images.sh 1.0.0 --push"
}

# Leer versión desde VERSION.md o usar argumento
VERSION=""
PUSH="false"

for arg in "$@"; do
    case "$arg" in
        --push)
            PUSH="true"
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            if [ -z "$VERSION" ]; then
                VERSION="$arg"
            else
                echo "❌ Argumento no reconocido: $arg"
                usage
                exit 1
            fi
            ;;
    esac
done

if [ -z "$VERSION" ]; then
    VERSION="$(grep -oP 'Current Version:\s*\K[0-9]+\.[0-9]+\.[0-9]+(?:-[0-9A-Za-z.-]+)?' "${ROOT_DIR}/VERSION.md" | head -1 || true)"
fi

REGISTRY_HOST=${REGISTRY_HOST:-registry.storage.simplicer.com}
REGISTRY_NAMESPACE=${REGISTRY_NAMESPACE:-antonio}
IMAGE_PREFIX="${REGISTRY_HOST}/${REGISTRY_NAMESPACE}"
VITE_API_URL=${VITE_API_URL:-https://api.lexemas.com}

if [ -z "$VERSION" ]; then
    echo "❌ Error: No se pudo determinar la versión"
    usage
    exit 1
fi

echo "🏗️  Construyendo imagen Docker para versión $VERSION"
echo "📦 Registry: $IMAGE_PREFIX"
echo

# Moverse al directorio raíz del proyecto
cd "${ROOT_DIR}"

# Construir imagen unificada (backend + frontend static)
echo "📦 Construyendo backend unificado (incluye frontend estático)..."
docker build \
    --build-arg APP_VERSION="$VERSION" \
    --build-arg BUILD_DATE="$(date -u +'%Y-%m-%dT%H:%M:%SZ')" \
    --build-arg VCS_REF="$(git rev-parse --short HEAD)" \
    --build-arg VITE_API_URL="$VITE_API_URL" \
    -t "${IMAGE_PREFIX}/uned-backend:${VERSION}" \
    -t "${IMAGE_PREFIX}/uned-backend:latest" \
    -f containers/Containerfile.backend \
    .
echo "✅ Imagen backend unificada construida"
echo

if [ "$PUSH" != "true" ]; then
    echo "📤 ¿Subir imágenes al registry $IMAGE_PREFIX? (y/N)"
    read -r PUSH_ANSWER
    if [ "$PUSH_ANSWER" = "y" ] || [ "$PUSH_ANSWER" = "Y" ]; then
        PUSH="true"
    fi
fi

if [ "$PUSH" = "true" ]; then
    echo "🚀 Subiendo imágenes..."

    docker push "${IMAGE_PREFIX}/uned-backend:${VERSION}"
    docker push "${IMAGE_PREFIX}/uned-backend:latest"
    
    echo "✅ Imagen subida correctamente"
    echo
    echo "🎉 Deployment ready!"
    echo "   App: ${IMAGE_PREFIX}/uned-backend:${VERSION}"
    echo
    echo "📋 Siguiente paso:"
    echo "   VERSION=${VERSION} docker stack deploy -c infra/compose.stack.yml uned-activities --with-registry-auth"
else
    echo "⏭️  Subida omitida"
    echo
    echo "💾 Imágenes construidas localmente:"
    docker images | grep -E "uned-backend"
fi
