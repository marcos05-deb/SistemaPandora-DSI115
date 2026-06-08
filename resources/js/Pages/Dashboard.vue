<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';

defineOptions({ layout: ClinicalLayout });

const page = usePage();

const canCreatePatient = computed(() => {
    const roles = page.props.auth?.user?.roles || [];
    return roles.includes('psychosocial_referent');
});

defineProps({
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
    }
});

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('es-ES', { year: 'numeric', month: 'short', day: 'numeric' });
}
</script>

<template>
    <Head title="Panel Clínico" />

    <div class="space-y-6">
        <!-- Header Section -->
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-[var(--nord0)] tracking-tight">Panel Principal</h1>
                <p class="text-sm text-[var(--nord3)] mt-1">Gestión de expedientes y pacientes clínicos.</p>
            </div>
            
            <div v-if="canCreatePatient">
                <Link 
                    href="/pacientes/create" 
                    class="px-4 py-2 bg-[var(--nord10)] hover:bg-[var(--nord9)] text-white font-medium rounded-lg shadow-sm transition-all duration-200 text-sm flex items-center gap-2 hover:shadow-md hover:-translate-y-0.5 active:translate-y-0"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Registrar Paciente
                </Link>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl shadow-sm border border-[var(--nord4)] p-6 hover:shadow-md transition-shadow duration-200">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-[var(--frost4)]/10 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-[var(--frost4)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-semibold text-[var(--nord3)] uppercase tracking-wider">Total Pacientes</h3>
                        <p class="text-3xl font-bold text-[var(--nord0)] mt-1">{{ stats.total }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-[var(--nord4)] p-6 hover:shadow-md transition-shadow duration-200">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-[var(--aurora-green)]/10 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-[var(--aurora-green)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-semibold text-[var(--nord3)] uppercase tracking-wider">Expedientes Activos</h3>
                        <p class="text-3xl font-bold text-[var(--aurora-green)] mt-1">{{ stats.activos }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Patients List / Empty State -->
        <div class="bg-white rounded-xl shadow-sm border border-[var(--nord4)] overflow-hidden">
            <div class="px-6 py-4 border-b border-[var(--nord4)] flex items-center justify-between">
                <h2 class="text-base font-semibold text-[var(--nord0)]">Pacientes Recientes</h2>
                <span v-if="pacientes.length > 0" class="text-[11px] text-[var(--nord3)] bg-[var(--nord6)] px-2.5 py-1 rounded-full font-medium">{{ pacientes.length }} registros</span>
            </div>
            
            <div v-if="pacientes.length === 0" class="p-12 text-center flex flex-col items-center">
                <div class="w-20 h-20 rounded-full bg-[var(--nord6)] flex items-center justify-center mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-[var(--nord3)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <h3 class="text-base font-medium text-[var(--nord0)] mb-1">No hay pacientes registrados</h3>
                <p class="text-sm text-[var(--nord3)] max-w-sm">
                    Aún no existen registros en tu área. 
                    <span v-if="canCreatePatient">Puedes comenzar registrando el primer paciente.</span>
                    <span v-else>Contacta al Referente Psicosocial para registrar pacientes.</span>
                </p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-[var(--nord6)] border-b border-[var(--nord4)] text-xs uppercase tracking-wider text-[var(--nord3)]">
                            <th class="p-4 font-semibold">Carnet</th>
                            <th class="p-4 font-semibold">Nombre del Paciente</th>
                            <th class="p-4 font-semibold">Registrado</th>
                            <th class="p-4 font-semibold text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--nord5)]">
                        <tr v-for="(paciente, idx) in pacientes" :key="paciente.carnet" class="hover:bg-[var(--nord6)] transition-colors duration-150 text-sm" :class="{ 'bg-[var(--nord6)]/30': idx % 2 === 1 }">
                            <td class="p-4 font-mono text-[var(--nord0)] font-medium">{{ paciente.carnet }}</td>
                            <td class="p-4 text-[var(--nord0)]">{{ paciente.nombre_completo }}</td>
                            <td class="p-4 text-[var(--nord3)]">{{ formatDate(paciente.created_at) }}</td>
                            <td class="p-4 text-right">
                                <Link :href="`/pacientes/${paciente.carnet}`" class="inline-flex items-center gap-1 text-[var(--nord10)] hover:text-[var(--nord9)] font-medium text-xs transition-colors">
                                    Ver Expediente
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
