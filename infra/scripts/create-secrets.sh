#!/bin/bash
# Script para crear secrets de Docker Swarm para deployment en producción

set -e

echo "🔐 Creando secrets de Docker Swarm para UNED Activities Finder"
echo

# Verificar que estamos en un nodo manager de Swarm
if ! docker info | grep -q "Swarm: active"; then
    echo "❌ Error: Docker Swarm no está activo en este nodo"
    echo "   Inicializa Swarm primero: docker swarm init"
    exit 1
fi

# Crear secret de PostgreSQL password
if ! docker secret ls | grep -q postgres_password; then
    echo -n "📝 Ingresa la contraseña de PostgreSQL: "
    read -s POSTGRES_PASSWORD
    echo
    echo "$POSTGRES_PASSWORD" | docker secret create postgres_password -
    echo "✅ Secret postgres_password creado"
else
    echo "⏭️  Secret postgres_password ya existe"
fi

# Crear secret de Redis password
if ! docker secret ls | grep -q redis_password; then
    echo -n "📝 Ingresa la contraseña de Redis: "
    read -s REDIS_PASSWORD
    echo
    echo "$REDIS_PASSWORD" | docker secret create redis_password -
    echo "✅ Secret redis_password creado"
else
    echo "⏭️  Secret redis_password ya existe"
fi

# Crear secret de JWT
if ! docker secret ls | grep -q jwt_secret; then
    echo -n "📝 Ingresa el secreto JWT (min 32 caracteres): "
    read -s JWT_SECRET
    echo
    if [ ${#JWT_SECRET} -lt 32 ]; then
        echo "❌ El secreto JWT debe tener al menos 32 caracteres"
        exit 1
    fi
    echo "$JWT_SECRET" | docker secret create jwt_secret -
    echo "✅ Secret jwt_secret creado"
else
    echo "⏭️  Secret jwt_secret ya existe"
fi

# Crear secret de SMTP password
if ! docker secret ls | grep -q smtp_password; then
    echo -n "📝 Ingresa la contraseña SMTP (opcional, Enter para saltar): "
    read -s SMTP_PASSWORD
    echo
    if [ -n "$SMTP_PASSWORD" ]; then
        echo "$SMTP_PASSWORD" | docker secret create smtp_password -
        echo "✅ Secret smtp_password creado"
    else
        echo "" | docker secret create smtp_password -
        echo "⏭️  Secret smtp_password creado vacío"
    fi
else
    echo "⏭️  Secret smtp_password ya existe"
fi

echo
echo "🎉 Todos los secrets han sido configurados correctamente"
echo
echo "📋 Lista de secrets:"
docker secret ls | grep -E "postgres_password|redis_password|jwt_secret|smtp_password"

echo
echo "✨ Siguiente paso: Despliega el stack con:"
echo "   docker stack deploy -c infra/compose.stack.yml uned-activities"
