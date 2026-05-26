# Registro de Cambios y Documentación del Proyecto

## ⚠️ REGLAS ESTRICTAS PARA TODOS LOS AGENTES (INCLUYENDO IA) ⚠️

1. **Documentación Obligatoria:** Cualquier agente (humano o inteligencia artificial) que trabaje en este proyecto DEBE documentar todos los cambios realizados detalladamente en este archivo.
2. **Prohibido Borrar:** ESTÁ ESTRICTAMENTE PROHIBIDO BORRAR LÍNEAS DE ESTE ARCHIVO. Este documento sirve como un registro histórico inmutable ("append-only") de todas las modificaciones y decisiones tomadas.
3. **Reversión de Cambios:** En caso de que se necesite revertir o deshacer un cambio previo, NO se debe eliminar el registro original. En su lugar, se debe agregar una nueva entrada explicando detalladamente la reversión, haciendo referencia al cambio original y el motivo por el cual se deshizo.
4. **Documentación Exaustiva:** La documentación debe cubrir el máximo posible de detalles sobre el cambio realizado, incluyendo el contexto, el objetivo, la implementación y cualquier información relevante que pueda ser útil para entender el cambio en el futuro.

---

## Historial de Cambios

### [2026-05-25] Inicialización del Registro
- **Agente:** Antigravity (IA)
- **Cambios realizados:** Se creó el archivo `DOCUMENTACION.md` estableciendo las reglas fundamentales e inmutables para el registro de modificaciones en el proyecto.

### [2026-05-25] Integración de Esquema de Base de Datos PostgreSQL
- **Agente:** Antigravity (IA)
- **Contexto:** Se requiere integrar el esquema inicial en PostgreSQL para el SistemaPandora-DSI115, incluyendo la extensión `uuid-ossp`, ENUMs personalizados, tablas para control de acceso (roles y permisos), estructura institucional y perfiles de pacientes.
- **Cambios realizados:**
  - Se crearon las migraciones en `database/migrations` para definir todo el esquema en múltiples archivos secuenciales.
  - `2024_01_01_000000_setup_postgres_extensions_and_enums.php`: Añade la extensión `uuid-ossp` y los tipos ENUM (`sexo_enum`, `estado_civil_enum`, `parentesco_enum`).
  - `2024_01_01_000001_create_users_table.php`: Crea la tabla `users`.
  - `2024_01_01_000002_create_rbac_tables.php`: Crea `roles`, `permisos`, `role_user` y `permission_role`.
  - `2024_01_01_000003_create_institutional_tables.php`: Crea `areas`, `facultades` y `carreras`.
  - `2024_01_01_000004_create_professionals_table.php`: Crea la tabla `profesionales` utilizando `uuid` y llaves foráneas a usuarios y áreas.
  - `2024_01_01_000005_create_patients_tables.php`: Crea las tablas `pacientes` y `contactos_paciente`, agregando las columnas con tipos ENUM usando `DB::statement` y creando los índices de optimización solicitados (búsquedas por carnet e identificadores de eliminación lógica).
