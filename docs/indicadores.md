# Catálogo de indicadores institucionales

`GET /indicadores` entrega conteos agregados por área y un total del alcance autorizado. La consulta no devuelve ni hidrata pacientes, expedientes, profesionales, nombres, carnets o texto clínico. No hay desglose por persona o profesional.

## Filtros

- `desde` y `hasta`: días calendario inclusivos en `America/El_Salvador`, convertidos a UTC para consultar; máximo 366 días inclusive. Predeterminado: últimos 30 días, incluyendo hoy.
- `area_id`: opcional. `area_coordinator` solo puede seleccionar sus áreas; `sysadmin` puede consultar todas o una.
- `indicadores[]`: uno o más slugs del catálogo. Todos se muestran inicialmente.

## Fórmulas

| Slug | Definición y fórmula | Fuente/campo temporal |
|---|---|---|
| `consultas_registradas` | `COUNT(consultas.id)` no eliminadas. | `consultas.fecha_consulta` |
| `pacientes_atendidos` | `COUNT(DISTINCT expedientes.paciente_id)` por área; el total institucional cuenta cada paciente una vez, aunque haya sido atendido en varias áreas. | Consultas unidas a expedientes no eliminados por `consultas.fecha_consulta` |
| `expedientes_abiertos` | Expedientes no eliminados cuyo estado actual sea `abierto` o `en_atencion`, abiertos durante el período. | `fecha_derivacion`; si es nula por legado, `created_at` |
| `expedientes_cerrados` | Expedientes no eliminados cuyo estado actual sea `cerrado`, cerrados durante el período. | `fecha_cierre` |
| `citas_programadas` | Citas no eliminadas con estado `programada`. | `citas.fecha_hora` |
| `citas_asistidas` | Citas no eliminadas con estado `asistida`. | `citas.fecha_hora` |
| `citas_no_asistidas` | Citas no eliminadas con estado `ausente`. | `citas.fecha_hora` |
| `citas_canceladas` | Citas no eliminadas con estado `cancelada`. | `citas.fecha_hora` |
| `citas_reprogramadas` | Citas no eliminadas con estado `reprogramada`. | `citas.fecha_hora` |
| `tasa_asistencia` | `asistidas / (asistidas + ausentes) × 100`, redondeada a 1 decimal. | Citas con `fecha_hora` dentro del período |
| `alertas_generadas` | Conteo de alertas desglosado por señal (`crisis_psicologica`, `ausencia_cita`, `tendencia_suicida`). | `alertas_preventivas.generada_en` |
| `seguimientos_registrados` | `COUNT(seguimientos_preventivos.id)`. | `seguimientos_preventivos.created_at` |

Para tasas se suprime la celda si el denominador, asistidas o ausentes es menor que el umbral; no se publican los conteos intermedios.

## Supresión estadística

`config('indicadores.min_celda')` vale **5**. Un conteo menor se muestra como `< 5`. Si una sola celda primaria permitiría reconstrucción por diferencia, se oculta además la menor celda visible complementaria. El total se suprime si es menor que cinco o si mostrarlo permitiría reconstruir una celda oculta. Una supresión complementaria se presenta como “Protegido”. En períodos sin datos se presentan ceros sin supresión.

En alertas, las celdas se desglosan como máximo por área y señal; no se muestran totales por señal que expongan una celda suprimida. El total general se protege si cualquier grupo subyacente requiere supresión.

## Acceso, rendimiento y auditoría

- La Policy limita al coordinador a sus áreas con perfiles profesionales activos y al `sysadmin` a agregados de todas las áreas. Los filtros de área ajena responden 403. El selector conserva la lista completa permitida incluso al filtrar una sola área.
- Cinco consultas agregadas de Query Builder procesan consultas/pacientes, expedientes, citas, alertas y seguimientos. Solo proyectan `area_id`, estado/señal, fechas y agregados; áreas autorizadas se resuelven por separado. No se hidratan modelos fuente ni se usa `withoutGlobalScope`.
- Los índices HU-18 son no únicos, parciales donde corresponde y cubren fechas/área; las citas conservan además su índice existente en `fecha_hora`.
- La lectura exitosa genera un evento `accessed` en `audits` con actor, acción, identificador de recurso agregado, filtros, fecha e IP. No guarda valores calculados ni PHI.
- La UI usa tablas accesibles y SVG inline; no agrega dependencia de gráficas.
