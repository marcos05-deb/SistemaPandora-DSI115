# Guía para Marcos — pruebas, flujos, evidencia y cierre Sprint 2

**Para:** Marcos Gallardo (`gc23032@ues.edu.sv`)  
**Rama:** `correcciones-sprint-2`  
**Objetivo:** que prepares **un documento de evidencia y cierre** (y actualices Jira) usando lo que ya está implementado.  
**No es trabajo de código nuevo:** el backend de PAN-14 a PAN-21 ya cumple en pruebas automatizadas. Tu foco es **regresión manual, capturas, flujos de demo, Jira y (opcional) npm**.

---

## 1. Qué debes entregar tú

Un documento (puede vivir en `correcciones/`) con nombre sugerido:

`SPRINT_2_EVIDENCIA_MARCOS.md`

Debe incluir, como mínimo:

1. Fecha, rama, commit (`git rev-parse --short HEAD`).
2. Resultado de la suite automática (comando + resumen: passed / failed / skipped).
3. Tabla PAN-14…PAN-21 con: criterio, prueba automática, prueba manual, captura/enlace, estado.
4. Guion de demostración (paso a paso) para la presentación.
5. Checklist de Jira (historias y subtareas Backend / Frontend / QA).
6. Notas de RF-05 (`npm audit`) si lo abordas; si no, dejarlo explícito como pendiente opcional.

Plantilla mínima al final de este archivo.

---

## 2. Preparación del entorno

```bash
cd ~/Pandora/SistemaPandora-DSI115   # o la ruta de tu copia
git checkout correcciones-sprint-2
git pull --ff-only origin correcciones-sprint-2

docker compose up -d
docker compose exec app php artisan migrate --database=pgsql_admin --force
docker compose exec app php artisan test
docker compose exec app php artisan migrate:status --database=pgsql_admin
```

Registrar en tu documento:

- Commit corto.
- Nº de pruebas / aserciones / fallos / omitidas.
- Nº de migraciones en estado `Ran` (esperado ≥ 47 tras la FK de consistencia).

Build frontend (para demo):

```bash
docker compose exec app npm run build
# o en host, según cómo trabajes el front:
npm run build
```

---

## 3. Suite automática que debes citar (no reescribir)

Ejecuta y pega el resumen. Prioriza estos filtros si quieres evidencia por bloque:

| Bloque | Comando / rutas | Historias |
|---|---|---|
| Derivación | `tests/Feature/Expediente/DerivacionTest.php` | PAN-14 |
| Consulta | `ConsultaTest`, `ConsultaCryptoTest`, `PerfilProfesionalPorAreaTest` | PAN-15 |
| Historial | `ConsultarHistorialTest`, `AreaScopeLeakTest` | PAN-16 |
| Expediente | `CerrarExpedienteTest` | PAN-17 |
| Citas / agenda persona | `AsignarProximaCitaTest`, `AgendaUsuarioMultiareaTest`, `ExclusionAgendaDbTest`, `ConcurrentExclusionTest`, `ProfesionalUserConsistencyTest` | PAN-18 |
| Asistencia / alerta | `AsistenciaTest`, `AlertasPreventivasTest` | PAN-19 |
| Reprogramar / cancelar | `ReprogramarCancelarCitaTest` | PAN-20 |
| Agenda UI | `ConsultarCitasTest`, `CoordinadorMultiareaCitaTest` | PAN-21 |
| Flujo integral | `FlujoClinicoIntegralTest` | varias |

```bash
docker compose exec app php artisan test \
  tests/Feature/Expediente \
  tests/Feature/Consulta \
  tests/Feature/Historial \
  tests/Feature/Cita \
  tests/Feature/Citas \
  tests/Feature/Multiarea \
  tests/Feature/Flujo \
  tests/Feature/Security/AreaScopeLeakTest.php
```

---

## 4. Flujos manuales (lo que debes probar y documentar)

Usa usuarios seed (o los del entorno demo). Por cada flujo: **pasos → resultado esperado → captura**.

### Flujo A — Derivación (PAN-14 / HU-07)

1. Entrar como referente.
2. Derivar paciente a un área con motivo.
3. Verificar un solo expediente activo por paciente/área.
4. Intentar derivar de nuevo al mismo área → debe rechazar / no duplicar.

### Flujo B — Consulta (PAN-15 / HU-08)

1. Especialista del área del expediente.
2. Registrar consulta con fecha, notas, diagnóstico/observación y plan.
3. Verificar autor y que el expediente pase a atención.
4. (Multiárea) mismo usuario con dos perfiles: la consulta debe quedar con el perfil del área del expediente.

### Flujo C — Historial (PAN-16 / HU-09)

1. Abrir historial del paciente.
2. Comprobar orden cronológico, área y profesional.
3. Filtrar por período, área y tipo.
4. Confirmar que no aparecen datos de áreas no autorizadas.

### Flujo D — Actualizar / cerrar expediente (PAN-17 / HU-10)

1. Actualizar un campo clínico con motivo.
2. Cerrar con resultado final y confirmación.
3. Verificar modo consulta (sin escritura).
4. Revisar que auditoría conserve valores anteriores (si tienes acceso a auditoría en UI o DB).

### Flujo E — Asignar cita (PAN-18 / HU-11) — crítico multiárea

1. Desde consulta activa, agendar cita futura.
2. Misma persona, **otra área**, mismo horario → **debe fallar**.
3. Misma persona, horario contiguo (11:00 tras 10:00–11:00) → **debe pasar**.
4. Otra persona, mismo horario → **debe pasar**.

### Flujo F — Asistencia / ausencia (PAN-19 / HU-12)

1. Cita cuya hora ya pasó → marcar Asistió / Ausente.
2. Antes de la hora → rechazar.
3. Ausencia → verificar que alimenta estadísticas / alerta preventiva (especialista o coordinador; **no** referente).

### Flujo G — Reprogramar / cancelar (PAN-20 / HU-13)

1. Reprogramar cita futura con motivo + acuerdo paciente.
2. Confirmar que la anterior queda `reprogramada`, la nueva `programada`, y el horario viejo se libera.
3. Cancelar otra cita futura con motivo; verificar autor y `fecha_cancelacion`.
4. Reprogramar hacia horario ocupado en otra área de la misma persona → rechazar.

### Flujo H — Agenda (PAN-21 / HU-14)

1. Vista diaria y semanal: fecha, hora, paciente, estado.
2. Filtros: período, paciente, profesional, resultado.
3. Paginación / “ver resultados restantes en lista” si hay muchas citas.
4. Especialista multiárea: aparecen citas de **todos** sus perfiles.
5. Coordinador / otra área: no ve lo ajeno.

---

## 5. Guion corto de demostración (para la presentación)

Ordénalo en ~8–10 minutos:

1. Derivación → expediente único.
2. Consulta cifrada / plan de atención.
3. Historial con filtros.
4. Cita desde consulta.
5. **Choque multiárea** (mismo usuario, dos áreas, misma hora) → error.
6. Asistencia + ausencia → alerta preventiva.
7. Reprogramar / cancelar con trazabilidad.
8. Agenda diaria/semanal + paginación.
9. (Opcional) mostrar suite verde en terminal.

---

## 6. Jira (RF-04) — solo después de evidencia

Historias: **PAN-14 … PAN-21** (HU-07 … HU-14).

Por cada historia:

1. Adjuntar o enlazar: salida de `php artisan test` + capturas del flujo manual.
2. Mover subtareas Backend / Frontend / QA según el flujo real del equipo.
3. Marcar la historia como terminada **solo** si los 3 criterios de aceptación están demostrados.
4. No dejar PAN-18 en “listo” sin evidencia del rechazo multiárea y de la suite (incluye concurrencia).

Verificar una por una (evitar abrir todas a la vez si Jira falla).

---

## 7. npm audit (RF-05) — opcional, rama separada

No bloquea el MVP de producción (`npm audit --omit=dev` = 0).

Si lo haces:

1. Rama nueva, no mezclar con la demo.
2. `npm audit fix` **sin** `--force`.
3. Confirmar subidas (Vite ≥ 8.0.16, Axios ≥ 1.18.0, etc.).
4. `npm run build` + smoke de UI.
5. Documentar resultado en tu MD de evidencia.

---

## 8. Qué ya no te toca reimplementar

Ya está en la rama (Eduardo + pruebas previas):

- `profesional_user_id` y exclusión por persona.
- Índice único perfiles activos usuario/área.
- FK compuesta perfil/usuario en citas.
- Prueba concurrente real (`ConcurrentExclusionTest`).
- Selector de expediente, agenda multiperfil, trazabilidad de cancelación, paginación agenda.

Tu trabajo es **demostrar, documentar y cerrar gestión**.

---

## 9. Plantilla para tu documento `SPRINT_2_EVIDENCIA_MARCOS.md`

Copia y completa:

```markdown
# Sprint 2 — Evidencia de pruebas y cierre (Marcos)

- Fecha:
- Rama: correcciones-sprint-2
- Commit:
- Entorno: Docker + PostgreSQL

## Suite automática

Comando:
Resultado: X passed, Y assertions, Z failed, W skipped

## Matriz por historia

| Jira | HU | CA | Auto | Manual | Evidencia | Estado |
|---|---|---|---|---|---|---|
| PAN-14 | HU-07 | 1..3 | | | | |
| PAN-15 | HU-08 | 1..3 | | | | |
| PAN-16 | HU-09 | 1..3 | | | | |
| PAN-17 | HU-10 | 1..3 | | | | |
| PAN-18 | HU-11 | 1..3 | | | | |
| PAN-19 | HU-12 | 1..3 | | | | |
| PAN-20 | HU-13 | 1..3 | | | | |
| PAN-21 | HU-14 | 1..3 | | | | |

## Flujos manuales ejecutados

### Flujo A …
Pasos / Resultado / Captura:

(repetir B–H)

## Guion de demo

1.
2.

## Jira

| Incidencia | Subtareas movidas | Evidencia adjuntada | Estado final |
|---|---|---|---|
| PAN-14 | | | |
| … | | | |

## RF-05 npm (si aplica)

- `npm audit --omit=dev`:
- `npm audit`:
- Acciones tomadas:

## Conclusión

Sprint 2 listo para presentación: sí / no  
Pendientes abiertos:
```

---

## 10. Definición de terminado (para ti)

- [ ] Suite completa verde documentada.
- [ ] Flujos A–H probados con evidencia.
- [ ] Documento `SPRINT_2_EVIDENCIA_MARCOS.md` (o equivalente) en el repo o adjunto al equipo.
- [ ] Jira alineado con la realidad del código.
- [ ] Guion de demo listo.
- [ ] RF-05 resuelto o explícitamente aplazado.
