<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';

defineOptions({ layout: ClinicalLayout });

const props = defineProps({
    paciente: {
        type: Object,
        required: true
    }
});

// Format date helper
const formatDate = (dateString) => {
    if (!dateString) return 'No registrada';
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('es-ES', options);
};
</script>

<template>
    <Head :title="`Expediente - ${paciente.carnet}`" />

    <div class="max-w-5xl mx-auto space-y-6 pb-10">
        <!-- Header Section -->
        <div class="flex justify-between items-center bg-white py-[14px] px-[18px] shadow-sm border border-[var(--nord4)] rounded-[10px]">
            <div>
                <h1 class="text-[16px] font-medium text-[var(--nord0)] tracking-tight flex items-center gap-2">
                    <Link href="/dashboard" class="text-[var(--nord3)] hover:text-[var(--nord0)] transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    Datos del Paciente
                </h1>
                <p class="text-[12px] text-[var(--nord3)] mt-0.5 ml-7">
                    Información personal y contactos de emergencia. El expediente clínico aún no está habilitado.
                </p>
            </div>
            <div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-[12px] font-medium bg-[var(--nord6)] text-[var(--nord3)] border border-[var(--nord4)]">
                    UUID: {{ paciente.codigo.substring(0,8) }}...
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Columna Izquierda: Información Principal -->
            <div class="md:col-span-2 space-y-6">
                <!-- Tarjeta de Perfil -->
                <div class="bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] overflow-hidden">
                    <div class="px-6 py-4 border-b border-[var(--nord4)] bg-[var(--nord6)] flex justify-between items-center">
                        <h2 class="text-[14px] font-medium text-[var(--nord0)] flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--nord10)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Perfil del Estudiante
                        </h2>
                    </div>
                    
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-y-6 gap-x-8">
                        <div>
                            <p class="text-[11px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-1">Nombre Completo</p>
                            <p class="text-[14px] text-[var(--nord0)] font-medium">{{ paciente.nombre_completo }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-1">Carnet Estudiantil</p>
                            <p class="text-[14px] text-[var(--nord0)] font-mono">{{ paciente.carnet }}</p>
                        </div>
                        <div v-if="paciente.motivo_consulta" class="md:col-span-2">
                            <p class="text-[11px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-1">Motivo de Consulta</p>
                            <p class="text-[14px] text-[var(--nord0)] whitespace-pre-line">{{ paciente.motivo_consulta }}</p>
                        </div>
                        <div class="md:col-span-2">
                            <p class="text-[11px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-1">Dirección de Residencia</p>
                            <p class="text-[14px] text-[var(--nord0)]">{{ paciente.direccion }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-1">Fecha de Nacimiento</p>
                            <p class="text-[14px] text-[var(--nord0)]">{{ formatDate(paciente.fecha_nacimiento) }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-1">Sexo / Estado Civil</p>
                            <p class="text-[14px] text-[var(--nord0)]">{{ paciente.sexo }} — {{ paciente.estado_civil }}</p>
                        </div>
                        <div v-if="paciente.profesion_ocupacion">
                            <p class="text-[11px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-1">Ocupación Adicional</p>
                            <p class="text-[14px] text-[var(--nord0)]">{{ paciente.profesion_ocupacion }}</p>
                        </div>
                    </div>
                </div>

                <!-- Tarjeta Académica y de Referencia -->
                <div class="bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] overflow-hidden">
                    <div class="px-6 py-4 border-b border-[var(--nord4)] bg-[var(--nord6)] flex justify-between items-center">
                        <h2 class="text-[14px] font-medium text-[var(--nord0)] flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--nord8)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                            Datos Universitarios e Ingreso
                        </h2>
                    </div>
                    
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-y-6 gap-x-8">
                        <div class="md:col-span-2">
                            <p class="text-[11px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-1">Facultad y Carrera</p>
                            <p class="text-[14px] text-[var(--nord0)] font-medium">{{ paciente.carrera?.facultad?.nombre || 'No asignada' }}</p>
                            <p class="text-[13px] text-[var(--nord3)] mt-0.5">{{ paciente.carrera?.nombre || 'No asignada' }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-1">Fecha Primera Consulta</p>
                            <p class="text-[14px] text-[var(--nord0)]">{{ formatDate(paciente.fecha_primera_consulta) }}</p>
                        </div>
                        <div v-if="paciente.referido_por">
                            <p class="text-[11px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-1">Referido Por</p>
                            <p class="text-[14px] text-[var(--nord0)]">{{ paciente.referido_por }}</p>
                        </div>
                        <div v-if="paciente.llevado_por">
                            <p class="text-[11px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-1">Acompañado Por</p>
                            <p class="text-[14px] text-[var(--nord0)]">{{ paciente.llevado_por }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha: Familiares -->
            <div class="space-y-6">
                <div class="bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] overflow-hidden">
                    <div class="px-6 py-4 border-b border-[var(--nord4)] bg-[var(--nord6)]">
                        <h2 class="text-[14px] font-medium text-[var(--nord0)] flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--aurora-orange)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            Familiares / Responsables
                        </h2>
                    </div>

                    <div class="p-0">
                        <div v-if="paciente.contactos && paciente.contactos.length > 0">
                            <div v-for="contacto in paciente.contactos" :key="contacto.id" class="p-6 border-b border-[var(--nord4)] last:border-0 relative">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-[13px] font-bold text-[var(--nord0)]">{{ contacto.parentesco }}</h3>
                                        <p class="text-[14px] text-[var(--nord3)] mt-1 font-medium">{{ contacto.nombre_completo }}</p>
                                    </div>
                                    <div v-if="contacto.es_responsable" class="flex items-center gap-1 text-[10px] border border-[var(--aurora-orange)] text-[var(--aurora-orange)] px-2 py-0.5 rounded font-medium">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-[10px] w-[10px]" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        Responsable Principal
                                    </div>
                                </div>
                                
                                <div class="mt-3 space-y-2">
                                    <div class="flex items-center gap-2 text-[13px] text-[var(--nord3)]" v-if="contacto.telefono_personal">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--nord4)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                        {{ contacto.telefono_personal }}
                                    </div>
                                    <div class="flex items-start gap-2 text-[13px] text-[var(--nord3)]" v-if="contacto.direccion">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--nord4)] mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span class="flex-1">{{ contacto.direccion }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="p-6 text-center text-[13px] text-[var(--nord3)]">
                            No hay familiares registrados.
                        </div>
                    </div>
                </div>

                <!-- Caja de Expediente (Bloqueado) -->
                <div class="bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] overflow-hidden">
                    <div class="p-6 text-center">
                        <div class="w-12 h-12 bg-[var(--nord6)] rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-[var(--nord4)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <h3 class="text-[14px] font-medium text-[var(--nord0)] mb-1">Módulo de Expedientes</h3>
                        <p class="text-[12px] text-[var(--nord3)] mb-4">
                            La funcionalidad de registro de sesiones y evoluciones clínicas será habilitada en el próximo sprint.
                        </p>
                        <button disabled class="w-full py-2 bg-[var(--nord6)] text-[var(--nord4)] text-[13px] font-medium rounded-[6px] cursor-not-allowed border border-[var(--nord4)]">
                            Crear Nueva Sesión
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>
