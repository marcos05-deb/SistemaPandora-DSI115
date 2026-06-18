\# Análisis de la Situación Actual



Bienestar Universitario opera actualmente con gestión fragmentada basada en papel.



Las agendas son administradas de forma aislada por cada profesional, los expedientes físicos dependen de un archivo centralizado y las notas de evolución se redactan a mano.



Esta situación eleva el riesgo de pérdida de información y retrasa la respuesta ante situaciones de crisis psicológica.



!\[Situación Actual](images/SistemaPandora.jpeg)



\---



\# Desarrollo del Sprint 1 (MVP)



\## Sprint Planning



\### Alcance del Sprint: Épicas Trabajadas



El Sprint 1 comprende la implementación completa de las primeras dos épicas del proyecto, que constituyen la base arquitectónica sobre la cual se construirán los módulos clínicos en sprints posteriores.



| Épica | Historias de Usuario Incluidas |

|--------|--------|

| Épica 1: Seguridad y Control de Acceso | HU-01 Autenticación, HU-02 Cierre de Sesión, HU-03 RBAC y Scopes de área |

| Épica 2: Gestión de Identidad y Privacidad | HU-04 Identificador Anónimo, HU-05 Cifrado en Reposo, HU-06 Búsqueda Segura |



Las épicas 3 a 7 (Expediente Multidisciplinario, Agendamiento, Alertas, Analítica y Auditoría) se abordarán en los Sprints 2 y 3 conforme al Product Backlog original.



\### Objetivo del Sprint (Sprint Goal)



Desplegar la infraestructura base de seguridad y el motor de registro clínico.



El sistema permitirá la autenticación de usuarios con sesiones seguras respaldadas por derivación de clave Argon2id, el control de acceso por roles (RBAC) con aislamiento de área mediante Global Scopes, y la creación de expedientes anonimizados con información cifrada en reposo (XSalsa20-Poly1305) en PostgreSQL.



\### Sprint Backlog Completado



| Historia | Cumplimiento Técnico (DoD) |

|-----------|-----------|

| HU-01: Autenticación Base | KeyDerivationService con Argon2id (`sodium\_crypto\_pwhash`). Sesión de servidor con clave derivada volátil `\_sym\_key`. Bloqueo tras 3 intentos fallidos por 15 minutos. |

| HU-02: Cierre de Sesión | Destrucción irrecuperable de `\_sym\_key`, invalidación de sesión activa y regeneración de token CSRF. |

| HU-03: RBAC y Scopes | AreaScope global sobre expedientes. Middleware EnforceAreaScope. Rol Referente Psicosocial añadido al catálogo. |

| HU-04: Identificador Anónimo | UUID v4 autogenerado por PostgreSQL como identificador anónimo del paciente. |

| HU-05: Cifrado en Reposo | EncryptedFieldCast con XSalsa20-Poly1305. Datos PHI ilegibles en base de datos. |

| HU-06: Búsqueda Segura | BlindIndexService (HMAC-SHA256) y SecureSearchController. |

| Dashboard Administrativo | CRUD de especialistas con paginación, filtros y métricas operativas. |

| Candado de Primer Acceso | Middleware RequirePasswordChange y regeneración de claves. |

| Rol Referente Psicosocial | Rol psychosocial\_referent con permisos específicos. |

| Dashboard Clínico | Métricas y pacientes segmentados por profesional. |

| Registro Pacientes Multicontacto | Transacción atómica y cifrado transparente de PHI. |

| Búsqueda por UUID | Resultados anonimizados filtrados por AreaScope. |

| Hardening de Seguridad | Docker multi-stage, CSP, IDOR, Rate Limiting y mínimos privilegios. |

| Compliance-as-Code | 16 pruebas HIPAA y OWASP ASVS v4 con 100 % de éxito. |



\### Adaptaciones al Sprint Backlog



\#### HU-04 — Cambio en el mecanismo de anonimización



\*\*Especificación original\*\*



Generar un código público PND-XXXXX mediante SHA-256 del UUID codificado en Base32 Crockford.



\*\*Decisión adoptada\*\*



El UUID v4 autogenerado por PostgreSQL ofrece entropía suficiente y unicidad absoluta, cumpliendo el requisito de opacidad de identidad sin necesidad de algoritmos adicionales.



\#### HU-01 / HU-02 — JWT descartado, sesiones de servidor adoptadas



\*\*Especificación original\*\*



Emitir tokens JWT con expiración de 8 horas.



\*\*Decisión adoptada\*\*



La clave derivada `\_sym\_key` debe permanecer únicamente en memoria del servidor. Las sesiones stateful de Laravel permiten invalidación inmediata y fortalecen el modelo criptográfico.



\### Cronograma de Actividades



El Sprint 1 se ejecutó en un bloque continuo del 30 de abril al 1 de junio de 2026.



!\[Cronograma Sprint 1](images/CronogramaSprint1.jpeg)



\---



\# Detalle de Implementación Técnica y Arquitectónica



\## 1. Stack Tecnológico



\- Laravel 13

\- Vue 3.5

\- Inertia.js

\- Tailwind CSS v4

\- PostgreSQL 16

\- Docker Compose



\## 2. Arquitectura Criptográfica (HU-01 / HU-05)



Este es el componente de mayor criticidad del sistema.



\- Derivación de claves mediante Argon2id.

\- Clave simétrica `\_sym\_key` almacenada únicamente en sesión volátil.

\- Cifrado de datos PHI mediante XSalsa20-Poly1305.

\- Implementación transparente mediante EncryptedFieldCast.

\- Protección incluso ante acceso directo a la base de datos.



\## 3. Control de Acceso Basado en Roles (HU-03)



\- RBAC mediante AreaScope.

\- Middleware EnforceAreaScope.

\- Rol `psychosocial\_referent`.

\- Políticas PacientePolicy y ExpedientePolicy.



\## 4. Identificador Anónimo y Búsqueda Segura (HU-04 / HU-06)



\### Búsqueda por carnet



\- Coincidencia exacta.

\- Validación de autoría.



\### Búsqueda por UUID



\- Validación mediante SecureSearchController.

\- Filtrado implícito mediante AreaScope.

\- Retorno exclusivo del UUID.



\## 5. Módulo Administrativo y Registro de Pacientes



\- CRUD completo de personal clínico.

\- Contraseñas temporales.

\- URLs firmadas temporales.

\- Registro multicontacto.

\- Transacciones atómicas.



\## 6. Hardening de Seguridad y Pruebas de Cumplimiento



\### Infraestructura



\- Docker multi-stage.

\- Roles PostgreSQL de menor privilegio.

\- Sistema de archivos read\_only.



\### Aplicación



\- CSP estricto.

\- HSTS.

\- X-Frame-Options.

\- Eliminación de vectores XSS.

\- Rate Limiting.



\### Compliance-as-Code



\- 16 pruebas automatizadas.

\- Cobertura HIPAA y OWASP ASVS v4.

\- Reducción de tiempo de ejecución de 63 s a 17 s.

\- 100 % de pruebas exitosas.



\---



\# Análisis y Diseño del Incremento



\## Diagrama de Casos de Uso



!\[Casos de Uso](images/CasosUsoSprint1.jpeg)



\### CU-01: Autenticación Segura (KDF)



\*\*Actor Principal:\*\* Especialista / Coordinador / Referente Psicosocial



\*\*Precondiciones:\*\* Usuario registrado y activo.



\#### Flujo Principal



1\. Ingreso de credenciales.

2\. Verificación de identidad.

3\. Ejecución de Argon2id.

4\. Almacenamiento de `\_sym\_key`.

5\. Redirección según rol.



\#### Flujo Alternativo



\- Bloqueo por fuerza bruta tras tres intentos fallidos durante 15 minutos.



\#### Postcondiciones



\- `\_sym\_key` disponible para operaciones clínicas.



\### CU-05: Registro de Paciente Multicontacto



\*\*Actor Principal:\*\* Referente Psicosocial



\#### Flujo Principal



1\. Completa formulario sociodemográfico.

2\. Asignación automática del UUID.

3\. Inicio de transacción atómica.

4\. Cifrado mediante EncryptedFieldCast.

5\. Registro de contactos asociados.



\#### Flujo Alternativo



\- Rollback automático ante errores de integridad.



\#### Postcondiciones



\- Información PHI protegida para usuarios no autorizados.



!\[Diagrama Secuencia CU-05](images/DiagramaSecuenciaCU05.jpeg)



\### CU-09: Búsqueda Segura por UUID



\*\*Actor Principal:\*\* Especialista / Coordinador de Área



\#### Flujo Principal



1\. Ingreso del UUID.

2\. Validación del formato.

3\. Aplicación de AreaScope.

4\. Retorno únicamente del UUID.

5\. Acceso al recurso autorizado.



\#### Flujo Alternativo



\- Respuesta vacía cuando el paciente está fuera del scope.



\#### Postcondiciones



\- Acceso seguro al expediente.



!\[Diagrama Secuencia CU-09](images/DiagramaSecuenciaCU09.jpeg)



\## Modelo de Dominio y Base de Datos



Los modelos centrales utilizan UUID generados mediante la extensión `uuid-ossp` de PostgreSQL.



RBAC se implementa mediante:



\- roles

\- permisos

\- role\_user

\- permission\_role



!\[Modelo de Dominio](images/ModeloDominioSprint1.jpeg)



!\[Diagrama Entidad Relación](images/DiagramaEntidadRelacion.jpeg)



\---



\# Sprint Review y Evaluación del MVP



\## Estado del Sprint



El equipo completó todas las historias de usuario y criterios de aceptación dentro del período planificado.



El incremento incluye:



\- Código auditado.

\- Migraciones ejecutadas.

\- Entorno Docker unificado.

\- Cobertura automatizada de pruebas.



La Sprint Review con los stakeholders de Bienestar Universitario se encuentra pendiente de programación.



\## Valor Entregado



\- Protección de credenciales y datos clínicos.

\- Administración segura de especialistas.

\- Registro protegido de pacientes.

\- Cumplimiento de requisitos de anonimización exigidos para instituciones públicas.



\---



\# Anexos



\## Bitácora de Daily Meetings



Registro visual de sincronización técnica del equipo SCRUM.



!\[Daily Meeting 1](images/bitacoradaily1.png)



!\[Daily Meeting 2](images/bitacoradaily2.png)



!\[Daily Meeting 3](images/bitacoradaily3.png)



\---



\## Resultados de la Suite Compliance-as-Code



Captura de la ejecución de las 16 pruebas de cumplimiento normativo.



!\[Compliance Suite](images/compliancesuiteresults.png)



\---



\## Arquitectura de Seguridad — Hardening Fases 1 y 2



Diagrama de infraestructura Docker con roles PostgreSQL de menor privilegio, Dockerfile multi-stage y defensa en profundidad.



!\[Arquitectura Hardening](images/ArquitecturaHardening.jpeg)

