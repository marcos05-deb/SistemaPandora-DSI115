# Inventario de mensajes del sistema

Generado el 2026-11-03 sobre la rama `feat/ui`. Cubre: `messages()`/validaciones de FormRequests y controladores, `ValidationException::withMessages`, `abort()`, excepciones y `RuntimeException` visibles, flashes (`->with(...)`), notificaciones, `lang/es`, mensajes de la UI Vue (errores, vacíos, confirmaciones, banners), PDF de reportes y mapeo de códigos HTTP en `Error.vue`.

**Convenciones fijadas:** tuteo ("tú") en todo el sistema; "correctamente" en confirmaciones de éxito; roles oficiales (Administrador del sistema, Coordinador de área, Especialista, Referente psicosocial); "Carnet" = identificador institucional, "código anónimo" = `Paciente.codigo`; límites desde `config/limites.php` y umbral de privacidad desde `PrivacyThreshold::minCell()`.

**Alcance:** las etiquetas de formulario, encabezados y botones no se listan; solo mensajes que informan un resultado, error, estado vacío o confirmación. Las excepciones de bajo nivel (`Casts`, cifrado) solo llegan al usuario como 500/401 genérico.

## Campos con nombre en español (`lang/es/validation.php` → `attributes`)

Se agregaron nombres para `code`, `desde`, `hasta`, `fecha_*`, `motivo_*`, `plan_atencion`, `valoracion`, `indicadores`, `agrupar_por`, `vigente_desde`, etc., para evitar salidas del tipo "The field x" / nombres técnicos en mensajes por defecto.

## Auth (31)

| Mensaje | Tipo | Origen | Cuándo aparece | Hallazgo |
|---|---|---|---|---|
| Cuenta bloqueada por seguridad tras 3 intentos fallidos. Intenta de nuevo en {$remaining} minuto(s). | error | `app/Http/Controllers/Auth/LoginController.php:66` | Al ejecutar la acción en el controlador | Alineado con config de bloqueo (3 intentos / 15 min) |
| El correo o la contraseña son incorrectos. | error | `app/Http/Controllers/Auth/LoginController.php:72` | Al ejecutar la acción en el controlador | Unificado: antes "credenciales proporcionadas" (3 variantes + lang/auth.php) |
| El correo o la contraseña son incorrectos. | error | `app/Http/Controllers/Auth/LoginController.php:78` | Al ejecutar la acción en el controlador | Unificado: antes "credenciales proporcionadas" (3 variantes + lang/auth.php) |
| Cuenta bloqueada por seguridad tras 3 intentos fallidos. Intenta de nuevo en 15 minutos. | error | `app/Http/Controllers/Auth/LoginController.php:96` | Al ejecutar la acción en el controlador | Alineado con config de bloqueo (3 intentos / 15 min) |
| El correo o la contraseña son incorrectos. | error | `app/Http/Controllers/Auth/LoginController.php:102` | Al ejecutar la acción en el controlador | Unificado: antes "credenciales proporcionadas" (3 variantes + lang/auth.php) |
| La contraseña es obligatoria. | validación | `app/Http/Controllers/Auth/PasswordSetupController.php:32` | Al ejecutar la acción en el controlador | OK |
| Las contraseñas no coinciden. | error | `app/Http/Controllers/Auth/PasswordSetupController.php:33` | Al ejecutar la acción en el controlador | OK |
| La contraseña debe tener al menos :min caracteres. | validación | `app/Http/Controllers/Auth/PasswordSetupController.php:34` | Al ejecutar la acción en el controlador | OK |
| La contraseña debe contener al menos una letra mayúscula y una minúscula. | error | `app/Http/Controllers/Auth/PasswordSetupController.php:35` | Al ejecutar la acción en el controlador | OK |
| La contraseña debe contener al menos un número. | error | `app/Http/Controllers/Auth/PasswordSetupController.php:36` | Al ejecutar la acción en el controlador | OK |
| La contraseña debe contener al menos un símbolo. | error | `app/Http/Controllers/Auth/PasswordSetupController.php:37` | Al ejecutar la acción en el controlador | OK |
| Contraseña configurada correctamente. | flash | `app/Http/Controllers/Auth/PasswordSetupController.php:65` | Tras completar la acción (banner/toast) | Unificado "correctamente" (antes mezcla con "exitosamente") |
| Contraseña configurada correctamente. | flash | `app/Http/Controllers/Auth/PasswordSetupController.php:70` | Tras completar la acción (banner/toast) | Unificado "correctamente" (antes mezcla con "exitosamente") |
| Tu sesión expiró. Inicia sesión nuevamente. | error | `app/Http/Middleware/ValidateSymmetricKey.php:24` | Al acceder a una ruta sin permiso o sin sesión | Tuteo unificado (antes usted: "Su sesión... inicie") |
| Tu sesión expiró. Inicia sesión nuevamente. | error | `app/Http/Middleware/ValidateSymmetricKey.php:29` | Al acceder a una ruta sin permiso o sin sesión | Tuteo unificado (antes usted: "Su sesión... inicie") |
| El correo electrónico es obligatorio. | validación | `app/Http/Requests/Auth/LoginRequest.php:31` | Al enviar el formulario con datos inválidos | OK |
| Ingresa un correo electrónico válido. | validación | `app/Http/Requests/Auth/LoginRequest.php:32` | Al enviar el formulario con datos inválidos | OK |
| La contraseña es obligatoria. | validación | `app/Http/Requests/Auth/LoginRequest.php:33` | Al enviar el formulario con datos inválidos | OK |
| Los registros de auditoría son inmutables. | error | `app/Providers/AppServiceProvider.php:73` | Al ejecutar la acción en el controlador | OK |
| Los registros de auditoría son inmutables. | error | `app/Providers/AppServiceProvider.php:77` | Al ejecutar la acción en el controlador | OK |
| Demasiados intentos de inicio de sesión desde este equipo. Intenta de nuevo en {$minutes} minuto(s). | error | `app/Providers/AppServiceProvider.php:90` | Al ejecutar la acción en el controlador | OK |
| El correo o la contraseña son incorrectos. | error | `lang/es/auth.php:4` | Al ejecutar la acción en el controlador | Unificado: antes "credenciales proporcionadas" (3 variantes + lang/auth.php) |
| La contraseña es incorrecta. | error | `lang/es/auth.php:5` | Al ejecutar la acción en el controlador | OK |
| Demasiados intentos de acceso. Intenta de nuevo en :seconds segundos. | error | `lang/es/auth.php:6` | Al ejecutar la acción en el controlador | OK |
| Tu contraseña ha sido restablecida. | error | `lang/es/passwords.php:4` | Al ejecutar la acción en el controlador | OK |
| Te hemos enviado el enlace para restablecer tu contraseña. | error | `lang/es/passwords.php:5` | Al ejecutar la acción en el controlador | OK |
| Espera antes de intentarlo de nuevo. | error | `lang/es/passwords.php:6` | Al ejecutar la acción en el controlador | OK |
| El token de restablecimiento no es válido. | error | `lang/es/passwords.php:7` | Al ejecutar la acción en el controlador | OK |
| No encontramos un usuario con ese correo electrónico. | error | `lang/es/passwords.php:8` | Al ejecutar la acción en el controlador | OK |
| Tu sesión expiró. Inicia sesión nuevamente. | error | `bootstrap/app.php:45` | Al ejecutar la acción en el controlador | Tuteo unificado (antes usted: "Su sesión... inicie") |
| Tu sesión expiró durante la operación. Inicia sesión nuevamente. | error | `bootstrap/app.php:50` | Al ejecutar la acción en el controlador | Tuteo unificado (antes usted: "Su sesión... inicie") |

## Pacientes (56)

| Mensaje | Tipo | Origen | Cuándo aparece | Hallazgo |
|---|---|---|---|---|
| La fecha inicial no puede ser futura. | validación | `app/Http/Controllers/Admin/PacienteController.php:28` | Al ejecutar la acción en el controlador | OK |
| La fecha final no puede ser futura. | validación | `app/Http/Controllers/Admin/PacienteController.php:29` | Al ejecutar la acción en el controlador | OK |
| La fecha final debe ser igual o posterior a la fecha inicial. | validación | `app/Http/Controllers/Admin/PacienteController.php:30` | Al ejecutar la acción en el controlador | Redacción unificada para fecha final |
| Este registro no es un aviso sensible. | flash | `app/Http/Controllers/Admin/PacienteController.php:140` | Tras completar la acción (banner/toast) | OK |
| El aviso ya estaba marcado como revisado. | flash | `app/Http/Controllers/Admin/PacienteController.php:146` | Tras completar la acción (banner/toast) | OK |
| Aviso marcado como revisado. El registro de auditoría no se modificó. | flash | `app/Http/Controllers/Admin/PacienteController.php:153` | Tras completar la acción (banner/toast) | OK |
| Solicitud aprobada. La persona solicitante puede hacer una sola actualización en los campos autorizados. | flash | `app/Http/Controllers/Admin/PacienteController.php:165` | Tras completar la acción (banner/toast) | OK |
| Solicitud rechazada. El expediente permanece bloqueado para nuevas correcciones. | flash | `app/Http/Controllers/Admin/PacienteController.php:177` | Tras completar la acción (banner/toast) | OK |
| No se encontró un expediente con ese carnet o no tienes permiso para verlo. Verifica el carnet e inténtalo de nuevo. | error | `app/Http/Controllers/PacienteController.php:71` | Al ejecutar la acción en el controlador | OK |
| Paciente registrado correctamente. | flash | `app/Http/Controllers/PacienteController.php:157` | Tras completar la acción (banner/toast) | Unificado "correctamente" (antes mezcla con "exitosamente") |
| Datos del paciente corregidos correctamente. La operación quedó registrada en auditoría. | flash | `app/Http/Controllers/PacienteController.php:385` | Tras completar la acción (banner/toast) | Unificado "correctamente" (antes mezcla con "exitosamente") |
| El código anónimo no tiene un formato válido. Revisa que esté completo. | validación | `app/Http/Controllers/SecureSearchController.php:32` | Al ejecutar la acción en el controlador | Terminología: "código anónimo" (antes "código del expediente"/UUID) |
| No tienes permiso para buscar expedientes. | error | `app/Http/Controllers/SecureSearchController.php:49` | Al ejecutar la acción en el controlador | OK |
| No se encontró un expediente con ese código anónimo. Verifica el código e inténtalo de nuevo. | error | `app/Http/Controllers/SecureSearchController.php:57` | Al ejecutar la acción en el controlador | Terminología: "código anónimo" (antes "código del expediente"/UUID) |
| Solicitud enviada. Administración debe aprobarla antes de que puedas volver a corregir estos datos. | flash | `app/Http/Controllers/SolicitudCorreccionController.php:28` | Tras completar la acción (banner/toast) | OK |
| Solicitud enviada. Administración debe aprobarla antes de que puedas volver a actualizar este expediente. | flash | `app/Http/Controllers/SolicitudCorreccionController.php:45` | Tras completar la acción (banner/toast) | OK |
| El carnet debe contener exactamente 2 letras seguidas de 5 números. | validación | `app/Http/Requests/StorePacienteRequest.php:74` | Al enviar el formulario con datos inválidos | OK |
| Este carnet ya está registrado. Verifica el carnet o busca al paciente existente. | validación | `app/Http/Requests/StorePacienteRequest.php:75` | Al enviar el formulario con datos inválidos | OK |
| La fecha de nacimiento no puede estar en el futuro. | validación | `app/Http/Requests/StorePacienteRequest.php:76` | Al enviar el formulario con datos inválidos | OK |
| La fecha de nacimiento no puede ser anterior a 1900. | validación | `app/Http/Requests/StorePacienteRequest.php:77` | Al enviar el formulario con datos inválidos | OK |
| La fecha de la primera consulta no puede estar en el futuro. | validación | `app/Http/Requests/StorePacienteRequest.php:78` | Al enviar el formulario con datos inválidos | OK |
| La primera consulta no puede ser anterior a la fecha de nacimiento. | validación | `app/Http/Requests/StorePacienteRequest.php:79` | Al enviar el formulario con datos inválidos | OK |
| El teléfono del padre debe tener exactamente 8 dígitos. | validación | `app/Http/Requests/StorePacienteRequest.php:80` | Al enviar el formulario con datos inválidos | OK |
| El teléfono de la madre debe tener exactamente 8 dígitos. | validación | `app/Http/Requests/StorePacienteRequest.php:81` | Al enviar el formulario con datos inválidos | OK |
| El teléfono del responsable debe tener exactamente 8 dígitos numéricos. | validación | `app/Http/Requests/StorePacienteRequest.php:82` | Al enviar el formulario con datos inválidos | OK |
| Indica al menos un campo. | validación | `app/Http/Requests/StoreSolicitudCorreccionRequest.php:39` | Al enviar el formulario con datos inválidos | Tuteo unificado (antes "Debe/Solicite/Corrija...") |
| Indica al menos un campo. | validación | `app/Http/Requests/StoreSolicitudCorreccionRequest.php:40` | Al enviar el formulario con datos inválidos | Tuteo unificado (antes "Debe/Solicite/Corrija...") |
| El motivo es obligatorio. | validación | `app/Http/Requests/StoreSolicitudCorreccionRequest.php:41` | Al enviar el formulario con datos inválidos | OK |
| El motivo debe tener al menos 10 caracteres. | validación | `app/Http/Requests/StoreSolicitudCorreccionRequest.php:42` | Al enviar el formulario con datos inválidos | OK |
| El carnet debe contener exactamente 2 letras seguidas de 5 números (formato AA00000). | validación | `app/Http/Requests/UpdatePacienteRequest.php:78` | Al enviar el formulario con datos inválidos | OK |
| Este carnet ya está registrado. Verifica el carnet o busca al paciente existente. | validación | `app/Http/Requests/UpdatePacienteRequest.php:79` | Al enviar el formulario con datos inválidos | OK |
| El motivo de la corrección es obligatorio. | validación | `app/Http/Requests/UpdatePacienteRequest.php:80` | Al enviar el formulario con datos inválidos | OK |
| El motivo de la corrección debe tener al menos 10 caracteres. | validación | `app/Http/Requests/UpdatePacienteRequest.php:81` | Al enviar el formulario con datos inválidos | OK |
| El motivo de la corrección no puede superar 500 caracteres. | validación | `app/Http/Requests/UpdatePacienteRequest.php:82` | Al enviar el formulario con datos inválidos | OK |
| La fecha de nacimiento no puede estar en el futuro ni ser posterior a la primera consulta. | validación | `app/Http/Requests/UpdatePacienteRequest.php:83` | Al enviar el formulario con datos inválidos | OK |
| La fecha de nacimiento no puede ser anterior a 1900. | validación | `app/Http/Requests/UpdatePacienteRequest.php:84` | Al enviar el formulario con datos inválidos | OK |
| El teléfono del padre debe tener exactamente 8 dígitos. | validación | `app/Http/Requests/UpdatePacienteRequest.php:85` | Al enviar el formulario con datos inválidos | OK |
| El teléfono de la madre debe tener exactamente 8 dígitos. | validación | `app/Http/Requests/UpdatePacienteRequest.php:86` | Al enviar el formulario con datos inválidos | OK |
| El teléfono del responsable debe tener exactamente 8 dígitos numéricos. | validación | `app/Http/Requests/UpdatePacienteRequest.php:87` | Al enviar el formulario con datos inválidos | OK |
| Solo se pueden aprobar solicitudes pendientes. | error | `app/Models/SolicitudCorreccionExpediente.php:133` | Al violar una regla de negocio al guardar | OK |
| Solo se pueden rechazar solicitudes pendientes. | error | `app/Models/SolicitudCorreccionExpediente.php:146` | Al violar una regla de negocio al guardar | OK |
| Solo se pueden consumir solicitudes aprobadas. | error | `app/Models/SolicitudCorreccionExpediente.php:159` | Al violar una regla de negocio al guardar | OK |
| Este paciente ya fue corregido una vez. Solicita permiso al Administrador del sistema para una nueva corrección. | error | `app/Services/PacienteDatosCorreccionService.php:67` | Al violar una regla de negocio al guardar | Nombre de rol oficial (antes "Sysadmin"/"administrador") |
| No se detectaron cambios en los datos. Corrige al menos un campo o cancela la operación. | error | `app/Services/PacienteDatosCorreccionService.php:81` | Al violar una regla de negocio al guardar | Tuteo unificado (antes "Debe/Solicite/Corrija...") |
| El código anónimo del paciente no debe cambiar durante la corrección. | error | `app/Services/PacienteDatosCorreccionService.php:155` | Al violar una regla de negocio al guardar | Terminología: "código anónimo" (antes "código del expediente"/UUID) |
| Solo puedes modificar los campos autorizados por el Administrador del sistema:  | error | `app/Services/PacienteDatosCorreccionService.php:196` | Al violar una regla de negocio al guardar | Nombre de rol oficial (antes "Sysadmin"/"administrador") |
| Aún no has usado la corrección libre. No necesitas solicitar permiso. | error | `app/Services/SolicitudCorreccionExpedienteService.php:31` | Al violar una regla de negocio al guardar | Tuteo unificado (antes "Debe/Solicite/Corrija...") |
| Ya existe un permiso aprobado vigente. Úsalo antes de solicitar otro. | error | `app/Services/SolicitudCorreccionExpedienteService.php:37` | Al violar una regla de negocio al guardar | Tuteo unificado (antes "Debe/Solicite/Corrija...") |
| Ya hay una solicitud pendiente de autorización para este paciente. | error | `app/Services/SolicitudCorreccionExpedienteService.php:43` | Al violar una regla de negocio al guardar | OK |
| Aún no has usado la actualización clínica libre. No necesitas solicitar permiso. | error | `app/Services/SolicitudCorreccionExpedienteService.php:75` | Al violar una regla de negocio al guardar | Tuteo unificado (antes "Debe/Solicite/Corrija...") |
| Ya existe un permiso aprobado vigente. Úsalo antes de solicitar otro. | error | `app/Services/SolicitudCorreccionExpedienteService.php:81` | Al violar una regla de negocio al guardar | Tuteo unificado (antes "Debe/Solicite/Corrija...") |
| Ya hay una solicitud pendiente de autorización para este expediente. | error | `app/Services/SolicitudCorreccionExpedienteService.php:87` | Al violar una regla de negocio al guardar | OK |
| Indica al menos un campo a corregir. | error | `app/Services/SolicitudCorreccionExpedienteService.php:291` | Al violar una regla de negocio al guardar | Tuteo unificado (antes "Debe/Solicite/Corrija...") |
| Campos no permitidos:  | error | `app/Services/SolicitudCorreccionExpedienteService.php:298` | Al violar una regla de negocio al guardar | OK |
| No hay solicitudes de permiso. | vacío | `resources/js/Pages/Admin/Pacientes/Index.vue:194` | Cuando no hay datos para mostrar | OK |
| No hay correcciones registradas. | vacío | `resources/js/Pages/Admin/Pacientes/Index.vue:241` | Cuando no hay datos para mostrar | OK |

## Expediente / Consultas (49)

| Mensaje | Tipo | Origen | Cuándo aparece | Hallazgo |
|---|---|---|---|---|
| Consulta registrada correctamente. | flash | `app/Http/Controllers/ConsultaController.php:73` | Tras completar la acción (banner/toast) | Unificado "correctamente" (antes mezcla con "exitosamente") |
| Paciente derivado correctamente. | flash | `app/Http/Controllers/DerivacionController.php:38` | Tras completar la acción (banner/toast) | Unificado "correctamente" (antes mezcla con "exitosamente") |
| Paciente derivado correctamente. | flash | `app/Http/Controllers/DerivacionController.php:39` | Tras completar la acción (banner/toast) | Unificado "correctamente" (antes mezcla con "exitosamente") |
| Expediente actualizado correctamente. | flash | `app/Http/Controllers/ExpedienteController.php:32` | Tras completar la acción (banner/toast) | Unificado "correctamente" (antes mezcla con "exitosamente") |
| Cancelada automáticamente por cierre del expediente clínico. | error | `app/Http/Controllers/ExpedienteController.php:67` | Al ejecutar la acción en el controlador | OK |
| Expediente cerrado. Citas futuras canceladas: {$citasCanceladas}. | error | `app/Http/Controllers/ExpedienteController.php:78` | Al ejecutar la acción en el controlador | OK |
| Expediente cerrado. | error | `app/Http/Controllers/ExpedienteController.php:79` | Al ejecutar la acción en el controlador | OK |
| El motivo de la consulta es obligatorio. | validación | `app/Http/Requests/ConsultaStoreRequest.php:81` | Al enviar el formulario con datos inválidos | OK |
| La técnica utilizada es obligatoria. | validación | `app/Http/Requests/ConsultaStoreRequest.php:82` | Al enviar el formulario con datos inválidos | OK |
| El plan de atención es obligatorio. | validación | `app/Http/Requests/ConsultaStoreRequest.php:83` | Al enviar el formulario con datos inválidos | OK |
| El plan de atención debe tener al menos :min caracteres. | validación | `app/Http/Requests/ConsultaStoreRequest.php:84` | Al enviar el formulario con datos inválidos | OK |
| El plan de atención no puede superar :max caracteres. | validación | `app/Http/Requests/ConsultaStoreRequest.php:85` | Al enviar el formulario con datos inválidos | OK |
| La fecha de la consulta es obligatoria. | validación | `app/Http/Requests/ConsultaStoreRequest.php:86` | Al enviar el formulario con datos inválidos | OK |
| La fecha de la consulta no puede ser futura. | validación | `app/Http/Requests/ConsultaStoreRequest.php:87` | Al enviar el formulario con datos inválidos | OK |
| La fecha de la consulta no puede tener más de  | validación | `app/Http/Requests/ConsultaStoreRequest.php:88` | Al enviar el formulario con datos inválidos | OK |
| La evaluación inicial es obligatoria en la primera consulta. | validación | `app/Http/Requests/ConsultaStoreRequest.php:89` | Al enviar el formulario con datos inválidos | OK |
| Indica si se registró una crisis psicológica. | validación | `app/Http/Requests/ConsultaStoreRequest.php:90` | Al enviar el formulario con datos inválidos | Tuteo unificado (antes "Debe/Solicite/Corrija...") |
| Registra los indicadores de tendencia suicida o indica que no hay ninguno. | validación | `app/Http/Requests/ConsultaStoreRequest.php:91` | Al enviar el formulario con datos inválidos | OK |
| Completa este campo de la evaluación inicial. | validación | `app/Http/Requests/ConsultaStoreRequest.php:95` | Al enviar el formulario con datos inválidos | OK |
| Completa el diagnóstico o las notas clínicas; al menos uno es obligatorio. | validación | `app/Http/Requests/ConsultaStoreRequest.php:110` | Al enviar el formulario con datos inválidos | OK |
| Completa el diagnóstico o las notas clínicas; al menos uno es obligatorio. | validación | `app/Http/Requests/ConsultaStoreRequest.php:114` | Al enviar el formulario con datos inválidos | OK |
| El motivo de derivación es obligatorio. | validación | `app/Http/Requests/DerivacionStoreRequest.php:53` | Al enviar el formulario con datos inválidos | OK |
| El motivo de derivación debe tener al menos :min caracteres. | validación | `app/Http/Requests/DerivacionStoreRequest.php:54` | Al enviar el formulario con datos inválidos | OK |
| El motivo de derivación no puede superar :max caracteres. | validación | `app/Http/Requests/DerivacionStoreRequest.php:55` | Al enviar el formulario con datos inválidos | OK |
| El resultado final es obligatorio para cerrar el expediente. | validación | `app/Http/Requests/ExpedienteCloseRequest.php:38` | Al enviar el formulario con datos inválidos | OK |
| El resultado final debe tener al menos :min caracteres. | validación | `app/Http/Requests/ExpedienteCloseRequest.php:39` | Al enviar el formulario con datos inválidos | OK |
| El motivo de cierre es obligatorio. | validación | `app/Http/Requests/ExpedienteCloseRequest.php:40` | Al enviar el formulario con datos inválidos | OK |
| Confirma que el cierre es irreversible. | validación | `app/Http/Requests/ExpedienteCloseRequest.php:41` | Al enviar el formulario con datos inválidos | Tuteo unificado (antes "Debe/Solicite/Corrija...") |
| El expediente ya se encuentra cerrado. | validación | `app/Http/Requests/ExpedienteCloseRequest.php:50` | Al enviar el formulario con datos inválidos | OK |
| Indica el motivo del cambio. | validación | `app/Http/Requests/ExpedienteUpdateRequest.php:41` | Al enviar el formulario con datos inválidos | Tuteo unificado (antes "Debe/Solicite/Corrija...") |
| El motivo del cambio debe tener al menos :min caracteres. | validación | `app/Http/Requests/ExpedienteUpdateRequest.php:42` | Al enviar el formulario con datos inválidos | OK |
| No se puede actualizar un expediente cerrado. | validación | `app/Http/Requests/ExpedienteUpdateRequest.php:55` | Al enviar el formulario con datos inválidos | OK |
| Modifica al menos un campo permitido del expediente. | validación | `app/Http/Requests/ExpedienteUpdateRequest.php:71` | Al enviar el formulario con datos inválidos | Tuteo unificado (antes "Debe/Solicite/Corrija...") |
| La fecha inicial no puede ser futura. | validación | `app/Http/Requests/HistorialFilterRequest.php:42` | Al enviar el formulario con datos inválidos | OK |
| La fecha final no puede ser futura. | validación | `app/Http/Requests/HistorialFilterRequest.php:43` | Al enviar el formulario con datos inválidos | OK |
| La fecha final debe ser igual o posterior a la fecha inicial. | validación | `app/Http/Requests/HistorialFilterRequest.php:44` | Al enviar el formulario con datos inválidos | Redacción unificada para fecha final |
| El tipo de atención seleccionado no es válido. | validación | `app/Http/Requests/HistorialFilterRequest.php:45` | Al enviar el formulario con datos inválidos | OK |
| El rango máximo del historial es de  | validación | `app/Http/Requests/HistorialFilterRequest.php:62` | Al enviar el formulario con datos inválidos | OK |
| No tiene autorización para filtrar por el área indicada. | validación | `app/Http/Requests/HistorialFilterRequest.php:82` | Al enviar el formulario con datos inválidos | OK |
| El paciente ya tiene un expediente activo en esta área. | flash | `app/Models/Expediente.php:57` | Al violar una regla de negocio al guardar | OK |
| No se puede actualizar un expediente cerrado. | error | `app/Services/ExpedienteActualizacionService.php:35` | Al violar una regla de negocio al guardar | OK |
| Este expediente ya fue actualizado una vez. Solicita permiso al Administrador del sistema para una nueva actualización. | error | `app/Services/ExpedienteActualizacionService.php:46` | Al violar una regla de negocio al guardar | Nombre de rol oficial (antes "Sysadmin"/"administrador") |
| Modifica al menos un campo permitido del expediente. | error | `app/Services/ExpedienteActualizacionService.php:60` | Al violar una regla de negocio al guardar | Tuteo unificado (antes "Debe/Solicite/Corrija...") |
| Solo puedes modificar los campos autorizados:  | error | `app/Services/ExpedienteActualizacionService.php:69` | Al violar una regla de negocio al guardar | Tuteo unificado (antes "Debe/Solicite/Corrija...") |
| No se pudo guardar. Completa los campos obligatorios marcados. | error | `resources/js/Pages/Consultas/Create.vue:248` | Fallo de red/permiso/validación en el cliente | OK |
| No se pudo guardar la consulta. Revisa los campos señalados: | error | `resources/js/Pages/Consultas/Create.vue:258` | Fallo de red/permiso/validación en el cliente | OK |
| No se pudo guardar la consulta. Revisa los campos señalados: | error | `resources/js/Pages/Consultas/Create.vue:352` | Fallo de red/permiso/validación en el cliente | OK |
| Indica el plan de atención acordado en esta consulta (mínimo 10 caracteres) | error | `resources/js/Pages/Consultas/Create.vue:650` | Fallo de red/permiso/validación en el cliente | Tuteo unificado (antes "Debe/Solicite/Corrija...") |
| Sin resultados para los filtros | vacío | `resources/js/Pages/Historial/Show.vue:237` | Cuando no hay datos para mostrar | OK |

## Citas (42)

| Mensaje | Tipo | Origen | Cuándo aparece | Hallazgo |
|---|---|---|---|---|
| El horario seleccionado ya no está disponible o existe un conflicto en la agenda del especialista. | flash | `app/Http/Controllers/CitaController.php:30` | Tras completar la acción (banner/toast) | OK |
| El horario seleccionado se solapa con otra cita programada del especialista. | error | `app/Http/Controllers/CitaController.php:295` | Al ejecutar la acción en el controlador | OK |
| Cita agendada correctamente. | flash | `app/Http/Controllers/CitaController.php:311` | Tras completar la acción (banner/toast) | Unificado "correctamente" (antes mezcla con "exitosamente") |
| Asistencia registrada: {$etiqueta}. | flash | `app/Http/Controllers/CitaController.php:344` | Tras completar la acción (banner/toast) | OK |
| Solo se pueden reprogramar citas que estén en estado programada. | error | `app/Http/Controllers/CitaController.php:388` | Al ejecutar la acción en el controlador | OK |
| Solo se pueden reprogramar citas futuras cuya hora aún no haya llegado. | error | `app/Http/Controllers/CitaController.php:394` | Al ejecutar la acción en el controlador | OK |
| El horario seleccionado se solapa con otra cita programada del especialista. | error | `app/Http/Controllers/CitaController.php:406` | Al ejecutar la acción en el controlador | OK |
| Cita reprogramada correctamente. | flash | `app/Http/Controllers/CitaController.php:429` | Tras completar la acción (banner/toast) | Unificado "correctamente" (antes mezcla con "exitosamente") |
| Solo se pueden cancelar citas que estén en estado programada. | error | `app/Http/Controllers/CitaController.php:462` | Al ejecutar la acción en el controlador | OK |
| Solo se pueden cancelar citas futuras cuya hora aún no haya llegado. | error | `app/Http/Controllers/CitaController.php:468` | Al ejecutar la acción en el controlador | OK |
| Cita cancelada correctamente. | flash | `app/Http/Controllers/CitaController.php:479` | Tras completar la acción (banner/toast) | Unificado "correctamente" (antes mezcla con "exitosamente") |
| El resultado debe ser Asistió o Ausente. | validación | `app/Http/Requests/CitaAsistenciaRequest.php:39` | Al enviar el formulario con datos inválidos | OK |
| Solo se puede registrar asistencia de citas programadas. | validación | `app/Http/Requests/CitaAsistenciaRequest.php:56` | Al enviar el formulario con datos inválidos | OK |
| No se puede registrar asistencia antes de la hora programada de la cita. | validación | `app/Http/Requests/CitaAsistenciaRequest.php:66` | Al enviar el formulario con datos inválidos | OK |
| El motivo de cancelación es obligatorio. | validación | `app/Http/Requests/CitaCancelarRequest.php:36` | Al enviar el formulario con datos inválidos | OK |
| El motivo de cancelación debe tener al menos :min caracteres. | validación | `app/Http/Requests/CitaCancelarRequest.php:37` | Al enviar el formulario con datos inválidos | OK |
| Solo se pueden cancelar citas que estén en estado programada. | validación | `app/Http/Requests/CitaCancelarRequest.php:54` | Al enviar el formulario con datos inválidos | OK |
| Solo se pueden cancelar citas futuras cuya hora aún no haya llegado. | validación | `app/Http/Requests/CitaCancelarRequest.php:63` | Al enviar el formulario con datos inválidos | OK |
| La nueva fecha de la cita debe ser futura. | validación | `app/Http/Requests/CitaReprogramarRequest.php:38` | Al enviar el formulario con datos inválidos | OK |
| La cita no puede agendarse a más de  | validación | `app/Http/Requests/CitaReprogramarRequest.php:39` | Al enviar el formulario con datos inválidos | OK |
| El motivo de reprogramación es obligatorio. | validación | `app/Http/Requests/CitaReprogramarRequest.php:40` | Al enviar el formulario con datos inválidos | OK |
| El motivo de reprogramación debe tener al menos :min caracteres. | validación | `app/Http/Requests/CitaReprogramarRequest.php:41` | Al enviar el formulario con datos inválidos | OK |
| Confirma que la nueva fecha fue acordada con el paciente. | validación | `app/Http/Requests/CitaReprogramarRequest.php:42` | Al enviar el formulario con datos inválidos | Tuteo unificado (antes "Debe/Solicite/Corrija...") |
| Solo se pueden reprogramar citas que estén en estado programada. | validación | `app/Http/Requests/CitaReprogramarRequest.php:59` | Al enviar el formulario con datos inválidos | OK |
| Solo se pueden reprogramar citas futuras cuya hora aún no haya llegado. | validación | `app/Http/Requests/CitaReprogramarRequest.php:68` | Al enviar el formulario con datos inválidos | OK |
| Agenda la cita desde una consulta activa. | validación | `app/Http/Requests/CitaStoreRequest.php:46` | Al enviar el formulario con datos inválidos | Tuteo unificado (antes "Debe/Solicite/Corrija...") |
| La consulta de origen no es válida. | validación | `app/Http/Requests/CitaStoreRequest.php:47` | Al enviar el formulario con datos inválidos | OK |
| La fecha de la cita debe ser futura. | validación | `app/Http/Requests/CitaStoreRequest.php:48` | Al enviar el formulario con datos inválidos | OK |
| La cita no puede agendarse a más de  | validación | `app/Http/Requests/CitaStoreRequest.php:49` | Al enviar el formulario con datos inválidos | OK |
| Confirma que la fecha y hora fueron acordadas con el paciente. | validación | `app/Http/Requests/CitaStoreRequest.php:50` | Al enviar el formulario con datos inválidos | Tuteo unificado (antes "Debe/Solicite/Corrija...") |
| No se pueden agendar citas en un expediente cerrado. | validación | `app/Http/Requests/CitaStoreRequest.php:65` | Al enviar el formulario con datos inválidos | OK |
| La consulta de origen no pertenece a este expediente. | validación | `app/Http/Requests/CitaStoreRequest.php:84` | Al enviar el formulario con datos inválidos | OK |
| Agenda la cita desde una consulta activa. Este expediente no tiene consultas registradas; registra una primero. | validación | `app/Http/Requests/CitaStoreRequest.php:95` | Al enviar el formulario con datos inválidos | Tuteo unificado (antes "Debe/Solicite/Corrija...") |
| Agenda la cita desde la consulta activa (la más reciente del expediente). | validación | `app/Http/Requests/CitaStoreRequest.php:104` | Al enviar el formulario con datos inválidos | Tuteo unificado (antes "Debe/Solicite/Corrija...") |
| El rango de fechas no puede exceder  | validación | `app/Http/Requests/IndexCitasRequest.php:43` | Al enviar el formulario con datos inválidos | Límite interpolado desde config/limites.php |
| No se puede guardar la cita: el perfil profesional no existe. | error | `app/Models/Cita.php:46` | Al violar una regla de negocio al guardar | OK |
| profesional_user_id debe coincidir con el usuario dueño del perfil profesional. | error | `app/Models/Cita.php:55` | Al violar una regla de negocio al guardar | OK |
| Solo se puede registrar asistencia de citas programadas. | error | `app/Models/Cita.php:167` | Al violar una regla de negocio al guardar | OK |
| No se puede registrar asistencia antes de la hora programada de la cita. | error | `app/Models/Cita.php:173` | Al violar una regla de negocio al guardar | OK |
| Indica el motivo del cambio y la nueva fecha acordada con el paciente. El motivo clínico original se conserva. | error | `resources/js/Components/Expediente/GestionarCitaModal.vue:69` | Fallo de red/permiso/validación en el cliente | Tuteo unificado (antes "Debe/Solicite/Corrija...") |
| Indica por qué cancelas esta cita. | error | `resources/js/Components/Expediente/GestionarCitaModal.vue:71` | Fallo de red/permiso/validación en el cliente | Tuteo unificado (antes "Debe/Solicite/Corrija...") |
| No hay citas para esta semana | vacío | `resources/js/Pages/Citas/Index.vue:277` | Cuando no hay datos para mostrar | OK |

## Alertas (55)

| Mensaje | Tipo | Origen | Cuándo aparece | Hallazgo |
|---|---|---|---|---|
| Seguimiento preventivo registrado. | flash | `app/Http/Controllers/AlertaPreventivaController.php:82` | Tras completar la acción (banner/toast) | OK |
| Alerta cerrada correctamente. | flash | `app/Http/Controllers/AlertaPreventivaController.php:94` | Tras completar la acción (banner/toast) | Unificado "correctamente" (antes mezcla con "exitosamente") |
| Alerta descartada correctamente. | flash | `app/Http/Controllers/AlertaPreventivaController.php:106` | Tras completar la acción (banner/toast) | Unificado "correctamente" (antes mezcla con "exitosamente") |
| Solo se pueden cerrar alertas activas o en seguimiento. | validación | `app/Http/Requests/AlertaCerrarRequest.php:47` | Al enviar el formulario con datos inválidos | OK |
| El resultado documentado es obligatorio para cerrar la alerta. | validación | `app/Http/Requests/AlertaCerrarRequest.php:56` | Al enviar el formulario con datos inválidos | OK |
| El resultado debe tener al menos :min caracteres. | validación | `app/Http/Requests/AlertaCerrarRequest.php:57` | Al enviar el formulario con datos inválidos | OK |
| Solo se pueden descartar alertas activas o en seguimiento. | validación | `app/Http/Requests/AlertaDescartarRequest.php:47` | Al enviar el formulario con datos inválidos | OK |
| La justificación del descarte es obligatoria. | validación | `app/Http/Requests/AlertaDescartarRequest.php:56` | Al enviar el formulario con datos inválidos | OK |
| La justificación debe tener al menos :min caracteres. | validación | `app/Http/Requests/AlertaDescartarRequest.php:57` | Al enviar el formulario con datos inválidos | OK |
| No se puede registrar seguimiento sobre una alerta terminal. | validación | `app/Http/Requests/SeguimientoStoreRequest.php:69` | Al enviar el formulario con datos inválidos | OK |
| La valoración es obligatoria. | validación | `app/Http/Requests/SeguimientoStoreRequest.php:78` | Al enviar el formulario con datos inválidos | OK |
| La valoración debe tener al menos :min caracteres. | validación | `app/Http/Requests/SeguimientoStoreRequest.php:79` | Al enviar el formulario con datos inválidos | OK |
| La acción preventiva es obligatoria. | validación | `app/Http/Requests/SeguimientoStoreRequest.php:80` | Al enviar el formulario con datos inválidos | OK |
| La acción preventiva debe tener al menos :min caracteres. | validación | `app/Http/Requests/SeguimientoStoreRequest.php:81` | Al enviar el formulario con datos inválidos | OK |
| El responsable del seguimiento es obligatorio. | validación | `app/Http/Requests/SeguimientoStoreRequest.php:82` | Al enviar el formulario con datos inválidos | OK |
| El responsable debe ser el profesional que registra el seguimiento. | validación | `app/Http/Requests/SeguimientoStoreRequest.php:83` | Al enviar el formulario con datos inválidos | OK |
| La fecha de próxima revisión es obligatoria. | validación | `app/Http/Requests/SeguimientoStoreRequest.php:84` | Al enviar el formulario con datos inválidos | OK |
| La próxima revisión no puede ser anterior a hoy. | validación | `app/Http/Requests/SeguimientoStoreRequest.php:85` | Al enviar el formulario con datos inválidos | OK |
| La próxima revisión no puede exceder  | validación | `app/Http/Requests/SeguimientoStoreRequest.php:86` | Al enviar el formulario con datos inválidos | Límite interpolado desde config/limites.php |
| Los seguimientos preventivos no se pueden modificar ni eliminar. | error | `app/Models/SeguimientoPreventivo.php:46` | Al violar una regla de negocio al guardar | Antes en inglés; traducido |
| Los seguimientos preventivos no se pueden modificar ni eliminar. | error | `app/Models/SeguimientoPreventivo.php:49` | Al violar una regla de negocio al guardar | Antes en inglés; traducido |
| No se puede registrar seguimiento en una alerta cerrada o descartada. Abre una alerta activa. | error | `app/Services/AlertaSeguimientoService.php:26` | Al violar una regla de negocio al guardar | OK |
| No tienes un perfil profesional activo en el área de la alerta. Pide al Administrador del sistema que te asigne a esa área. | error | `app/Services/AlertaSeguimientoService.php:33` | Al violar una regla de negocio al guardar | Nombre de rol oficial (antes "Sysadmin"/"administrador") |
| Solo se pueden cerrar alertas activas o en seguimiento. | error | `app/Services/AlertaSeguimientoService.php:64` | Al violar una regla de negocio al guardar | OK |
| No tienes un perfil profesional activo en el área de la alerta. Pide al Administrador del sistema que te asigne a esa área. | error | `app/Services/AlertaSeguimientoService.php:71` | Al violar una regla de negocio al guardar | Nombre de rol oficial (antes "Sysadmin"/"administrador") |
| Solo se pueden descartar alertas activas o en seguimiento. | error | `app/Services/AlertaSeguimientoService.php:93` | Al violar una regla de negocio al guardar | OK |
| No tienes un perfil profesional activo en el área de la alerta. Pide al Administrador del sistema que te asigne a esa área. | error | `app/Services/AlertaSeguimientoService.php:100` | Al violar una regla de negocio al guardar | Nombre de rol oficial (antes "Sysadmin"/"administrador") |
| Alerta preventiva: el paciente acumula {$total} ausencias en el rango seleccionado. | error | `app/Services/AlertasPreventivasService.php:124` | Al violar una regla de negocio al guardar | OK |
| Alerta preventiva: el paciente acumula {$total} ausencias en los últimos {$ventanaDias} días. | error | `app/Services/AlertasPreventivasService.php:125` | Al violar una regla de negocio al guardar | Límite interpolado desde config/limites.php |
| Alerta preventiva: el paciente acumula {$total} ausencias desde la fecha de derivación. | error | `app/Services/AlertasPreventivasService.php:126` | Al violar una regla de negocio al guardar | OK |
| Alerta preventiva: el paciente alcanzó un puntaje de {$total} por ausencias. | error | `app/Services/AlertasPreventivasService.php:127` | Al violar una regla de negocio al guardar | OK |
| Por privacidad, se ocultan los grupos con menos de {{ $page.props.privacidad?.min_celda }} casos y los totales que permitirían deducirlos. | vacío | `resources/js/Components/Dashboard/AlertOverviewCharts.vue:227` | Cuando no hay datos para mostrar | Umbral interpolado desde PrivacyThreshold::minCell() / $page.props.privacidad |
| Aún no hay alertas registradas. | vacío | `resources/js/Components/Dashboard/AlertOverviewCharts.vue:309` | Cuando no hay datos para mostrar | OK |
| El detalle individual está reservado al personal clínico autorizado. | error | `resources/js/Components/Dashboard/AlertOverviewCharts.vue:344` | Fallo de red/permiso/validación en el cliente | OK |
| No hay alertas activas pendientes. | vacío | `resources/js/Components/Dashboard/AlertOverviewCharts.vue:344` | Cuando no hay datos para mostrar | OK |
| Conteos ocultos por privacidad. | vacío | `resources/js/Components/Dashboard/AlertOverviewCharts.vue:376` | Cuando no hay datos para mostrar | OK |
| Aún no hay etiquetas registradas en expedientes. | vacío | `resources/js/Components/Dashboard/AlertOverviewCharts.vue:376` | Cuando no hay datos para mostrar | OK |
| Conteos ocultos por privacidad. | vacío | `resources/js/Components/Dashboard/AlertOverviewCharts.vue:507` | Cuando no hay datos para mostrar | OK |
| Sin casos de tendencia suicida. | vacío | `resources/js/Components/Dashboard/AlertOverviewCharts.vue:507` | Cuando no hay datos para mostrar | OK |
| Sin desglose por carrera. | vacío | `resources/js/Components/Dashboard/AlertOverviewCharts.vue:538` | Cuando no hay datos para mostrar | OK |
| No fue posible cargar las alertas. Inténtalo de nuevo. | error | `resources/js/Pages/Alertas/Index.vue:61` | Fallo de red/permiso/validación en el cliente | OK |
| No hay alertas para mostrar | vacío | `resources/js/Pages/Alertas/Index.vue:160` | Cuando no hay datos para mostrar | OK |
| No hay alertas con este estado. Prueba con otro estado. | vacío | `resources/js/Pages/Alertas/Index.vue:162` | Cuando no hay datos para mostrar | OK |
| No hay alertas registradas. | vacío | `resources/js/Pages/Alertas/Index.vue:162` | Cuando no hay datos para mostrar | OK |
| Revisar alerta de ${signalLabel(alert.senal)} para el paciente ${alert.paciente_carnet} | error | `resources/js/Pages/Alertas/Index.vue:187` | Fallo de red/permiso/validación en el cliente | OK |
| No tienes permiso para realizar esta acción. | error | `resources/js/Pages/Alertas/Show.vue:88` | Fallo de red/permiso/validación en el cliente | OK |
| No fue posible guardar los cambios. Inténtalo de nuevo. | error | `resources/js/Pages/Alertas/Show.vue:91` | Fallo de red/permiso/validación en el cliente | OK |
| No se pudo conectar. Revisa tu conexión e inténtalo de nuevo. | error | `resources/js/Pages/Alertas/Show.vue:96` | Fallo de red/permiso/validación en el cliente | OK |
| No fue posible cargar la información de esta alerta. | error | `resources/js/Pages/Alertas/Show.vue:215` | Fallo de red/permiso/validación en el cliente | OK |
| No hay una revisión programada. | vacío | `resources/js/Pages/Alertas/Show.vue:288` | Cuando no hay datos para mostrar | OK |
| Aún no se han registrado seguimientos. | vacío | `resources/js/Pages/Alertas/Show.vue:300` | Cuando no hay datos para mostrar | OK |
| No tienes permisos para registrar seguimientos ni cambiar el estado de esta alerta. | error | `resources/js/Pages/Alertas/Show.vue:347` | Fallo de red/permiso/validación en el cliente | OK |
| Revisa los campos marcados: | error | `resources/js/Pages/Alertas/Show.vue:357` | Fallo de red/permiso/validación en el cliente | OK |
| Revisa el campo marcado: | error | `resources/js/Pages/Alertas/Show.vue:473` | Fallo de red/permiso/validación en el cliente | OK |
| Revisa el campo marcado: | error | `resources/js/Pages/Alertas/Show.vue:499` | Fallo de red/permiso/validación en el cliente | OK |

## Rúbricas (19)

| Mensaje | Tipo | Origen | Cuándo aparece | Hallazgo |
|---|---|---|---|---|
| La versión de la rúbrica se creó correctamente. | flash | `app/Http/Controllers/RubricaController.php:72` | Tras completar la acción (banner/toast) | Unificado "correctamente" (antes mezcla con "exitosamente") |
| La fecha de vigencia debe incluir hora y minutos. | validación | `app/Http/Requests/RubricaVersionStoreRequest.php:54` | Al enviar el formulario con datos inválidos | OK |
| La fecha de vigencia debe ser una fecha y hora válida. | validación | `app/Http/Requests/RubricaVersionStoreRequest.php:62` | Al enviar el formulario con datos inválidos | OK |
| La fecha de vigencia debe ser posterior a la fecha y hora actuales. | validación | `app/Http/Requests/RubricaVersionStoreRequest.php:71` | Al enviar el formulario con datos inválidos | OK |
| La fecha de vigencia debe ser posterior a la última versión. | validación | `app/Http/Requests/RubricaVersionStoreRequest.php:75` | Al enviar el formulario con datos inválidos | OK |
| La combinación de señal, indicador y cálculo debe ser única. | validación | `app/Http/Requests/RubricaVersionStoreRequest.php:100` | Al enviar el formulario con datos inválidos | OK |
| La ventana de días es obligatoria para el cálculo por frecuencia. | validación | `app/Http/Requests/RubricaVersionStoreRequest.php:106` | Al enviar el formulario con datos inválidos | OK |
| La clave del indicador es obligatoria para tendencia suicida. | validación | `app/Http/Requests/RubricaVersionStoreRequest.php:111` | Al enviar el formulario con datos inválidos | OK |
| La tendencia suicida debe calcularse por puntaje. | validación | `app/Http/Requests/RubricaVersionStoreRequest.php:115` | Al enviar el formulario con datos inválidos | OK |
| La tendencia suicida no usa una ventana de días. | validación | `app/Http/Requests/RubricaVersionStoreRequest.php:119` | Al enviar el formulario con datos inválidos | Límite interpolado desde config/limites.php |
| La clave del indicador solo aplica a tendencia suicida. | validación | `app/Http/Requests/RubricaVersionStoreRequest.php:122` | Al enviar el formulario con datos inválidos | OK |
| La rúbrica debe incluir cada tipo de señal. | validación | `app/Http/Requests/RubricaVersionStoreRequest.php:128` | Al enviar el formulario con datos inválidos | OK |
| Este registro de rúbrica es inmutable. | error | `app/Models/Concerns/AppendOnly.php:14` | Al violar una regla de negocio al guardar | OK |
| Este registro de rúbrica es inmutable. | error | `app/Models/Concerns/AppendOnly.php:18` | Al violar una regla de negocio al guardar | OK |
| La fecha de vigencia debe ser posterior a la fecha y hora actuales y a la última versión. | error | `app/Services/RubricaVersionService.php:61` | Al violar una regla de negocio al guardar | OK |
| La justificación del cambio es obligatoria desde la segunda versión. | error | `app/Services/RubricaVersionService.php:67` | Al violar una regla de negocio al guardar | OK |
| Otra versión ya utiliza esta fecha de vigencia. | error | `app/Services/RubricaVersionService.php:101` | Al violar una regla de negocio al guardar | OK |
| La combinación de señal, indicador y cálculo debe ser única. | error | `app/Services/RubricaVersionService.php:107` | Al violar una regla de negocio al guardar | OK |
| Sin ventana de días para este cálculo. | vacío | `resources/js/Pages/Rubricas/Create.vue:351` | Cuando no hay datos para mostrar | OK |

## Indicadores / Reportes (50)

| Mensaje | Tipo | Origen | Cuándo aparece | Hallazgo |
|---|---|---|---|---|
| El período no puede exceder  | validación | `app/Http/Requests/IndicadoresRequest.php:80` | Al enviar el formulario con datos inválidos | Límite interpolado desde config/limites.php |
| La fecha inicial es obligatoria. | validación | `app/Http/Requests/IndicadoresRequest.php:101` | Al enviar el formulario con datos inválidos | OK |
| La fecha inicial debe tener el formato AAAA-MM-DD. | validación | `app/Http/Requests/IndicadoresRequest.php:102` | Al enviar el formulario con datos inválidos | OK |
| La fecha final es obligatoria. | validación | `app/Http/Requests/IndicadoresRequest.php:103` | Al enviar el formulario con datos inválidos | OK |
| La fecha final debe tener el formato AAAA-MM-DD. | validación | `app/Http/Requests/IndicadoresRequest.php:104` | Al enviar el formulario con datos inválidos | OK |
| La fecha final debe ser igual o posterior a la fecha inicial. | validación | `app/Http/Requests/IndicadoresRequest.php:105` | Al enviar el formulario con datos inválidos | Redacción unificada para fecha final |
| El área seleccionada no es válida. | validación | `app/Http/Requests/IndicadoresRequest.php:106` | Al enviar el formulario con datos inválidos | OK |
| El área seleccionada no existe. | validación | `app/Http/Requests/IndicadoresRequest.php:107` | Al enviar el formulario con datos inválidos | OK |
| Selecciona al menos un indicador. | validación | `app/Http/Requests/IndicadoresRequest.php:108` | Al enviar el formulario con datos inválidos | OK |
| La selección de indicadores no es válida. | validación | `app/Http/Requests/IndicadoresRequest.php:109` | Al enviar el formulario con datos inválidos | OK |
| Selecciona al menos un indicador. | validación | `app/Http/Requests/IndicadoresRequest.php:110` | Al enviar el formulario con datos inválidos | OK |
| Uno de los indicadores seleccionados no existe. | validación | `app/Http/Requests/IndicadoresRequest.php:111` | Al enviar el formulario con datos inválidos | OK |
| El período no puede exceder  | validación | `app/Http/Requests/ReporteGenerarRequest.php:59` | Al enviar el formulario con datos inválidos | Límite interpolado desde config/limites.php |
| Selecciona un área válida. | validación | `app/Http/Requests/ReporteGenerarRequest.php:80` | Al enviar el formulario con datos inválidos | OK |
| El área seleccionada no existe. | validación | `app/Http/Requests/ReporteGenerarRequest.php:86` | Al enviar el formulario con datos inválidos | OK |
| La fecha inicial es obligatoria. | validación | `app/Http/Requests/ReporteGenerarRequest.php:116` | Al enviar el formulario con datos inválidos | OK |
| La fecha final es obligatoria. | validación | `app/Http/Requests/ReporteGenerarRequest.php:117` | Al enviar el formulario con datos inválidos | OK |
| La fecha final debe ser igual o posterior a la fecha inicial. | validación | `app/Http/Requests/ReporteGenerarRequest.php:118` | Al enviar el formulario con datos inválidos | Redacción unificada para fecha final |
| Selecciona un área antes de generar el reporte. | validación | `app/Http/Requests/ReporteGenerarRequest.php:119` | Al enviar el formulario con datos inválidos | OK |
| Selecciona al menos un indicador. | validación | `app/Http/Requests/ReporteGenerarRequest.php:120` | Al enviar el formulario con datos inválidos | OK |
| Selecciona al menos un indicador. | validación | `app/Http/Requests/ReporteGenerarRequest.php:121` | Al enviar el formulario con datos inválidos | OK |
| Uno de los indicadores seleccionados no existe. | validación | `app/Http/Requests/ReporteGenerarRequest.php:122` | Al enviar el formulario con datos inválidos | OK |
| Selecciona una forma de agrupación válida. | validación | `app/Http/Requests/ReporteGenerarRequest.php:123` | Al enviar el formulario con datos inválidos | OK |
| Selecciona al menos una forma de agrupación. | validación | `app/Http/Requests/ReporteGenerarRequest.php:124` | Al enviar el formulario con datos inválidos | OK |
| Puedes seleccionar como máximo dos agrupaciones. | validación | `app/Http/Requests/ReporteGenerarRequest.php:125` | Al enviar el formulario con datos inválidos | OK |
| Una de las agrupaciones seleccionadas no es válida. | validación | `app/Http/Requests/ReporteGenerarRequest.php:126` | Al enviar el formulario con datos inválidos | OK |
| No se pudo crear temporal para Excel. | error | `app/Services/ReporteExportService.php:93` | Al violar una regla de negocio al guardar | OK |
| No tienes acceso a estos indicadores. | error | `resources/js/Pages/Indicadores/Index.vue:52` | Fallo de red/permiso/validación en el cliente | OK |
| No fue posible consultar los indicadores. | error | `resources/js/Pages/Indicadores/Index.vue:53` | Fallo de red/permiso/validación en el cliente | OK |
| No se pudo conectar. Revisa tu conexión e inténtalo de nuevo. | error | `resources/js/Pages/Indicadores/Index.vue:56` | Fallo de red/permiso/validación en el cliente | OK |
| Por privacidad, los grupos con menos de {{ minCelda }} casos y los totales que permitirían deducirlos se ocultan. | vacío | `resources/js/Pages/Indicadores/Index.vue:191` | Cuando no hay datos para mostrar | Umbral interpolado desde PrivacyThreshold::minCell() / $page.props.privacidad |
| Sin datos en el período | vacío | `resources/js/Pages/Indicadores/Index.vue:197` | Cuando no hay datos para mostrar | OK |
| Oculto porque el grupo es pequeño | vacío | `resources/js/Pages/Indicadores/Index.vue:214` | Cuando no hay datos para mostrar | OK |
| Oculto para evitar deducir otros conteos | vacío | `resources/js/Pages/Indicadores/Index.vue:214` | Cuando no hay datos para mostrar | OK |
| Oculto porque el grupo es pequeño | vacío | `resources/js/Pages/Indicadores/Index.vue:243` | Cuando no hay datos para mostrar | OK |
| Oculto para evitar que se deduzcan otros conteos | vacío | `resources/js/Pages/Indicadores/Index.vue:243` | Cuando no hay datos para mostrar | OK |
| Oculto porque el grupo es pequeño | vacío | `resources/js/Pages/Indicadores/Index.vue:245` | Cuando no hay datos para mostrar | OK |
| Oculto para evitar que se deduzcan otros conteos | vacío | `resources/js/Pages/Indicadores/Index.vue:245` | Cuando no hay datos para mostrar | OK |
| Oculto porque el grupo es pequeño | vacío | `resources/js/Pages/Indicadores/Index.vue:266` | Cuando no hay datos para mostrar | OK |
| Oculto para evitar deducir otros conteos | vacío | `resources/js/Pages/Indicadores/Index.vue:266` | Cuando no hay datos para mostrar | OK |
| No tienes permiso para generar un reporte con esos parámetros. | error | `resources/js/Pages/Reportes/Index.vue:59` | Fallo de red/permiso/validación en el cliente | OK |
| No se pudo generar el reporte. Inténtalo de nuevo. | error | `resources/js/Pages/Reportes/Index.vue:60` | Fallo de red/permiso/validación en el cliente | OK |
| No se pudo conectar. Revisa tu conexión e inténtalo de nuevo. | error | `resources/js/Pages/Reportes/Index.vue:63` | Fallo de red/permiso/validación en el cliente | OK |
| No tienes permiso para exportar con esos parámetros. | error | `resources/js/Pages/Reportes/Index.vue:92` | Fallo de red/permiso/validación en el cliente | OK |
| Revisa los parámetros antes de exportar. | error | `resources/js/Pages/Reportes/Index.vue:98` | Fallo de red/permiso/validación en el cliente | OK |
| No fue posible generar el archivo de exportación. | error | `resources/js/Pages/Reportes/Index.vue:102` | Fallo de red/permiso/validación en el cliente | OK |
| No se pudo conectar. Revisa tu conexión e inténtalo de nuevo. | error | `resources/js/Pages/Reportes/Index.vue:119` | Fallo de red/permiso/validación en el cliente | OK |
| Por privacidad, los grupos con menos de {{ minCelda }} casos y los totales que permitirían deducirlos se ocultan. | vacío | `resources/js/Pages/Reportes/Index.vue:255` | Cuando no hay datos para mostrar | Umbral interpolado desde PrivacyThreshold::minCell() / $page.props.privacidad |
| No hay registros que coincidan con estos filtros. Por eso, los totales son 0. | vacío | `resources/js/Pages/Reportes/Index.vue:274` | Cuando no hay datos para mostrar | OK |
| No hay resultados para esta combinación. Prueba con otros filtros. | vacío | `resources/js/Pages/Reportes/Index.vue:306` | Cuando no hay datos para mostrar | OK |

## Bitácora / Auditoría (23)

| Mensaje | Tipo | Origen | Cuándo aparece | Hallazgo |
|---|---|---|---|---|
| El período no puede exceder  | validación | `app/Http/Requests/BitacoraIndexRequest.php:64` | Al enviar el formulario con datos inválidos | Límite interpolado desde config/limites.php |
| La fecha inicial es obligatoria. | validación | `app/Http/Requests/BitacoraIndexRequest.php:88` | Al enviar el formulario con datos inválidos | OK |
| La fecha final es obligatoria. | validación | `app/Http/Requests/BitacoraIndexRequest.php:89` | Al enviar el formulario con datos inválidos | OK |
| La fecha final debe ser igual o posterior a la inicial. | validación | `app/Http/Requests/BitacoraIndexRequest.php:90` | Al enviar el formulario con datos inválidos | Redacción unificada para fecha final |
| La acción seleccionada no es válida. | validación | `app/Http/Requests/BitacoraIndexRequest.php:91` | Al enviar el formulario con datos inválidos | OK |
| El usuario seleccionado no existe. | validación | `app/Http/Requests/BitacoraIndexRequest.php:92` | Al enviar el formulario con datos inválidos | OK |
| Los registros de auditoría son inmutables. | error | `app/Models/Audit.php:18` | Al violar una regla de negocio al guardar | OK |
| Los registros de auditoría son inmutables. | error | `app/Models/Audit.php:22` | Al violar una regla de negocio al guardar | OK |
| Los registros de auditoría son inmutables. | error | `app/Models/Audit.php:29` | Al violar una regla de negocio al guardar | OK |
| Los registros de auditoría de corrección son inmutables. | error | `app/Models/PacienteCorreccionAuditoria.php:63` | Al violar una regla de negocio al guardar | OK |
| Los registros de auditoría de corrección no pueden eliminarse. | error | `app/Models/PacienteCorreccionAuditoria.php:68` | Al violar una regla de negocio al guardar | OK |
| Solo los avisos sensibles pueden marcarse como revisados. | error | `app/Models/PacienteCorreccionAuditoria.php:95` | Al violar una regla de negocio al guardar | OK |
| No se pudo crear temporal para la exportación. | error | `app/Services/BitacoraExportService.php:46` | Al violar una regla de negocio al guardar | OK |
| No se pudo abrir el temporal CSV. | error | `app/Services/BitacoraExportService.php:54` | Al violar una regla de negocio al guardar | OK |
| Fallo simulado de exportación de bitácora. | error | `app/Services/BitacoraExportService.php:112` | Al violar una regla de negocio al guardar | OK |
| EXPLAIN (FORMAT TEXT) SELECT audits.id FROM audits WHERE audits.created_at >= ? AND audits.created_at <= ? | error | `app/Services/BitacoraQueryService.php:195` | Al violar una regla de negocio al guardar | OK |
| No tienes permiso para exportar la bitácora. | error | `resources/js/Pages/Admin/Bitacora/Index.vue:76` | Fallo de red/permiso/validación en el cliente | OK |
| No se pudo exportar con esos filtros. | error | `resources/js/Pages/Admin/Bitacora/Index.vue:82` | Fallo de red/permiso/validación en el cliente | OK |
| No fue posible generar el archivo de evidencia. | error | `resources/js/Pages/Admin/Bitacora/Index.vue:86` | Fallo de red/permiso/validación en el cliente | OK |
| No se pudo conectar. Revisa tu conexión e inténtalo de nuevo. | error | `resources/js/Pages/Admin/Bitacora/Index.vue:106` | Fallo de red/permiso/validación en el cliente | OK |
| Sin resultados para los filtros aplicados | vacío | `resources/js/Pages/Admin/Bitacora/Index.vue:221` | Cuando no hay datos para mostrar | OK |
| Sin nombres de campo registrados. | vacío | `resources/js/Pages/Admin/Bitacora/Index.vue:268` | Cuando no hay datos para mostrar | OK |
| No hay eventos en el período seleccionado. | vacío | `resources/js/Pages/Admin/Bitacora/Index.vue:273` | Cuando no hay datos para mostrar | OK |

## Admin / Usuarios (15)

| Mensaje | Tipo | Origen | Cuándo aparece | Hallazgo |
|---|---|---|---|---|
| Esta área ya tiene un Coordinador de área activo. Elige otra área o desactiva al coordinador actual. | error | `app/Http/Controllers/Admin/UserController.php:123` | Al ejecutar la acción en el controlador | OK |
| Usuario creado. Se ha enviado el enlace de configuración al correo del usuario. | flash | `app/Http/Controllers/Admin/UserController.php:170` | Tras completar la acción (banner/toast) | OK |
| Perfil del Administrador del sistema actualizado. | validación | `app/Http/Controllers/Admin/UserController.php:213` | Al ejecutar la acción en el controlador | Nombre de rol oficial (antes "Sysadmin"/"administrador") |
| Esta área ya tiene un Coordinador de área activo. Elige otra área o desactiva al coordinador actual. | error | `app/Http/Controllers/Admin/UserController.php:237` | Al ejecutar la acción en el controlador | OK |
| Usuario actualizado correctamente. | validación | `app/Http/Controllers/Admin/UserController.php:278` | Al ejecutar la acción en el controlador | Unificado "correctamente" (antes mezcla con "exitosamente") |
| No puedes desactivar tu propia cuenta. | error | `app/Http/Controllers/Admin/UserController.php:290` | Al ejecutar la acción en el controlador | OK |
| Usuario desactivado. Ya no podrá ingresar al sistema. | flash | `app/Http/Controllers/Admin/UserController.php:298` | Tras completar la acción (banner/toast) | OK |
| Bienvenido a Sistema PANDORA - Configuración de Acceso | notificación | `app/Notifications/UserCreatedNotification.php:37` | Al ejecutar la acción en el controlador | OK |
| Tu cuenta en el Sistema PANDORA ha sido creada exitosamente. | notificación | `app/Notifications/UserCreatedNotification.php:39` | Al ejecutar la acción en el controlador | OK |
| Para completar la configuración de tu cuenta y establecer tu contraseña definitiva, por favor haz clic en el siguiente enlace: | notificación | `app/Notifications/UserCreatedNotification.php:40` | Al ejecutar la acción en el controlador | OK |
| Este enlace de acceso seguro expirará en 24 horas. | notificación | `app/Notifications/UserCreatedNotification.php:42` | Al ejecutar la acción en el controlador | OK |
| Si no esperabas este correo, puedes ignorarlo. | notificación | `app/Notifications/UserCreatedNotification.php:43` | Al ejecutar la acción en el controlador | OK |
| Registra un nuevo usuario en el sistema. Se generará una contraseña segura automáticamente. | error | `resources/js/Pages/Admin/Users/Form.vue:71` | Fallo de red/permiso/validación en el cliente | OK |
| No puedes cambiar la contraseña de esta cuenta desde esta pantalla. Esta opción está deshabilitada para proteger los datos cifrados. | error | `resources/js/Pages/Admin/Users/Form.vue:144` | Fallo de red/permiso/validación en el cliente | OK |
| Intenta con otro nombre, correo o rol. | error | `resources/js/Pages/Admin/Users/Index.vue:230` | Fallo de red/permiso/validación en el cliente | OK |

## Genérico / HTTP (32)

| Mensaje | Tipo | Origen | Cuándo aparece | Hallazgo |
|---|---|---|---|---|
| No se pudo descifrar el campo %s (área ni clave legacy). | error | `app/Casts/AreaEncryptedFieldCast.php:38` | Al ejecutar la acción en el controlador | Antes en inglés; traducido |
| No se pudo resolver el area_id para el cifrado en el modelo %s. | error | `app/Casts/AreaEncryptedFieldCast.php:84` | Al ejecutar la acción en el controlador | OK |
| Error al decodificar JSON descifrado:  | error | `app/Casts/EncryptedArrayCast.php:32` | Al ejecutar la acción en el controlador | OK |
| EncryptedArrayCast espera un array u objeto para serializar. | error | `app/Casts/EncryptedArrayCast.php:48` | Al ejecutar la acción en el controlador | OK |
| Error al codificar a JSON:  | error | `app/Casts/EncryptedArrayCast.php:53` | Al ejecutar la acción en el controlador | OK |
| No se pudo resolver el area_id para el cifrado en el modelo %s. | error | `app/Casts/EncryptedArrayCast.php:87` | Al ejecutar la acción en el controlador | OK |
| El valor cifrado del campo está malformado (base64 inválido). | error | `app/Casts/EncryptedFieldCast.php:43` | Al ejecutar la acción en el controlador | OK |
| No se pudo descifrar el campo. Posible corrupción o clave incorrecta. | error | `app/Casts/EncryptedFieldCast.php:50` | Al ejecutar la acción en el controlador | Antes en inglés; traducido |
| El Administrador del sistema no tiene acceso a operaciones clínicas. | error | `app/Http/Middleware/EnforceAreaScope.php:27` | Al acceder a una ruta sin permiso o sin sesión | Nombre de rol oficial (antes "Sysadmin"/"administrador") |
| No tienes acceso a este expediente clínico. Solo puedes ver expedientes de tu área. | error | `app/Http/Middleware/EnforceAreaScope.php:37` | Al acceder a una ruta sin permiso o sin sesión | OK |
| No tienes acceso a este expediente clínico. Solo puedes ver expedientes de tu área. | error | `app/Http/Middleware/EnforceAreaScope.php:43` | Al acceder a una ruta sin permiso o sin sesión | OK |
| Acceso denegado. Esta sección es solo para el Administrador del sistema. | error | `app/Http/Middleware/EnsureIsSysadmin.php:22` | Al acceder a una ruta sin permiso o sin sesión | Nombre de rol oficial (antes "Sysadmin"/"administrador") |
| Debes iniciar sesión para acceder a esta sección. | error | `app/Http/Middleware/RoleMiddleware.php:20` | Al acceder a una ruta sin permiso o sin sesión | OK |
| No tienes permiso para acceder a esta sección. Si lo necesitas, solicítalo al Administrador del sistema. | error | `app/Http/Middleware/RoleMiddleware.php:36` | Al acceder a una ruta sin permiso o sin sesión | Nombre de rol oficial (antes "Sysadmin"/"administrador") |
| La variable AREA_KEY_SECRET no está configurada o es inválida. | error | `app/Services/AreaEncryptionService.php:18` | Al violar una regla de negocio al guardar | OK |
| El payload encriptado no tiene un formato base64 válido. | error | `app/Services/AreaEncryptionService.php:45` | Al violar una regla de negocio al guardar | OK |
| El payload es demasiado corto para contener un nonce. | error | `app/Services/AreaEncryptionService.php:51` | Al violar una regla de negocio al guardar | OK |
| No se pudo descifrar: llave de área incorrecta o datos corruptos. | error | `app/Services/AreaEncryptionService.php:65` | Al violar una regla de negocio al guardar | Antes en inglés; traducido |
| Clave de sesión no disponible. La sesión puede haber expirado. | error | `app/Services/Crypto/EncryptionContextService.php:36` | Al violar una regla de negocio al guardar | OK |
| BLIND_INDEX_SECRET no configurado. Avisa al Administrador del sistema. | error | `app/Services/Crypto/EncryptionContextService.php:45` | Al violar una regla de negocio al guardar | Nombre de rol oficial (antes "Sysadmin"/"administrador") |
| BLIND_INDEX_SECRET debe ser 32 bytes en Base64. | error | `app/Services/Crypto/EncryptionContextService.php:52` | Al violar una regla de negocio al guardar | OK |
| No hay profesional responsable configurado para el área. | error | `app/Services/EvaluadorRiesgoPreventivo.php:418` | Al violar una regla de negocio al guardar | OK |
| No fue posible actualizar la notificación. Inténtalo de nuevo. | error | `resources/js/Layouts/ClinicalLayout.vue:91` | Fallo de red/permiso/validación en el cliente | OK |
| Aún no existen registros en tu área. Puedes comenzar registrando el primer paciente. | vacío | `resources/js/Pages/Dashboard.vue:181` | Cuando no hay datos para mostrar | OK |
| No hay pacientes registrados — canCreatePatient ? 'Aún no existen registros en tu área. Puedes comenzar registrando el primer paciente.' : 'Contacta al Referente Psicosocial para registrar pacientes.' | vacío | `resources/js/Pages/Dashboard.vue:181` | Lista vacía | OK |
| No tienes permisos para ver este recurso. Si crees que deberías poder acceder, contacta a tu coordinador o al administrador del sistema. | error | `resources/js/Pages/Error.vue:20` | Página de error HTTP | `detail` del abort() se muestra tal cual en 403/404/419/429; los abort() ya están en español. |
| La ruta o el recurso solicitado no existe, fue movido o ya no está disponible. | error | `resources/js/Pages/Error.vue:26` | Página de error HTTP | `detail` del abort() se muestra tal cual en 403/404/419/429; los abort() ya están en español. |
| La sesión o la página expiró. Recarga la página; si el problema continúa, cierra sesión e inicia de nuevo. | error | `resources/js/Pages/Error.vue:32` | Página de error HTTP | `detail` del abort() se muestra tal cual en 403/404/419/429; los abort() ya están en español. |
| Has realizado demasiados intentos en poco tiempo. Espera unos minutos e inténtalo nuevamente. | error | `resources/js/Pages/Error.vue:38` | Página de error HTTP | `detail` del abort() se muestra tal cual en 403/404/419/429; los abort() ya están en español. |
| No se pudo completar la solicitud. Inténtalo de nuevo en unos momentos; si el problema continúa, informa al administrador del sistema. | error | `resources/js/Pages/Error.vue:44` | Página de error HTTP | `detail` del abort() se muestra tal cual en 403/404/419/429; los abort() ya están en español. |
| PANDORA está en mantenimiento o temporalmente fuera de servicio. Vuelve a intentar en unos minutos. | error | `resources/js/Pages/Error.vue:50` | Página de error HTTP | `detail` del abort() se muestra tal cual en 403/404/419/429; los abort() ya están en español. |
| Algo salió mal al procesar tu solicitud. | error | `resources/js/Pages/Error.vue:57` | Página de error HTTP | `detail` del abort() se muestra tal cual en 403/404/419/429; los abort() ya están en español. |

## Hallazgos que no son solo texto

- Los flashes `->with('success', ...)` de agendar/reprogramar/cancelar cita y derivar paciente **nunca se mostraban**: `HandleInertiaRequests` solo comparte `flash.message`/`flash.variant`. Se agregó `message` + `variant` junto a `success` (se conserva `success` por los tests).
- `lang/es/validation.php` antepone el artículo al atributo ("El carnet"); en reglas con género distinto (p. ej. `exists`: "La carrera seleccionado") puede haber discordancia. Pendiente.
- `Pages/Admin/Citas/Index.vue:120` usa `console.error('Error fetching citas')` (solo consola, no visible al usuario).
- Longitudes mínimas "10 caracteres" en `Pacientes/Edit.vue` y `Consultas/Create.vue` están duplicadas a mano de las reglas PHP (coinciden hoy); no existe config compartida.
