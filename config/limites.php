<?php

declare(strict_types=1);

// Single source of truth for date-range limits (days). Shared with the frontend via Inertia.
return [
    'citas_rango_dias' => 45,
    'analitica_rango_dias' => 366, // inclusive days: bitacora, indicadores, reportes
    'historial_rango_dias' => 730,
    'alerta_revision_max_dias' => 365,
    'consulta_antiguedad_max_dias' => 365,
    'cita_horizonte_max_dias' => 365,
];
