# 🔄 AGENTIC LOOP PROMPT - Complete Task Automation

**Descripción:** Este prompt ejecuta automáticamente 5 tareas sin pausas, testeando cada una y haciendo commits convenionales.

---

## 🚀 COPIA ESTE PROMPT COMPLETO EN CLAUDE

```
# AGENTIC TASK LOOP - UNED Activities Finder Post-Audit Tasks

## CONTEXTO DEL PROYECTO
- Repositorio: /home/antonio/CODE/anvius-uned-extension-finder
- Lenguaje: PHP 8.3, React, TypeScript
- Estado: Post-auditoría (12 errores críticos identificados)
- Licencia Objetivo: MIT

## ARCHIVOS RELEVANTES A CONSIDERAR
@README.md
@doc/api-specs/openapi.yaml
@package.json
@composer.json
@AUDIT_SUMMARY.md

## VERSIONING STRATEGY
Basado en commits convencionales:
- **feat:** = minor version bump (X.0.0)
- **fix:** = patch version bump (0.0.X)
- **chore/docs:** = no bump
- Formato final: 0.X.Y-alpha

Del git log analizado:
- feat commits encontrados: 10+ (por lo tanto, 0.10.0 mínimo)
- fix commits encontrados: 1 (1 patch)
- **Versión resultante: 0.10.1-alpha**

---

## TAREAS A EJECUTAR (SIN PAUSAS)

### TAREA 1: Añadir LICENSE (MIT)
**Ubicación:** /LICENSE (raíz)
**Contenido:** Standard MIT License
**Verificación:** 
- [ ] Archivo existe
- [ ] Contiene "Permission is hereby granted"
- [ ] Contiene "THE SOFTWARE IS PROVIDED "AS IS""
**Commit:** `feat: add MIT license`

### TAREA 2: Crear VERSIONING.md
**Ubicación:** /VERSION.md o similar
**Contenido:** 
- Versión actual: 0.10.1-alpha
- Explicación de estrategia semver
- Cálculo de versión
**Verificación:**
- [ ] Archivo existe
- [ ] Contiene "0.10.1-alpha"
- [ ] Explicación clara
**Commit:** `chore: add version file with semver strategy`

### TAREA 3: Actualizar README.md
**Ubicación:** /README.md
**Cambios necesarios:**
- Agregar versión en header: `# UNED Activities Finder v0.10.1-alpha`
- Agregar sección "License" al final
- Mejorar descripción inicial (ver TAREA 5)
**Verificación:**
- [ ] README contiene versión
- [ ] README contiene sección License
- [ ] Syntax markdown válido
**Commit:** `docs: update README with version and license info`

### TAREA 4: Actualizar openapi.yaml
**Ubicación:** /doc/api-specs/openapi.yaml
**Cambios necesarios:**
- Línea `version:` cambiar de `1.0.0` a `0.10.1-alpha`
- Mantener descripción igual
**Verificación:**
- [ ] YAML es válido (prueba: `yamllint`)
- [ ] Version field contiene `0.10.1-alpha`
- [ ] Sin caracteres inválidos
**Commit:** `chore: bump API version to 0.10.1-alpha`

### TAREA 5: Redactar nuevo README (COMPLETO)
**Ubicación:** /README.md (reemplazar, manteniendo estructura técnica)
**Tipo:** Manual de usuario + instalación (NO detalles técnicos internos)
**Contenido debe incluir:**

1. **Portada:**
   - Título: UNED Activities Finder v0.10.1-alpha
   - Tagline: "Find and stay updated on UNED extension activities in your language"
   - Badges: License, Version, Status

2. **What is it? (qué es)**
   - Descripción clara: busca actividades de extensión de UNED
   - Problema que resuelve
   - Público objetivo

3. **Key Features:**
   - 6 idiomas soportados (ES, EN, CA, VA, EU, GL)
   - Búsqueda y filtrado
   - Notificaciones personalizadas
   - Precios y detalles

4. **Getting Started (instalación usuario)**
   - Web: Cómo acceder (URL será prod)
   - Docker Compose: Para self-hosted
   - Requisitos: Docker, Docker Compose
   - Pasos 1-5 simple

5. **How to Use:**
   - Buscar actividades
   - Guardar búsquedas
   - Configurar notificaciones
   - Ver detalles de precios

6. **FAQ:**
   - ¿Cómo me registro?
   - ¿Es gratuito?
   - ¿Qué idiomas soporta?
   - ¿Cómo reporto un error?

7. **License & Attribution:**
   - MIT License
   - Contributors
   - Contacto

**Verificación:**
- [ ] README NO contiene términos técnicos internos (DDD, Hexagonal, etc)
- [ ] README es legible por usuarios finales
- [ ] Contiene pasos concretos de instalación
- [ ] Markdown válido
- [ ] Versión correcta en header

**Commit:** `docs: rewrite README for end-users with installation guide`

---

## PROCESO PARA CADA TAREA

### Para cada tarea, ejecuta EN ORDEN:

1. **LEE:** El contexto del archivo actual (si existe)
2. **CREA/MODIFICA:** El archivo según especificación
3. **VERIFICA:** Todos los puntos de verificación (✓)
4. **TESTEA:**
   - Si es YAML: valida con yamllint o jq
   - Si es Markdown: verifica sintaxis
   - Si es código: verifica que no haya caracteres inválidos
5. **COMMITTER:** 
   ```bash
   git add [archivo]
   git commit -m "[MENSAJE CONVENCIONAL AQUÍ]"
   git log -1 --oneline
   ```
6. **CONFIRMA:** El commit fue exitoso
7. **PASA A SIGUIENTE:** Sin hacer preguntas

---

## VERIFICACIONES FINALES (Al terminar todas 5 tareas)

```bash
# 1. Verificar todos los archivos existen
ls -la LICENSE VERSION.md README.md doc/api-specs/openapi.yaml

# 2. Verificar commits están en git
git log --oneline -5

# 3. Verificar README no tiene términos técnicos
grep -i "hexagonal\|ddd\|bounded\|aggregate\|port\|adapter" README.md || echo "✓ No technical terms found"

# 4. Verificar openapi.yaml es válido
python3 -c "import yaml; yaml.safe_load(open('doc/api-specs/openapi.yaml'))" && echo "✓ YAML valid"

# 5. Verificar versión correcta
grep "0.10.1-alpha" VERSION.md README.md doc/api-specs/openapi.yaml | wc -l
# Debe mostrar 3 (VERSION.md, README.md, openapi.yaml)
```

---

## COMMITS ESPERADOS

Al final deberías ver esto:

```
ccd1234 docs: rewrite README for end-users with installation guide
abc1232 chore: bump API version to 0.10.1-alpha
def1231 docs: update README with version and license info
ghi1230 chore: add version file with semver strategy
jkl1229 feat: add MIT license
```

---

## INSTRUCCIONES ESPECIALES

✅ **DEBE HACER:**
- Procesar sin pausas (no preguntar)
- Testar cada cambio ANTES de commitear
- Usar mensajes convencionales en inglés
- Hacer commits atomicos (un cambio por commit)
- Verificar que todo el work tree esté clean

❌ **NO DEBE:**
- Preguntar al usuario entre tareas
- Cambiar versiones (usar 0.10.1-alpha exactamente)
- Incluir términos técnicos en README nuevo
- Hacer rebases o squashes (commits separados)

---

## COMANDOS ÚTILES

```bash
# Verificar estado
git status
git log -1

# Revertir un commit si algo sale mal
git revert HEAD --no-edit

# Ver cambios antes de commitear
git diff --cached

# Verificar archivo YAML
python3 -c "import yaml; print(yaml.safe_load(open('doc/api-specs/openapi.yaml')))"

# Verificar archivo JSON
python3 -c "import json; json.load(open('package.json'))"
```

---

## 🎯 GOAL: 5 Commits + Clean Status

Cuando termines, deberías poder ejecutar:
```bash
git log -5 --oneline
# Ver 5 commits nuevos

git status
# En clean state
```

¡Adelante! Comienza por TAREA 1: Añadir LICENSE.
```

---

## 📋 CHECKLIST DE EJECUCIÓN

Copia este checklist y marca a medida que avanzas:

- [ ] TAREA 1: LICENSE creada y committeada
- [ ] TAREA 2: VERSIONING.md creada y committeada
- [ ] TAREA 3: README actualizado con versión y license
- [ ] TAREA 4: openapi.yaml actualizado a 0.10.1-alpha
- [ ] TAREA 5: README reescrito para usuarios finales
- [ ] Verificaciones finales pasadas
- [ ] Git status clean
- [ ] 5 commits en el log

**Próximo paso:** Copia el prompt entre las líneas de backticks en Claude y ejecútalo.
