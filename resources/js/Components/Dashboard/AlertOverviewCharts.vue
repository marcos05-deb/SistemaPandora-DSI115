<script setup>
import { computed } from 'vue';
import ProtectedValue from '@/Components/UI/ProtectedValue.vue';
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

function formatDate(dateStr) {
    return formatLocalDate(dateStr, { year: 'numeric', month: 'short', day: 'numeric' }, 'es-SV', '-');
}

const countLabel = (count) => count === null ? 'Dato protegido' : count;

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

const facultadesConDatos = computed(() =>
    (tendencia.value.por_facultad || []).filter((row) => row.count !== null)
);

const maxFacultadCount = computed(() => {
    const counts = facultadesConDatos.value.map((row) => row.count);
    return Math.max(1, ...counts, 0);
});

const shortFacultadLabel = (label) => {
    const cleaned = (label || '')
        .replace(/^Facultad Multidisciplinaria (de |del |de la )?/i, '')
        .replace(/^Facultad (de |del |de la )?/i, '')
        .trim();
    return cleaned || label || '—';
};

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
                        Resumen {{ scopeLabel }} · <ProtectedValue v-if="overview.total === null" /><template v-else>{{ overview.abiertas }} abiertas de {{ overview.total }} registradas</template>
                        <span v-if="!revealCarnets" class="text-[var(--nord3)]"> · sin identificadores personales</span>
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
                            <span class="text-2xl font-bold tabular-nums text-[var(--nord0)]"><ProtectedValue v-if="overview.total === null" compact /><template v-else>{{ countLabel(overview.total) }}</template></span>
                            <span class="text-[10px] uppercase tracking-wide text-[var(--nord3)]">Total</span>
                        </div>
                    </div>
                    <ul class="w-full space-y-1.5 text-sm" role="list">
                        <li v-for="row in overview.por_estado" :key="row.key" class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2 text-[var(--nord1)]">
                                <span class="h-2.5 w-2.5 rounded-full shrink-0" :style="{ background: ESTADO_COLORS[row.key] || 'var(--nord10)' }" />
                                {{ row.label }}
                            </span>
                            <span class="font-semibold tabular-nums text-[var(--nord0)]"><ProtectedValue v-if="row.count === null" /><template v-else>{{ countLabel(row.count) }}</template></span>
                        </li>
                    </ul>
                </div>

                <div>
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-[var(--nord3)]">Por señal</h3>
                    <ul class="space-y-3" role="list">
                        <li v-for="row in overview.por_senal" :key="row.key">
                            <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                                <span class="truncate text-[var(--nord1)]">{{ row.label }}</span>
                                <span class="shrink-0 font-semibold tabular-nums text-[var(--nord0)]"><ProtectedValue v-if="row.count === null" /><template v-else>{{ countLabel(row.count) }}</template></span>
                            </div>
                            <div v-if="row.count !== null" class="h-2 overflow-hidden rounded-full bg-[var(--nord5)]" role="presentation">
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
                            <span class="shrink-0 font-semibold tabular-nums text-[var(--nord0)]"><ProtectedValue v-if="row.count === null" /><template v-else>{{ countLabel(row.count) }}</template></span>
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
                <div v-else class="text-sm text-[var(--nord3)]"><ProtectedValue v-if="overview.por_etiqueta?.some((row) => row.count === null)" /><p v-else>Aún no hay etiquetas registradas en expedientes.</p></div>
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
                        Distribución estadística por facultad y carrera. Solo conteos agregados; no se incluyen identificadores personales ni notas clínicas.
                    </p>
                </div>
                <div class="flex items-baseline gap-2 rounded-2xl border border-[var(--nord4)] bg-[var(--surface-subtle)] px-5 py-3">
                    <span class="text-3xl font-bold tabular-nums text-[var(--nord0)]"><ProtectedValue v-if="tendencia.total_alumnos === null" /><template v-else>{{ countLabel(tendencia.total_alumnos) }}</template></span>
                    <span class="text-xs font-medium text-[var(--nord3)]">alumnos</span>
                </div>
            </div>

            <div class="grid grid-cols-1 divide-y divide-[var(--nord4)] lg:grid-cols-2 lg:divide-x lg:divide-y-0">
                <div class="p-6">
                    <div class="mb-4 flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-[var(--nord0)]">Por facultad</h3>
                        <span class="text-[11px] text-[var(--nord3)]">Conteo agregado</span>
                    </div>

                    <ul v-if="facultadesConDatos.length" class="max-h-[22rem] space-y-3 overflow-y-auto pr-1" role="list">
                        <li v-for="row in facultadesConDatos" :key="row.id" class="rounded-xl px-1 py-2.5">
                            <div class="mb-2 flex items-start justify-between gap-3">
                                <p class="min-w-0 text-sm font-medium leading-snug text-[var(--nord0)]">{{ shortFacultadLabel(row.label) }}</p>
                                <span class="shrink-0 text-sm font-bold tabular-nums text-[var(--nord0)]">{{ row.count }}</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-[var(--nord5)]">
                                <div
                                    class="h-full rounded-full bg-[var(--aurora-red)] transition-all duration-700 ease-out"
                                    :style="{ width: `${Math.max(4, (row.count / maxFacultadCount) * 100)}%` }"
                                />
                            </div>
                        </li>
                    </ul>
                    <p v-else class="rounded-xl border border-dashed border-[var(--nord4)] px-4 py-10 text-center text-sm text-[var(--nord3)]">
                        Sin casos de tendencia suicida.
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
                                <span class="shrink-0 text-sm font-bold tabular-nums text-[var(--nord0)]"><ProtectedValue v-if="row.count === null" /><template v-else>{{ countLabel(row.count) }}</template></span>
                            </div>
                            <div v-if="row.count !== null" class="h-1.5 overflow-hidden rounded-full bg-[var(--nord5)]">
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
