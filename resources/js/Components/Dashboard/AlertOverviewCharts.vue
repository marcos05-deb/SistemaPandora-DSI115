<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { formatLocalDate } from '@/utils/dates';

const props = defineProps({
    overview: {
        type: Object,
        required: true,
    },
    /** Enlace a bandeja de alertas (coordinador/especialista). */
    showBandejaLink: {
        type: Boolean,
        default: true,
    },
    /** Abrir detalle de alerta desde “Requieren atención”. */
    linkUrgentes: {
        type: Boolean,
        default: true,
    },
});

const ESTADO_COLORS = {
    activa: 'var(--aurora-red)',
    en_seguimiento: 'var(--frost3)',
    cerrada: 'var(--aurora-green)',
    descartada: 'var(--nord3)',
};

const SENAL_COLORS = {
    crisis_psicologica: 'var(--aurora-orange)',
    ausencia_cita: 'var(--frost4)',
    tendencia_suicida: 'var(--aurora-red)',
};

const ETIQUETA_PALETTE = [
    'var(--frost4)',
    'var(--nord10)',
    'var(--aurora-orange)',
    'var(--aurora-green)',
    'var(--frost3)',
    'var(--aurora-red)',
    'var(--nord9)',
    'var(--nord8)',
    'var(--nord7)',
];

const FACULTAD_BAR_PALETTE = [
    { front: '#BF616A', top: '#D08770', side: '#9A4B53' },
    { front: '#5E81AC', top: '#81A1C1', side: '#49688F' },
    { front: '#A3BE8C', top: '#B8CFA3', side: '#839C70' },
    { front: '#B48EAD', top: '#C8A8C2', side: '#937391' },
    { front: '#D08770', top: '#E0A48C', side: '#B06E58' },
    { front: '#EBCB8B', top: '#F3DBA9', side: '#C9AB6F' },
    { front: '#88C0D0', top: '#A3D0DC', side: '#6C9EAC' },
    { front: '#81A1C1', top: '#9CB5CF', side: '#6685A3' },
];

function formatDate(dateStr) {
    return formatLocalDate(dateStr, { year: 'numeric', month: 'short', day: 'numeric' }, 'es-ES', '-');
}

const countLabel = (count) => count === null ? 'Protegido' : count;

const visible = computed(() => Boolean(props.overview?.visible));
const revealCarnets = computed(() => Boolean(props.overview?.reveal_carnets));
const scopeLabel = computed(() =>
    props.overview?.scope === 'institucional' ? 'institucional' : 'de tu área'
);

const maxSenalCount = computed(() => {
    const counts = (props.overview?.por_senal || []).map((row) => row.count);
    return Math.max(1, ...counts, 0);
});

const etiquetasConDatos = computed(() =>
    (props.overview?.por_etiqueta || []).filter((row) => row.count > 0)
);

const maxEtiquetaCount = computed(() => {
    const counts = etiquetasConDatos.value.map((row) => row.count);
    return Math.max(1, ...counts, 0);
});

const tendencia = computed(() => props.overview?.tendencia_suicida ?? {
    total_alumnos: 0,
    por_facultad: [],
    por_carrera: [],
});

const maxCarreraCount = computed(() => {
    const counts = (tendencia.value.por_carrera || []).map((row) => row.count);
    return Math.max(1, ...counts, 0);
});

const shortFacultadLabel = (label) => {
    const cleaned = (label || '')
        .replace(/^Facultad Multidisciplinaria (de |del |de la )?/i, '')
        .replace(/^Facultad (de |del |de la )?/i, '')
        .trim();
    return cleaned || label || '—';
};

const niceAxisMax = (value) => {
    const n = Math.max(1, Number(value) || 1);
    if (n <= 4) return 4;
    if (n <= 5) return 5;
    if (n <= 8) return 8;
    if (n <= 10) return 10;
    const exp = 10 ** Math.floor(Math.log10(n));
    const norm = n / exp;
    const nice = norm <= 1.5 ? 1.5 : norm <= 2 ? 2 : norm <= 3 ? 3 : norm <= 5 ? 5 : 10;
    return Math.ceil(nice * exp);
};

const facultad3dChart = computed(() => {
    const rows = (tendencia.value.por_facultad || []).filter((row) => row.count !== null);
    const plotLeft = 36;
    const plotRight = 16;
    const plotTop = 28;
    const plotBottom = 52;
    const depth = 14;
    const chartW = 420;
    const chartH = 260;
    const plotW = chartW - plotLeft - plotRight - depth;
    const plotH = chartH - plotTop - plotBottom - depth;
    const baselineY = plotTop + plotH;
    const maxCount = Math.max(0, ...rows.map((r) => r.count));
    const axisMax = niceAxisMax(maxCount);
    const tickCount = 4;
    const ticks = Array.from({ length: tickCount + 1 }, (_, i) => {
        const value = Math.round((axisMax / tickCount) * i);
        const y = baselineY - (value / axisMax) * plotH;
        return { value, y };
    });

    const n = rows.length;
    const barW = n <= 2 ? 52 : n <= 4 ? 44 : Math.min(40, Math.max(22, plotW / (n * 1.7)));
    const gap = n <= 2 ? 48 : n <= 4 ? 28 : Math.max(12, (plotW - n * barW) / (n + 1));
    const groupW = n * barW + Math.max(0, n - 1) * gap;
    const startX = plotLeft + Math.max(0, (plotW - groupW) / 2);

    const bars = rows.map((row, index) => {
        const colors = FACULTAD_BAR_PALETTE[index % FACULTAD_BAR_PALETTE.length];
        const h = Math.max(row.count > 0 ? 8 : 0, (row.count / axisMax) * plotH);
        const x = startX + index * (barW + gap);
        const y = baselineY - h;
        const short = shortFacultadLabel(row.label);
        const labelLines = short.length > 14
            ? [short.slice(0, 12).trim() + '…']
            : short.match(/.{1,12}(\s|$)/g)?.map((s) => s.trim()).filter(Boolean).slice(0, 2) || [short];

        return {
            id: row.id,
            label: row.label,
            short,
            labelLines,
            count: row.count,
            colors,
            front: `${x},${y} ${x + barW},${y} ${x + barW},${baselineY} ${x},${baselineY}`,
            top: `${x},${y} ${x + depth},${y - depth} ${x + barW + depth},${y - depth} ${x + barW},${y}`,
            side: `${x + barW},${y} ${x + barW + depth},${y - depth} ${x + barW + depth},${baselineY - depth} ${x + barW},${baselineY}`,
            valueX: x + barW / 2,
            valueY: y - depth - 6,
            labelX: x + barW / 2 + depth / 3,
        };
    });

    const floor = {
        front: `${plotLeft - 4},${baselineY} ${plotLeft + plotW + 8},${baselineY} ${plotLeft + plotW + 8 + depth},${baselineY + 10} ${plotLeft - 4 + depth},${baselineY + 10}`,
        top: `${plotLeft - 4},${baselineY} ${plotLeft - 4 + depth},${baselineY - depth} ${plotLeft + plotW + 8 + depth},${baselineY - depth} ${plotLeft + plotW + 8},${baselineY}`,
    };

    return {
        chartW,
        chartH,
        plotLeft,
        plotRight,
        plotTop,
        baselineY,
        plotW,
        depth,
        axisMax,
        ticks,
        bars,
        floor,
        gridRight: plotLeft + plotW + depth,
    };
});

const donutSegments = computed(() => {
    const rows = props.overview?.por_estado || [];
    const total = rows.reduce((sum, row) => sum + row.count, 0);
    const circumference = 2 * Math.PI * 40;
    if (total === 0) {
        return [{
            key: 'empty',
            color: 'var(--nord5)',
            dasharray: `${circumference} ${circumference}`,
            dashoffset: 0,
            count: 0,
            label: 'Sin alertas',
        }];
    }

    let offset = 0;
    return rows
        .filter((row) => row.count > 0)
        .map((row) => {
            const length = (row.count / total) * circumference;
            const segment = {
                key: row.key,
                label: row.label,
                count: row.count,
                color: ESTADO_COLORS[row.key] || 'var(--nord10)',
                dasharray: `${length} ${circumference - length}`,
                dashoffset: -offset,
            };
            offset += length;
            return segment;
        });
});
</script>

<template>
    <div v-if="visible" class="space-y-6">
        <p v-if="overview.privacidad_protegida" role="status" class="text-sm text-[var(--nord3)]">Los desgloses con grupos pequeños y sus totales relacionados se protegen para evitar la identificación de personas.</p>
        <section
            aria-labelledby="dashboard-alertas-title"
            class="bg-white rounded-2xl shadow-sm border border-[var(--nord4)] overflow-hidden animate-fade-in"
        >
            <div class="px-6 py-4 border-b border-[var(--nord4)] flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h2 id="dashboard-alertas-title" class="text-base font-semibold text-[var(--nord0)] flex items-center gap-2">
                        <svg class="h-4 w-4 text-[var(--aurora-red)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                        Alertas preventivas
                    </h2>
                    <p class="mt-1 text-sm text-[var(--nord3)]">
                        Resumen {{ scopeLabel }} · {{ countLabel(overview.abiertas) }} abiertas de {{ countLabel(overview.total) }} registradas
                        <span v-if="!revealCarnets" class="text-[var(--nord3)]"> · sin carnets</span>
                    </p>
                </div>
                <Link
                    v-if="showBandejaLink"
                    href="/alertas"
                    class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-[var(--nord10)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--nord9)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)]"
                >
                    Ver bandeja
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" /></svg>
                </Link>
            </div>

            <div class="grid grid-cols-1 gap-6 p-6 lg:grid-cols-3">
                <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-start lg:flex-col lg:items-center">
                    <div class="relative h-40 w-40 shrink-0">
                        <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90" role="img" :aria-label="`Distribución por estado: ${countLabel(overview.total)} alertas`">
                            <circle cx="50" cy="50" r="40" fill="none" stroke="var(--nord5)" stroke-width="12" />
                            <circle
                                v-for="segment in donutSegments"
                                :key="segment.key"
                                cx="50"
                                cy="50"
                                r="40"
                                fill="none"
                                :stroke="segment.color"
                                stroke-width="12"
                                :stroke-dasharray="segment.dasharray"
                                :stroke-dashoffset="segment.dashoffset"
                                stroke-linecap="butt"
                            />
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-2xl font-bold tabular-nums text-[var(--nord0)]">{{ countLabel(overview.total) }}</span>
                            <span class="text-[10px] uppercase tracking-wide text-[var(--nord3)]">Total</span>
                        </div>
                    </div>
                    <ul class="w-full space-y-1.5 text-sm" role="list">
                        <li v-for="row in overview.por_estado" :key="row.key" class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2 text-[var(--nord1)]">
                                <span class="h-2.5 w-2.5 rounded-full shrink-0" :style="{ background: ESTADO_COLORS[row.key] || 'var(--nord10)' }" />
                                {{ row.label }}
                            </span>
                            <span class="font-semibold tabular-nums text-[var(--nord0)]">{{ countLabel(row.count) }}</span>
                        </li>
                    </ul>
                </div>

                <div>
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-[var(--nord3)]">Por señal</h3>
                    <ul class="space-y-3" role="list">
                        <li v-for="row in overview.por_senal" :key="row.key">
                            <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                                <span class="truncate text-[var(--nord1)]">{{ row.label }}</span>
                                <span class="shrink-0 font-semibold tabular-nums text-[var(--nord0)]">{{ countLabel(row.count) }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-[var(--nord5)]" role="presentation">
                                <div
                                    class="h-full rounded-full transition-all duration-700 ease-out"
                                    :style="{
                                        width: `${(row.count / maxSenalCount) * 100}%`,
                                        background: SENAL_COLORS[row.key] || 'var(--nord10)',
                                    }"
                                />
                            </div>
                        </li>
                    </ul>
                    <p v-if="overview.total === 0" class="mt-4 text-sm text-[var(--nord3)]">Aún no hay alertas registradas.</p>
                </div>

                <div>
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-[var(--nord3)]">Requieren atención</h3>
                    <ul v-if="overview.urgentes?.length" class="divide-y divide-[var(--nord5)] rounded-xl border border-[var(--nord4)] overflow-hidden">
                        <li v-for="alerta in overview.urgentes" :key="alerta.id">
                            <component
                                :is="linkUrgentes ? Link : 'div'"
                                v-bind="linkUrgentes ? { href: `/alertas/${alerta.id}` } : {}"
                                class="flex flex-col gap-1 px-3 py-3"
                                :class="linkUrgentes
                                    ? 'transition-colors hover:bg-[var(--nord6)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-[var(--frost3)]'
                                    : ''"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-medium text-[var(--nord0)] truncate">{{ alerta.senal_label }}</span>
                                    <span
                                        class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                                        :class="alerta.estado === 'activa'
                                            ? 'bg-[var(--aurora-red)]/10 text-[var(--aurora-red)] border border-[var(--aurora-red)]/20'
                                            : 'bg-[var(--frost3)]/10 text-[var(--frost3)] border border-[var(--frost3)]/20'"
                                    >
                                        {{ alerta.estado_label }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between gap-2 text-[12px] text-[var(--nord3)]">
                                    <span v-if="revealCarnets" class="font-mono truncate">{{ alerta.paciente_carnet || '—' }}</span>
                                    <span v-else>Sin identificador personal</span>
                                    <span class="shrink-0">{{ formatDate(alerta.generada_en) }}</span>
                                </div>
                            </component>
                        </li>
                    </ul>
                    <p v-else class="rounded-xl border border-dashed border-[var(--nord4)] bg-[var(--surface-subtle)] px-4 py-6 text-center text-sm text-[var(--nord3)]">
                        {{ !revealCarnets ? 'El detalle individual está reservado al personal clínico autorizado.' : 'No hay alertas activas pendientes.' }}
                    </p>
                </div>
            </div>
        </section>

        <section
            aria-labelledby="dashboard-casos-title"
            class="bg-white rounded-2xl shadow-sm border border-[var(--nord4)] overflow-hidden"
        >
            <div class="px-6 py-4 border-b border-[var(--nord4)]">
                <h2 id="dashboard-casos-title" class="text-base font-semibold text-[var(--nord0)]">Tipos de caso por etiqueta</h2>
                <p class="mt-1 text-sm text-[var(--nord3)]">Conteo de estudiantes según etiquetas de motivo (agregado).</p>
            </div>
            <div class="p-6">
                <ul v-if="etiquetasConDatos.length" class="grid grid-cols-1 gap-3 sm:grid-cols-2" role="list">
                    <li v-for="(row, idx) in etiquetasConDatos" :key="row.key">
                        <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                            <span class="truncate text-[var(--nord1)]">{{ row.label }}</span>
                            <span class="shrink-0 font-semibold tabular-nums text-[var(--nord0)]">{{ countLabel(row.count) }}</span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-[var(--nord5)]">
                            <div
                                class="h-full rounded-full transition-all duration-700 ease-out"
                                :style="{
                                    width: `${(row.count / maxEtiquetaCount) * 100}%`,
                                    background: ETIQUETA_PALETTE[idx % ETIQUETA_PALETTE.length],
                                }"
                            />
                        </div>
                    </li>
                </ul>
                <p v-else class="text-sm text-[var(--nord3)]">{{ overview.por_etiqueta?.some((row) => row.count === null) ? 'Desglose protegido por privacidad.' : 'Aún no hay etiquetas registradas en expedientes.' }}</p>
            </div>
        </section>

        <section
            aria-labelledby="dashboard-tendencia-title"
            class="overflow-hidden rounded-2xl border border-[var(--nord4)] bg-white shadow-sm"
        >
            <div class="flex flex-col gap-4 border-b border-[var(--nord4)] px-6 py-5 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[var(--aurora-red)]">Prevención</p>
                    <h2 id="dashboard-tendencia-title" class="mt-1 text-lg font-semibold tracking-tight text-[var(--nord0)]">
                        Tendencia suicida
                    </h2>
                    <p class="mt-1 max-w-xl text-sm text-[var(--nord3)]">
                        Distribución estadística por facultad y carrera. Solo conteos agregados; sin carnets ni datos personales.
                    </p>
                </div>
                <div class="flex items-baseline gap-2 rounded-2xl border border-[var(--nord4)] bg-[var(--surface-subtle)] px-5 py-3">
                    <span class="text-3xl font-bold tabular-nums text-[var(--nord0)]">{{ countLabel(tendencia.total_alumnos) }}</span>
                    <span class="text-xs font-medium text-[var(--nord3)]">alumnos</span>
                </div>
            </div>

            <div class="grid grid-cols-1 divide-y divide-[var(--nord4)] lg:grid-cols-2 lg:divide-x lg:divide-y-0">
                <div class="p-6">
                    <div class="mb-4 flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-[var(--nord0)]">Por facultad</h3>
                        <span class="text-[11px] text-[var(--nord3)]">Conteo agregado</span>
                    </div>

                    <div
                        v-if="facultad3dChart.bars.length"
                        class="rounded-2xl border border-[var(--nord4)] bg-[var(--surface-subtle)] p-3 sm:p-4"
                    >
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-stretch">
                            <div class="min-w-0 flex-1">
                                <svg
                                    :viewBox="`0 0 ${facultad3dChart.chartW} ${facultad3dChart.chartH}`"
                                    class="mx-auto h-auto w-full max-w-lg"
                                    role="img"
                                    :aria-label="`Barras 3D: ${tendencia.total_alumnos} alumnos con tendencia suicida por facultad`"
                                >
                                    <rect
                                        :x="facultad3dChart.plotLeft - 8"
                                        :y="facultad3dChart.plotTop - 12"
                                        :width="facultad3dChart.plotW + facultad3dChart.depth + 20"
                                        :height="facultad3dChart.baselineY - facultad3dChart.plotTop + facultad3dChart.depth + 28"
                                        rx="10"
                                        fill="var(--surface)"
                                        opacity="0.9"
                                    />
                                    <g v-for="tick in facultad3dChart.ticks" :key="`tick-${tick.value}`">
                                        <line
                                            :x1="facultad3dChart.plotLeft"
                                            :y1="tick.y"
                                            :x2="facultad3dChart.gridRight"
                                            :y2="tick.y"
                                            stroke="var(--nord4)"
                                            stroke-width="1"
                                            stroke-dasharray="3 4"
                                        />
                                        <text
                                            :x="facultad3dChart.plotLeft - 8"
                                            :y="tick.y + 3"
                                            text-anchor="end"
                                            class="fill-[var(--nord3)]"
                                            style="font-size: 10px;"
                                        >{{ tick.value }}</text>
                                    </g>
                                    <polygon :points="facultad3dChart.floor.top" fill="var(--nord5)" opacity="0.85" />
                                    <polygon :points="facultad3dChart.floor.front" fill="var(--nord4)" opacity="0.55" />
                                    <g v-for="bar in facultad3dChart.bars" :key="bar.id">
                                        <polygon :points="bar.side" :fill="bar.colors.side">
                                            <title>{{ bar.label }}: {{ bar.count }}</title>
                                        </polygon>
                                        <polygon :points="bar.front" :fill="bar.colors.front">
                                            <title>{{ bar.label }}: {{ bar.count }}</title>
                                        </polygon>
                                        <polygon :points="bar.top" :fill="bar.colors.top">
                                            <title>{{ bar.label }}: {{ bar.count }}</title>
                                        </polygon>
                                        <text
                                            :x="bar.valueX"
                                            :y="bar.valueY"
                                            text-anchor="middle"
                                            class="fill-[var(--nord0)] font-semibold"
                                            style="font-size: 11px;"
                                        >{{ bar.count }}</text>
                                        <text
                                            :x="bar.labelX"
                                            :y="facultad3dChart.baselineY + 18"
                                            text-anchor="middle"
                                            class="fill-[var(--nord3)]"
                                            style="font-size: 9px;"
                                        >
                                            <tspan
                                                v-for="(line, li) in bar.labelLines"
                                                :key="li"
                                                :x="bar.labelX"
                                                :dy="li === 0 ? 0 : 11"
                                            >{{ line }}</tspan>
                                        </text>
                                    </g>
                                </svg>
                            </div>

                            <ul
                                class="flex flex-row flex-wrap gap-x-4 gap-y-2 sm:w-44 sm:flex-col sm:flex-nowrap sm:justify-center sm:border-l sm:border-[var(--nord4)] sm:pl-4"
                                role="list"
                                aria-label="Leyenda por facultad"
                            >
                                <li
                                    v-for="bar in facultad3dChart.bars"
                                    :key="`leg-${bar.id}`"
                                    class="flex min-w-0 items-start gap-2"
                                >
                                    <span
                                        class="mt-0.5 h-3 w-3 shrink-0 rounded-sm border border-black/10"
                                        :style="{ background: bar.colors.front }"
                                        aria-hidden="true"
                                    />
                                    <span class="min-w-0">
                                        <span class="block text-[12px] font-medium leading-snug text-[var(--nord0)]">{{ bar.short }}</span>
                                        <span class="block text-[11px] tabular-nums text-[var(--nord3)]">{{ bar.count }} alumnos</span>
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <p v-else class="rounded-xl border border-dashed border-[var(--nord4)] px-4 py-10 text-center text-sm text-[var(--nord3)]">
                        {{ tendencia.total_alumnos === null ? 'Desglose protegido por privacidad.' : 'Sin casos de tendencia suicida.' }}
                    </p>
                </div>

                <div class="p-6">
                    <div class="mb-5 flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-[var(--nord0)]">Por carrera</h3>
                        <span class="text-[11px] text-[var(--nord3)]">Ordenado por frecuencia</span>
                    </div>

                    <ul v-if="tendencia.por_carrera.length" class="max-h-[22rem] space-y-3 overflow-y-auto pr-1" role="list">
                        <li
                            v-for="row in tendencia.por_carrera"
                            :key="row.id"
                            class="rounded-xl px-1 py-2.5"
                        >
                            <div class="mb-2 flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium leading-snug text-[var(--nord0)]">{{ row.label }}</p>
                                    <p class="mt-0.5 text-[11px] leading-snug text-[var(--nord3)]">{{ row.facultad_label }}</p>
                                </div>
                                <span class="shrink-0 text-sm font-bold tabular-nums text-[var(--nord0)]">{{ countLabel(row.count) }}</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-[var(--nord5)]">
                                <div
                                    class="h-full rounded-full bg-[var(--frost3)] transition-all duration-700 ease-out"
                                    :style="{ width: `${row.count === null ? 0 : Math.max(8, (row.count / maxCarreraCount) * 100)}%` }"
                                />
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-[var(--nord3)]">Sin desglose por carrera.</p>
                </div>
            </div>
        </section>
    </div>
</template>
