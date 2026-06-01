<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';

defineOptions({ layout: ClinicalLayout });

const page = usePage();

// Computed property to check if user has the psychosocial_referent role
const canCreatePatient = computed(() => {
    const roles = page.props.auth?.user?.roles || [];
    return roles.includes('psychosocial_referent');
});

// Mock data or pass via props if available
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
                    class="px-4 py-2 bg-[var(--nord10)] hover:bg-[var(--nord9)] text-white font-medium rounded-lg shadow-sm transition-colors text-sm flex items-center gap-2"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Registrar Paciente
                </Link>
            </div>
        </div>

        <!-- Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl shadow-sm border border-[var(--nord4)] p-6">
                <h3 class="text-xs font-semibold text-[var(--nord3)] uppercase tracking-wider mb-2">Total Pacientes</h3>
                <p class="text-3xl font-bold text-[var(--nord0)]">{{ stats.total }}</p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-[var(--nord4)] p-6">
                <h3 class="text-xs font-semibold text-[var(--nord3)] uppercase tracking-wider mb-2">Expedientes Activos</h3>
                <p class="text-3xl font-bold text-[var(--nord0)]">{{ stats.activos }}</p>
            </div>
        </div>

        <!-- Patients List / Empty State -->
        <div class="bg-white rounded-xl shadow-sm border border-[var(--nord4)] overflow-hidden">
            <div class="p-6 border-b border-[var(--nord4)]">
                <h2 class="text-lg font-semibold text-[var(--nord0)]">Pacientes Recientes</h2>
            </div>
            
            <div v-if="pacientes.length === 0" class="p-12 text-center flex flex-col items-center">
                <div class="w-16 h-16 rounded-full bg-[var(--nord6)] flex items-center justify-center mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[var(--nord3)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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

            <div v-else>
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-[var(--nord6)] border-b border-[var(--nord4)] text-xs uppercase tracking-wider text-[var(--nord3)]">
                            <th class="p-4 font-semibold">Carnet</th>
                            <th class="p-4 font-semibold">Nombre del Paciente</th>
                            <th class="p-4 font-semibold">Registrado</th>
                            <th class="p-4 font-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="paciente in pacientes" :key="paciente.carnet" class="border-b border-[var(--nord4)] hover:bg-[var(--nord6)] transition-colors text-sm">
                            <td class="p-4 font-mono text-[var(--nord0)]">{{ paciente.carnet }}</td>
                            <td class="p-4 text-[var(--nord0)] font-medium">{{ paciente.nombre_completo }}</td>
                            <td class="p-4 text-[var(--nord3)]">{{ paciente.created_at }}</td>
                            <td class="p-4">
                                <Link :href="`/pacientes/${paciente.carnet}`" class="text-[var(--nord10)] hover:underline font-medium">
                                    Ver Expediente
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
