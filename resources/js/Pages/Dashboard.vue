<script setup>
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import DerivacionModal from '@/Components/Expediente/DerivacionModal.vue';
import { formatLocalDate } from '@/utils/dates';

defineOptions({ layout: ClinicalLayout });

const isDerivacionModalOpen = ref(false);
const pacienteSeleccionado = ref(null);

const openDerivacionModal = (paciente) => {
    pacienteSeleccionado.value = paciente;
    isDerivacionModalOpen.value = true;
};

const page = usePage();

const canCreatePatient = computed(() => {
    const roles = page.props.auth?.user?.roles || [];
    return roles.includes('psychosocial_referent');
});

const props = defineProps({
    pacientes: {
        type: Array,
        default: () => []
    },
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            activos: 0
        })
    },
    areasDisponibles: {
        type: Array,
        default: () => []
    },
    alertOverview: {
        type: Object,
        default: () => ({
            visible: false,
            total: 0,
            abiertas: 0,
            por_estado: [],
            por_senal: [],
            urgentes: [],
        })
    }
});

const ESTADO_COLORS = {
    activa: 'var(--aurora-red)',
    en_seguimiento: 'var(--frost3)',
    cerrada: 'var(--aurora-green)',
    descartada: 'var(--nord3)',
};

const SENAL_COLORS = {
    crisis_psicologica: 'var(--aurora-orange, #D08770)',
    ausencia_cita: 'var(--frost4)',
    tendencia_suicida: 'var(--aurora-red)',
};

const getAreaName = (areaId) => {
    const area = props.areasDisponibles.find(a => a.id === areaId);
    return area ? area.nombre : 'Área';
};

function formatDate(dateStr) {
    return formatLocalDate(dateStr, { year: 'numeric', month: 'short', day: 'numeric' }, 'es-ES', '-');
}

const isExpedienteClosed = (paciente) => {
    if (!paciente.expedientes || paciente.expedientes.length === 0) return false;
    return paciente.expedientes.every(exp => exp.estado === 'cerrado');
};

const showAlertCharts = computed(() => Boolean(props.alertOverview?.visible));

const maxSenalCount = computed(() => {
    const counts = (props.alertOverview?.por_senal || []).map((row) => row.count);
    return Math.max(1, ...counts, 0);
});

/** Arcos SVG para donut de estados (circunferencia ~ 2πr con r=40). */
const donutSegments = computed(() => {
    const rows = props.alertOverview?.por_estado || [];
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
    <Head title="Panel Clínico" />

    <div class="space-y-6">
        <!-- Header Section -->
        <div class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-start">
            <div class="min-w-0">
                <h1 class="text-xl sm:text-2xl font-bold text-[var(--nord0)] tracking-tight">Panel Principal</h1>
                <p class="text-sm text-[var(--nord3)] mt-1">Gestión de expedientes y pacientes clínicos.</p>
            </div>

            <div v-if="canCreatePatient" class="shrink-0">
                <Link
                    href="/pacientes/create"
                    class="group relative inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white rounded-xl shadow-lg transition-all duration-200 hover:shadow-xl hover:-translate-y-0.5 active:translate-y-0 overflow-hidden"
                    style="background: linear-gradient(135deg, var(--frost4) 0%, var(--nord9) 100%);"
                >
                    <span class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-200 rounded-xl"></span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    Registrar Paciente
                </Link>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Total Pacientes -->
            <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border border-[var(--nord4)] p-6 group hover:shadow-md transition-all duration-300">
                <div class="absolute top-0 right-0 w-32 h-32 rounded-full opacity-[0.06] -translate-y-8 translate-x-8 transition-transform duration-500 group-hover:translate-x-4 group-hover:-translate-y-4"
                    style="background: radial-gradient(circle, var(--frost4), transparent);">
                </div>
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center shrink-0 shadow-sm"
                        style="background: linear-gradient(135deg, #5E81AC22 0%, #5E81AC33 100%); border: 1px solid #5E81AC22;">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-[var(--frost4)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-[var(--nord3)] uppercase tracking-wider mb-1">Total Pacientes</p>
                        <p class="text-4xl font-bold text-[var(--nord0)] tabular-nums">{{ stats.total }}</p>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-[var(--nord4)]/50 flex items-center gap-1.5">
                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-[var(--frost4)] bg-[var(--frost4)]/8 px-2 py-0.5 rounded-full">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        Expedientes totales registrados
                    </span>
                </div>
            </div>

            <!-- Activos -->
            <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border border-[var(--nord4)] p-6 group hover:shadow-md transition-all duration-300">
                <div class="absolute top-0 right-0 w-32 h-32 rounded-full opacity-[0.06] -translate-y-8 translate-x-8 transition-transform duration-500 group-hover:translate-x-4 group-hover:-translate-y-4"
                    style="background: radial-gradient(circle, var(--aurora-green), transparent);">
                </div>
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center shrink-0 shadow-sm"
                        style="background: linear-gradient(135deg, #A3BE8C22 0%, #A3BE8C33 100%); border: 1px solid #A3BE8C22;">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-[var(--aurora-green)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-[var(--nord3)] uppercase tracking-wider mb-1">Expedientes Activos</p>
                        <p class="text-4xl font-bold text-[var(--aurora-green)] tabular-nums">{{ stats.activos }}</p>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-[var(--nord4)]/50 flex items-center gap-2">
                    <div class="flex-1 h-1.5 bg-[var(--nord5)] rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-700"
                            style="background: linear-gradient(90deg, var(--aurora-green), #7DAE63);"
                            :style="{ width: stats.total > 0 ? (stats.activos / stats.total * 100) + '%' : '0%' }">
                        </div>
                    </div>
                    <span class="text-[11px] font-semibold text-[var(--aurora-green)] shrink-0">
                        {{ stats.total > 0 ? Math.round(stats.activos / stats.total * 100) : 0 }}%
                    </span>
                </div>
            </div>
        </div>

        <!-- Alertas preventivas — resumen visual (HU-16/17) -->
        <section
            v-if="showAlertCharts"
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
                        Resumen de tu área · {{ alertOverview.abiertas }} abiertas de {{ alertOverview.total }} registradas
                    </p>
                </div>
                <Link
                    href="/alertas"
                    class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-[var(--nord10)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--nord9)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)]"
                >
                    Ver bandeja
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" /></svg>
                </Link>
            </div>

            <div class="grid grid-cols-1 gap-6 p-6 lg:grid-cols-3">
                <!-- Donut por estado -->
                <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-start lg:flex-col lg:items-center">
                    <div class="relative h-40 w-40 shrink-0">
                        <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90" role="img" :aria-label="`Distribución por estado: ${alertOverview.total} alertas`">
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
                                stroke-linecap="butt"
                                :stroke-dasharray="segment.dasharray"
                                :stroke-dashoffset="segment.dashoffset"
                                class="transition-all duration-700 ease-out"
                            />
                        </svg>
                        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center rotate-0">
                            <span class="text-3xl font-bold tabular-nums text-[var(--nord0)]">{{ alertOverview.total }}</span>
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nord3)]">Total</span>
                        </div>
                    </div>
                    <ul class="w-full space-y-2 text-sm">
                        <li
                            v-for="row in alertOverview.por_estado"
                            :key="row.key"
                            class="flex items-center justify-between gap-3"
                        >
                            <span class="inline-flex items-center gap-2 text-[var(--nord1)]">
                                <span class="h-2.5 w-2.5 rounded-full shrink-0" :style="{ background: ESTADO_COLORS[row.key] || 'var(--nord10)' }" aria-hidden="true" />
                                {{ row.label }}
                            </span>
                            <span class="font-semibold tabular-nums text-[var(--nord0)]">{{ row.count }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Barras por señal -->
                <div class="lg:col-span-1">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-[var(--nord3)]">Por señal</h3>
                    <ul class="space-y-3" role="list">
                        <li v-for="row in alertOverview.por_senal" :key="row.key">
                            <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                                <span class="truncate text-[var(--nord1)]">{{ row.label }}</span>
                                <span class="shrink-0 font-semibold tabular-nums text-[var(--nord0)]">{{ row.count }}</span>
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
                    <p v-if="alertOverview.total === 0" class="mt-4 text-sm text-[var(--nord3)]">Aún no hay alertas en tu área.</p>
                </div>

                <!-- Urgentes -->
                <div>
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-[var(--nord3)]">Requieren atención</h3>
                    <ul v-if="alertOverview.urgentes.length" class="divide-y divide-[var(--nord5)] rounded-xl border border-[var(--nord4)] overflow-hidden">
                        <li v-for="alerta in alertOverview.urgentes" :key="alerta.id">
                            <Link
                                :href="`/alertas/${alerta.id}`"
                                class="flex flex-col gap-1 px-3 py-3 transition-colors hover:bg-[var(--nord6)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-[var(--frost3)]"
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
                                    <span class="font-mono">{{ alerta.paciente_carnet || '—' }}</span>
                                    <span>{{ formatDate(alerta.generada_en) }}</span>
                                </div>
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="rounded-xl border border-dashed border-[var(--nord4)] bg-[var(--surface-subtle)] px-4 py-6 text-center text-sm text-[var(--nord3)]">
                        No hay alertas activas pendientes.
                    </p>
                </div>
            </div>
        </section>

        <!-- Patients List / Empty State -->
        <div class="bg-white rounded-2xl shadow-sm border border-[var(--nord4)] overflow-hidden">
            <div class="px-6 py-4 border-b border-[var(--nord4)] flex items-center justify-between">
                <h2 class="text-base font-semibold text-[var(--nord0)] flex items-center gap-2">
                    <svg class="h-4 w-4 text-[var(--nord3)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Pacientes Recientes
                </h2>
                <span v-if="pacientes.length > 0" class="text-[11px] text-[var(--frost4)] bg-[var(--frost4)]/8 border border-[var(--frost4)]/20 px-2.5 py-1 rounded-full font-semibold">
                    {{ pacientes.length }} registros
                </span>
            </div>

            <div v-if="pacientes.length === 0">
                <EmptyState icon="users" title="No hay pacientes registrados" :description="canCreatePatient ? 'Aún no existen registros en tu área. Puedes comenzar registrando el primer paciente.' : 'Contacta al Referente Psicosocial para registrar pacientes.'">
                    <Link v-if="canCreatePatient" href="/pacientes/create" class="inline-flex items-center gap-1.5 px-4 py-2 bg-[var(--nord10)] hover:bg-[var(--nord9)] text-white font-medium rounded-lg shadow-sm transition-all duration-200 text-sm hover:shadow-md">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        Registrar Paciente
                    </Link>
                </EmptyState>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-[var(--surface-header)] border-b border-[var(--nord4)] text-[11px] uppercase tracking-wider text-[var(--nord3)]">
                            <th class="px-6 py-3 font-semibold">Paciente</th>
                            <th class="px-6 py-3 font-semibold">Carnet</th>
                            <th class="px-6 py-3 font-semibold hidden sm:table-cell">Registrado</th>
                            <th class="px-6 py-3 font-semibold text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--nord5)]">
                        <tr v-for="(paciente, idx) in pacientes" :key="paciente.carnet"
                            class="group transition-colors duration-150 text-sm"
                            :class="[isExpedienteClosed(paciente) ? 'hover:bg-[var(--nord6)] opacity-75' : 'hover:bg-[var(--nord6)]']">
                            <td class="px-6 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-[12px] font-bold text-white shrink-0 shadow-sm"
                                        :class="isExpedienteClosed(paciente) ? 'grayscale opacity-70' : ''"
                                        :style="`background: linear-gradient(135deg, hsl(${(idx * 47 + 200) % 360}, 55%, 55%), hsl(${(idx * 47 + 230) % 360}, 50%, 45%));`">
                                        {{ (paciente.nombre_completo || '?').charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-medium text-[var(--nord0)] truncate max-w-[200px]" :class="{ 'text-[var(--nord3)] line-through decoration-[var(--nord4)]': isExpedienteClosed(paciente) }">
                                            {{ paciente.nombre_completo }}
                                        </span>
                                        <span v-if="isExpedienteClosed(paciente)" class="text-[9px] font-bold text-[var(--aurora-red)] bg-[var(--aurora-red)]/10 px-1.5 py-0.5 rounded w-max mt-0.5 border border-[var(--aurora-red)]/20 uppercase tracking-widest">
                                            Cerrado
                                        </span>
                                        <span v-else class="text-[9px] font-bold text-[var(--aurora-green)] bg-[var(--aurora-green)]/10 px-1.5 py-0.5 rounded w-max mt-0.5 border border-[var(--aurora-green)]/20 uppercase tracking-widest">
                                            Activo
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-3.5 font-mono text-[var(--nord3)] text-[12px] font-medium">{{ paciente.carnet }}</td>
                            <td class="px-6 py-3.5 text-[var(--nord3)] hidden sm:table-cell text-[12px]">{{ formatDate(paciente.created_at) }}</td>
                            <td class="px-6 py-3.5 text-right flex justify-end gap-2 items-center">
                                <template v-if="canCreatePatient">
                                    <template v-if="paciente.expedientes && paciente.expedientes.length > 0">
                                        <span v-if="isExpedienteClosed(paciente)" class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-[var(--nord3)] bg-[var(--surface-subtle)] px-3 py-1.5 rounded-lg border border-[var(--nord4)]">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-[var(--aurora-red)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                            Finalizado
                                        </span>
                                        <span v-else class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-[var(--nord3)] bg-[var(--surface-subtle)] px-3 py-1.5 rounded-lg border border-[var(--nord4)]">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-[var(--aurora-green)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                            </svg>
                                            Referido al {{ getAreaName(paciente.expedientes[0].area_id) }}
                                        </span>
                                    </template>
                                    <button v-else @click="openDerivacionModal(paciente)"
                                        class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-[var(--nord8)] hover:text-white bg-[var(--nord8)]/10 hover:bg-[var(--nord8)] px-3 py-1.5 rounded-lg transition-all duration-150">
                                        Derivar a Área
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                        </svg>
                                    </button>
                                </template>
                                <Link :href="`/pacientes/${paciente.carnet}`"
                                    class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-[var(--frost4)] hover:text-[var(--nord10)] bg-[var(--frost4)]/8 hover:bg-[var(--frost4)]/15 px-3 py-1.5 rounded-lg transition-all duration-150">
                                    Ver Expediente
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                    </svg>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <DerivacionModal
            :show="isDerivacionModalOpen"
            :paciente="pacienteSeleccionado"
            :areas="areasDisponibles"
            @close="isDerivacionModalOpen = false"
        />
    </div>
</template>
