<script setup>
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import DerivacionModal from '@/Components/Expediente/DerivacionModal.vue';

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
    }
});

const getAreaName = (areaId) => {
    const area = props.areasDisponibles.find(a => a.id === areaId);
    return area ? area.nombre : 'Área';
};

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('es-ES', { year: 'numeric', month: 'short', day: 'numeric' });
}

const isExpedienteClosed = (paciente) => {
    if (!paciente.expedientes || paciente.expedientes.length === 0) return false;
    return paciente.expedientes.every(exp => exp.estado === 'cerrado');
};
</script>

<template>
    <Head title="Panel Clínico" />

    <div class="space-y-6">
        <!-- Header Section -->
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-2xl font-bold text-[var(--nord0)] tracking-tight">Panel Principal</h1>
                <p class="text-sm text-[var(--nord3)] mt-1">Gestión de expedientes y pacientes clínicos.</p>
            </div>

            <div v-if="canCreatePatient">
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
