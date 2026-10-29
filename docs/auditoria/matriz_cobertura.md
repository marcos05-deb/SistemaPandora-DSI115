# Matriz de cobertura de auditoría — HU-21

Fecha: 2026-10-24 · Commit base: `feat/hu-21-auditoria-inmutable`

Actualización de cobertura: 2026-10-29. Las lecturas reparadas del dashboard y las HU-15–HU-23 están incluidas en ambas suites completas finales de QA aislado: **362 passed / 1842 assertions**, sin fallos ni omisiones en cada corrida. La verificación local no sustituye la comprobación de privilegios/triggers en el destino autorizado ni las aprobaciones institucionales.

| Módulo | Modelo | Acción | Punto de auditoría | Test | Estado |
|---|---|---|---|---|---|
| Pacientes | Paciente | C/U/D | OwenIt `Auditable` + mask | HU21-E01, E06 | ✅ |
| Pacientes | Paciente | R detalle | `PacienteController@show` → ClinicalAccessAuditor | HU21-E02, E09 | ✅ |
| Pacientes | ContactoPaciente | C/U/D | OwenIt `Auditable` + mask | cobertura E05 | ✅ |
| Expedientes | Expediente | C/U/D | OwenIt + mask + motivo_cambio | AuditLoggingTest / HU21-E06 | ✅ |
| Expedientes | Expediente | R | `PacienteController@show` view_expediente | AuditLoggingTest | ✅ |
| Consultas | Consulta | C/U/D | OwenIt + mask | cobertura E05 | ✅ |
| Citas | Cita | C/U/D | OwenIt + mask | cobertura E05 | ✅ |
| Citas | Cita | R listado | `CitaController@index` recordListing | HU21-E10 | ✅ |
| Historial | Paciente | R | `HistorialController` view_historial | AuditLoggingTest | ✅ |
| Alertas | AlertaPreventiva | C/U + R | Auditable + ClinicalAccessAuditor | HU-16/17 | ✅ |
| Dashboard administrativo y clínico | Agregados de alertas, etiquetas y tendencia suicida | R agregado | `DashboardAlertOverviewService::forUser` → `ClinicalAccessAuditor::recordAggregate`, acción `view_dashboard_overview`; metadatos de alcance y áreas, sin valores clínicos ni resultados | `tests/Feature/Dashboard/DashboardAlertOverviewTest.php`: payload administrativo protegido y evento agregado | Aprobado en ambas suites locales finales |
| Dashboard clínico | AlertaPreventiva | R listado de alertas urgentes | Servicio compartido → `ClinicalAccessAuditor::recordListing` solo para acceso clínico autorizado; consulta acotada a áreas permitidas. Sysadmin no recibe registros individuales | Mismo archivo: acceso clínico conservado, evento de listado y ausencia de acceso del referente excluido | Aprobado en ambas suites locales finales |
| Seguimiento | SeguimientoPreventivo | C | Auditable append-only | HU-17 | ✅ |
| Rúbrica | Rubrica* / EvaluacionRiesgo | C/U | Auditable | HU-15/16 | ✅ |
| Solicitudes | SolicitudCorreccionExpediente | C/U | Auditable (whitelist) | cobertura E05 | ✅ |
| Auth | Especialista | login/logout/pwd | AuthAccessAuditor | HU21-E11 | ✅ |
| Auth | Especialista / Profesional | C/U/D | Auditable (secrets excluded) | cobertura E05 | ✅ |
| Inmutabilidad | audits | — | Eloquent + REVOKE + trigger | HU21-E04 | ✅ |
| Clasificación | config/auditoria.php | — | test falla si modelo sin clasificar | HU21-E05 | ✅ |

## Inmutabilidad BD
- `REVOKE UPDATE, DELETE, TRUNCATE` a `pandora_app`
- Trigger `trg_audits_immutable_row` / `trg_audits_immutable_truncate`
- Modelo `App\Models\Audit` lanza RuntimeException en update/delete/save existente

## Re-verificación transversal B.5.5

Las pruebas del dashboard y HU-21 pasaron dentro de las dos suites completas finales seriales: **362 passed / 1842 assertions** en cada corrida (869.22s y 692.86s). Los fallos heredados fueron corregidos sin relajar assertions, y el QA endurecido usa caché persistente y raíz read-only: no quedan escenarios omitidos. El chequeo global Pint pasa sus 306 archivos. El inventario incluye los puntos de lectura compartidos del dashboard; los requisitos del destino siguen pendientes del operador autorizado. Comandos, hashes de fuente, inspección de contenedores y resultados reales: `docs/evidencia/SPRINT_3_REPAIR_VERIFICATION.md`.

## Pendiente humano (Azure)
```sql
SELECT has_table_privilege('pandora_app','audits','UPDATE'),
       has_table_privilege('pandora_app','audits','DELETE'),
       has_table_privilege('pandora_app','audits','TRUNCATE');
-- esperado: f,f,f
SELECT tgname FROM pg_trigger WHERE tgrelid='audits'::regclass AND NOT tgisinternal;
```
