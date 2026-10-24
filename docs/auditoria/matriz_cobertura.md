# Matriz de cobertura de auditoría — HU-21

Fecha: 2026-10-24 · Commit base: `feat/hu-21-auditoria-inmutable`

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

## Pendiente humano (Azure)
```sql
SELECT has_table_privilege('pandora_app','audits','UPDATE'),
       has_table_privilege('pandora_app','audits','DELETE'),
       has_table_privilege('pandora_app','audits','TRUNCATE');
-- esperado: f,f,f
SELECT tgname FROM pg_trigger WHERE tgrelid='audits'::regclass AND NOT tgisinternal;
```
