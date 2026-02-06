#!/bin/bash
# Script para construir y subir imágenes Docker para deployment en producción

set -e

# Leer versión desde VERSION.md o usar argumento
VERSION=${1:-$(grep -oP 'Versión actual: \K[0-9]+\.[0-9]+\.[0-9]+-[a-z]+' ../VERSION.md | head -1)}
REGISTRY=${REGISTRY:-ghcr.io/lexemas}

if [ -z "$VERSION" ]; then
    echo "❌ Error: No se pudo determinar la versión"
    echo "   Uso: ./build-images.sh <version>"
    echo "   Ejemplo: ./build-images.sh 0.21.0-alpha"
    exit 1
fi

echo "🏗️  Construyendo imágenes Docker para versión $VERSION"
echo

# Moverse al directorio raíz del proyecto
cd "$(dirname "$0")/../.."

# Construir imagen del backend
echo "📦 Construyendo backend..."
docker build \
    --build-arg VERSION="$VERSION" \
    --build-arg BUILD_DATE="$(date -u +'%Y-%m-%dT%H:%M:%SZ')" \
    --build-arg VCS_REF="$(git rev-parse --short HEAD)" \
    -t "${REGISTRY}/uned-backend:${VERSION}" \
    -t "${REGISTRY}/uned-backend:latest" \
    -f infra/Dockerfile.backend \
    .
echo "✅ Backend construido"
echo

# Construir imagen del frontend
echo "🎨 Construyendo frontend..."
docker build \
    --build-arg VERSION="$VERSION" \
    --build-arg BUILD_DATE="$(date -u +'%Y-%m-%dT%H:%M:%SZ')" \
    -t "${REGISTRY}/uned-frontend:${VERSION}" \
    -t "${REGISTRY}/uned-frontend:latest" \
    -f infra/Dockerfile.frontend \
    .
echo "✅ Frontend construido"
echo

# Preguntar si se deben subir las imágenes
echo "📤 ¿Subir imágenes al registry $REGISTRY? (y/N)"
read -r PUSH

if [ "$PUSH" = "y" ] || [ "$PUSH" = "Y" ]; then
    echo "🚀 Subiendo imágenes..."
    
    # Login al registry (si es necesario)
    if [ "$REGISTRY" = "ghcr.io/lexemas" ]; then
        echo "   Asegúrate de haber hecho login: echo \$GITHUB_TOKEN | docker login ghcr.io -u USERNAME --password-stdin"
    fi
    
    docker push "${REGISTRY}/uned-backend:${VERSION}"
    docker push "${REGISTRY}/uned-backend:latest"
    docker push "${REGISTRY}/uned-frontend:${VERSION}"
    docker push "${REGISTRY}/uned-frontend:latest"
    
    echo "✅ Imágenes subidas correctamente"
    echo
    echo "🎉 Deployment ready!"
    echo "   Backend: ${REGISTRY}/uned-backend:${VERSION}"
    echo "   Frontend: ${REGISTRY}/uned-frontend:${VERSION}"
    echo
    echo "📋 Siguiente paso:"
    echo "   docker stack deploy -c infra/compose.stack.yml uned-activities"
else
    echo "⏭️  Subida omitida"
    echo
    echo "💾 Imágenes construidas localmente:"
    docker images | grep -E "uned-(backend|frontend)"
fi
