<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';
import Breadcrumbs from '@/Components/UI/Breadcrumbs.vue';
import DerivacionModal from '@/Components/Expediente/DerivacionModal.vue';
import CierreExpedienteModal from '@/Components/Expediente/CierreExpedienteModal.vue';
import ActualizarExpedienteModal from '@/Components/Expediente/ActualizarExpedienteModal.vue';
import AgendarCitaModal from '@/Components/Expediente/AgendarCitaModal.vue';
import GestionarCitaModal from '@/Components/Expediente/GestionarCitaModal.vue';
import { computed, ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

defineOptions({ layout: ClinicalLayout });

const props = defineProps({
    paciente: { type: Object, required: true },
    hasAnyExpediente: { type: Boolean, default: false },
    areasDisponibles: { type: Array, default: () => [] },
    expedientesActivos: { type: Array, default: () => [] },
    expedienteSeleccionadoId: { type: String, default: null },
    citasPendientes: { type: Array, default: () => [] },
    consultaActivaId: { type: String, default: null },
    expedienteCerrado: { type: Object, default: null },
    alertaPreventiva: { type: Object, default: () => ({ activa: false, total: 0 }) },
    can: { type: Object, default: () => ({}) }
});

const page = usePage();

const canSeeFullUuid = computed(() => {
    const roles = page.props.auth?.user?.roles || [];
    return roles.includes('sysadmin') || roles.includes('area_coordinator');
});

const expedienteActivo = computed(() => {
    if (!props.paciente.expedientes || !Array.isArray(props.paciente.expedientes)) return null;
    if (props.expedienteSeleccionadoId) {
        return props.paciente.expedientes.find(e => e.id === props.expedienteSeleccionadoId) || null;
    }
    return props.paciente.expedientes.find(e => e.estado !== 'cerrado') || null;
});

const seleccionarExpediente = (expedienteId) => {
    router.get(`/pacientes/${props.paciente.carnet}`, {
        expediente_id: expedienteId,
    }, {
        preserveScroll: true,
        preserveState: false,
    });
};

const formatDate = (dateString) => {
    if (!dateString) return 'No registrada';
    return new Date(dateString).toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' });
};

const formatDateTime = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleString('es-ES', {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
    });
};

const getAreaName = (areaId) => {
    const area = props.areasDisponibles.find(a => a.id === areaId);
    return area ? area.nombre : 'Área';
};

const isDerivacionModalOpen = ref(false);
const isCierreModalOpen = ref(false);
const isActualizarModalOpen = ref(false);
const isCitaModalOpen = ref(false);
const isGestionarCitaModalOpen = ref(false);
const gestionarCitaMode = ref('reprogramar');
const selectedCita = ref(null);

const openGestionarCitaModal = (cita, mode) => {
    selectedCita.value = cita;
    gestionarCitaMode.value = mode;
    isGestionarCitaModalOpen.value = true;
};

const marcarAsistencia = (citaId, estado) => {
    if (!props.can?.updateCita || !expedienteActivo.value) return;
    const cita = props.citasPendientes.find(c => c.id === citaId);
    if (cita && !puedeRegistrarAsistencia(cita)) return;

    router.patch(`/expedientes/${expedienteActivo.value.id}/citas/${citaId}/asistencia`, {
        estado: estado
    }, {
        preserveScroll: true
    });
};

const puedeRegistrarAsistencia = (cita) => {
    if (!cita?.fecha_hora) return false;
    return new Date(cita.fecha_hora).getTime() <= Date.now();
};

onMounted(() => {
    if (page.props.flash?.prompt_cita_expediente_id && expedienteActivo.value?.id === page.props.flash.prompt_cita_expediente_id) {
        if (props.can?.assignCita) {
            isCitaModalOpen.value = true;
        }
    }
});
</script>

<template>
    <Head :title="`Expediente - ${paciente.carnet}`" />

    <div class="max-w-5xl mx-auto space-y-6 pb-10">
        <!-- Header Section -->
        <Breadcrumbs :items="[
            { label: 'Panel Clínico', href: '/dashboard' },
            { label: 'Pacientes', href: '/pacientes' },
            { label: paciente.carnet },
        ]" />
        <div
            v-if="alertaPreventiva?.activa"
            class="mb-4 rounded-[10px] border border-[var(--aurora-orange)]/40 bg-[var(--aurora-orange)]/10 px-4 py-3 text-[13px] text-[var(--nord0)]"
            role="alert"
        >
            <p class="font-medium">{{ alertaPreventiva.mensaje }}</p>
            <p class="text-[12px] text-[var(--nord3)] mt-1">
                Umbral: {{ alertaPreventiva.umbral }} ausencias en {{ alertaPreventiva.ventana_dias }} días.
            </p>
        </div>
        <div
            v-if="expedientesActivos.length > 1"
            class="mb-4 rounded-[10px] border border-[var(--nord4)] bg-white px-4 py-3"
        >
            <p class="text-[12px] font-medium text-[var(--nord0)] mb-2">Expediente / área de trabajo</p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="exp in expedientesActivos"
                    :key="exp.id"
                    type="button"
                    class="px-3 py-1.5 text-[12px] font-semibold rounded-lg border transition-colors"
                    :class="exp.id === expedienteSeleccionadoId
                        ? 'bg-[var(--nord8)] text-white border-[var(--nord8)]'
                        : 'border-[var(--nord4)] text-[var(--nord3)] hover:border-[var(--nord8)]'"
                    @click="seleccionarExpediente(exp.id)"
                >
                    {{ exp.area_nombre }}
                </button>
            </div>
        </div>
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
                <p class="text-[12px] text-[var(--nord3)] mt-0.5 ml-7">Información personal y contactos de emergencia.</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-medium bg-[var(--surface-header)] text-[var(--nord3)] border border-[var(--nord4)]" :class="{ 'font-mono': canSeeFullUuid }">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4" />
                    </svg>
                    <template v-if="canSeeFullUuid">{{ paciente.codigo }}</template>
                    <template v-else>{{ paciente.codigo.substring(0,8) }}...</template>
                </span>
                
                <template v-if="can.derivar">
                    <template v-if="paciente.expedientes && paciente.expedientes.length > 0">
                        <span class="px-3 py-1.5 bg-[var(--surface-subtle)] text-[var(--nord3)] text-[11px] font-semibold rounded-lg border border-[var(--nord4)] inline-flex items-center gap-1.5 shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-[var(--aurora-green)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                            Referido al {{ getAreaName(paciente.expedientes[0].area_id) }}
                        </span>
                    </template>
                    <button v-else 
                        @click="isDerivacionModalOpen = true"
                        class="px-3 py-1.5 bg-[var(--nord8)] hover:bg-[var(--nord9)] text-white text-[11px] font-medium rounded-lg shadow-sm transition-colors cursor-pointer inline-flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        Derivar a Área
                    </button>
                </template>

                <button disabled class="px-3 py-1.5 bg-[var(--surface-header)] text-[var(--nord3)] text-[11px] font-medium rounded-lg border border-[var(--nord4)] cursor-not-allowed">
                    Expediente Clínico
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Left Column -->
            <div class="md:col-span-2 space-y-6">
                <!-- Profile Card -->
                <div class="bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] overflow-hidden">
                    <div class="px-6 py-4 border-b border-[var(--nord4)] bg-[var(--surface-header)] flex justify-between items-center">
                        <h2 class="text-[14px] font-medium text-[var(--nord0)] flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--nord10)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Perfil del Estudiante
                        </h2>
                    </div>
                    
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Nombre -->
                        <div class="flex items-start gap-3 p-3 rounded-xl bg-[var(--surface-subtle)] border border-[var(--nord4)]">
                            <div class="w-8 h-8 rounded-lg bg-[var(--frost4)]/10 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="h-4 w-4 text-[var(--frost4)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-0.5">Nombre Completo</p>
                                <p class="text-[14px] text-[var(--nord0)] font-semibold">{{ paciente.nombre_completo }}</p>
                            </div>
                        </div>
                        <!-- Carnet -->
                        <div class="flex items-start gap-3 p-3 rounded-xl bg-[var(--surface-subtle)] border border-[var(--nord4)]">
                            <div class="w-8 h-8 rounded-lg bg-[var(--aurora-yellow)]/15 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="h-4 w-4 text-[var(--aurora-yellow)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-0.5">Carnet Estudiantil</p>
                                <p class="text-[14px] text-[var(--nord0)] font-mono font-semibold">{{ paciente.carnet }}</p>
                            </div>
                        </div>
                        <!-- Motivo -->
                        <div v-if="paciente.motivo_consulta" class="md:col-span-2 flex items-start gap-3 p-3 rounded-xl bg-[var(--frost4)]/5 border border-[var(--frost4)]/20">
                            <div class="w-8 h-8 rounded-lg bg-[var(--frost4)]/10 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="h-4 w-4 text-[var(--frost4)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-1">Motivo de Consulta</p>
                                <p class="text-[13px] text-[var(--nord0)] whitespace-pre-line leading-relaxed">{{ paciente.motivo_consulta }}</p>
                                <div v-if="paciente.etiquetas_motivo && paciente.etiquetas_motivo.length > 0" class="flex flex-wrap gap-1.5 mt-2">
                                    <span v-for="tag in paciente.etiquetas_motivo" :key="tag" class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-[var(--nord10)] text-white shadow-sm">
                                        {{ tag }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <!-- Motivo de derivación -->
                        <div
                            v-if="paciente.expedientes?.some(e => e.motivo_derivacion)"
                            class="md:col-span-2 flex items-start gap-3 p-3 rounded-xl bg-[var(--nord8)]/5 border border-[var(--nord8)]/20"
                        >
                            <div class="w-8 h-8 rounded-lg bg-[var(--nord8)]/10 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="h-4 w-4 text-[var(--nord8)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </div>
                            <div class="min-w-0 flex-1 space-y-3">
                                <template v-for="exp in paciente.expedientes.filter(e => e.motivo_derivacion)" :key="exp.id">
                                    <div>
                                        <p class="text-[10px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-1">
                                            Motivo de Derivación
                                            <span v-if="getAreaName(exp.area_id)"> — {{ getAreaName(exp.area_id) }}</span>
                                        </p>
                                        <p class="text-[13px] text-[var(--nord0)] whitespace-pre-line leading-relaxed">{{ exp.motivo_derivacion }}</p>
                                        <p v-if="exp.fecha_derivacion" class="text-[11px] text-[var(--nord3)] mt-1">
                                            {{ formatDate(exp.fecha_derivacion) }}
                                        </p>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <!-- Dirección -->
                        <div class="md:col-span-2 flex items-start gap-3 p-3 rounded-xl bg-[var(--surface-subtle)] border border-[var(--nord4)]">
                            <div class="w-8 h-8 rounded-lg bg-[var(--aurora-green)]/10 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="h-4 w-4 text-[var(--aurora-green)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-0.5">Dirección de Residencia</p>
                                <p class="text-[13px] text-[var(--nord0)]">{{ paciente.direccion }}</p>
                            </div>
                        </div>
                        <!-- Fecha nac + Sexo/Civil en fila -->
                        <div class="flex items-start gap-3 p-3 rounded-xl bg-[var(--surface-subtle)] border border-[var(--nord4)]">
                            <div class="w-8 h-8 rounded-lg bg-[var(--aurora-purple)]/10 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="h-4 w-4 text-[var(--aurora-purple)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <p class="text-[10px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-0.5">Fecha de Nacimiento</p>
                                <p class="text-[14px] text-[var(--nord0)]">{{ formatDate(paciente.fecha_nacimiento) }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-3 rounded-xl bg-[var(--surface-subtle)] border border-[var(--nord4)]">
                            <div class="w-8 h-8 rounded-lg bg-[var(--aurora-orange)]/10 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="h-4 w-4 text-[var(--aurora-orange)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                            <div>
                                <p class="text-[10px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-0.5">Sexo / Estado Civil</p>
                                <p class="text-[14px] text-[var(--nord0)]">{{ paciente.sexo }} — {{ paciente.estado_civil }}</p>
                            </div>
                        </div>
                        <div v-if="paciente.profesion_ocupacion" class="flex items-start gap-3 p-3 rounded-xl bg-[var(--surface-subtle)] border border-[var(--nord4)]">
                            <div class="w-8 h-8 rounded-lg bg-[var(--frost1)]/15 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="h-4 w-4 text-[var(--frost1)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <p class="text-[10px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-0.5">Ocupación Adicional</p>
                                <p class="text-[14px] text-[var(--nord0)]">{{ paciente.profesion_ocupacion }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Academic Card -->
                <div class="bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] overflow-hidden">
                    <div class="px-6 py-4 border-b border-[var(--nord4)] bg-[var(--surface-header)] flex justify-between items-center">
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

            <!-- Right Column -->
            <div class="space-y-6">
                <!-- Family Contacts -->
                <div class="bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] overflow-hidden">
                    <div class="px-6 py-4 border-b border-[var(--nord4)] bg-[var(--surface-header)]">
                        <h2 class="text-[14px] font-medium text-[var(--nord0)] flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--aurora-orange)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            Familiares / Responsables
                        </h2>
                    </div>

                    <div class="p-0">
                        <div v-if="paciente.contactos && paciente.contactos.length > 0">
                            <div v-for="contacto in paciente.contactos" :key="contacto.id" class="p-5 border-b border-[var(--nord4)] last:border-0 relative">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-[13px] font-bold text-[var(--nord0)]">{{ contacto.parentesco }}</h3>
                                        <p class="text-[14px] text-[var(--nord3)] mt-1 font-medium">{{ contacto.nombre_completo }}</p>
                                    </div>
                                    <div v-if="contacto.es_responsable" class="flex items-center gap-1 text-[10px] border border-[var(--aurora-orange)] text-[var(--aurora-orange)] px-2 py-0.5 rounded-full font-medium whitespace-nowrap">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-[10px] w-[10px]" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        Responsable
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
                            <div class="w-12 h-12 rounded-full bg-[var(--surface-header)] flex items-center justify-center mx-auto mb-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-[var(--nord3)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            No hay familiares registrados.
                        </div>
                    </div>
                </div>

                <!-- Citas Pendientes -->
                <div v-if="citasPendientes && citasPendientes.length > 0" class="bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] overflow-hidden">
                    <div class="px-6 py-4 border-b border-[var(--nord4)] bg-[var(--surface-header)]">
                        <h2 class="text-[14px] font-medium text-[var(--nord0)] flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--aurora-purple)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Citas Pendientes
                        </h2>
                    </div>
                    <div class="p-0">
                        <div v-for="cita in citasPendientes" :key="cita.id" class="p-5 border-b border-[var(--nord4)] last:border-0">
                            <div class="flex flex-col gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-[11px] font-semibold bg-[var(--surface-header)] text-[var(--nord3)] border border-[var(--nord4)]">
                                        {{ formatDate(cita.fecha_hora) }} a las {{ new Date(cita.fecha_hora).toLocaleTimeString('es-ES', {hour: '2-digit', minute:'2-digit'}) }}
                                    </span>
                                </div>
                                <p class="text-[13px] text-[var(--nord0)] font-medium mt-1">{{ cita.motivo }}</p>
                                <div v-if="cita.cita_origen" class="mt-2 rounded-lg border border-[var(--aurora-purple)]/30 bg-[var(--aurora-purple)]/5 px-3 py-2 text-left">
                                    <p class="text-[11px] font-bold uppercase tracking-wide text-[var(--aurora-purple)]">Historial de reprogramación</p>
                                    <p class="text-[12px] text-[var(--nord0)] mt-1">
                                        Antes: {{ formatDateTime(cita.cita_origen.fecha_hora) }}
                                    </p>
                                    <p v-if="cita.cita_origen.motivo_reprogramacion" class="text-[12px] text-[var(--nord3)] mt-0.5">
                                        Motivo: {{ cita.cita_origen.motivo_reprogramacion }}
                                    </p>
                                </div>
                                
                                <div v-if="can?.updateCita" class="flex flex-col gap-2 mt-3 pt-3 border-t border-[var(--nord4)]/60">
                                    <p v-if="!puedeRegistrarAsistencia(cita)" class="text-[11px] text-[var(--nord3)]">
                                        La asistencia se habilita a partir de la hora programada de la cita.
                                    </p>
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            @click="marcarAsistencia(cita.id, 'asistida')"
                                            :disabled="!puedeRegistrarAsistencia(cita)"
                                            class="flex-1 py-1.5 text-[11px] font-semibold rounded-lg bg-[var(--aurora-green)] hover:bg-[#8FBCBB] text-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-[var(--aurora-green)]"
                                        >
                                            Asistió
                                        </button>
                                        <button
                                            type="button"
                                            @click="marcarAsistencia(cita.id, 'ausente')"
                                            :disabled="!puedeRegistrarAsistencia(cita)"
                                            class="flex-1 py-1.5 text-[11px] font-semibold rounded-lg bg-[var(--aurora-red)] hover:bg-[#BF616A] text-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-[var(--aurora-red)]"
                                        >
                                            Ausente
                                        </button>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button @click="openGestionarCitaModal(cita, 'reprogramar')" class="flex-1 py-1.5 text-[11px] font-semibold rounded-lg bg-[var(--surface-header)] hover:bg-[var(--nord6)] text-[var(--nord8)] border border-[var(--nord8)] transition-colors">
                                            Reprogramar
                                        </button>
                                        <button @click="openGestionarCitaModal(cita, 'cancelar')" class="flex-1 py-1.5 text-[11px] font-semibold rounded-lg bg-[var(--surface-header)] hover:bg-[var(--nord6)] text-[var(--aurora-red)] border border-[var(--aurora-red)] transition-colors">
                                            Cancelar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Clinical Records -->
                <div class="bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] overflow-hidden">
                    <div v-if="expedienteActivo" class="px-6 py-6 text-center">
                        <div class="w-14 h-14 mx-auto mb-4 rounded-2xl flex items-center justify-center bg-[var(--frost4)]/10">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-[var(--frost4)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h3 class="text-[14px] font-semibold text-[var(--nord0)] mb-1">Módulo de Expedientes</h3>
                        <p class="text-[12px] text-[var(--nord3)] mb-5 leading-relaxed">
                            Expediente actual en estado <span class="font-bold">{{ expedienteActivo.estado.replace('_', ' ') }}</span>.
                        </p>
                        <div class="space-y-3">
                            <Link v-if="can?.createConsulta" :href="'/expedientes/' + expedienteActivo.id + '/consultas/create'" class="w-full py-2 text-[12px] font-medium rounded-lg flex items-center justify-center gap-2 bg-[var(--nord8)] hover:bg-[var(--nord9)] text-white transition-colors shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                Registrar Consulta Clínica
                            </Link>

                            <button v-if="can?.updateExpediente" @click="isActualizarModalOpen = true" class="w-full py-2 text-[12px] font-medium rounded-lg flex items-center justify-center gap-2 bg-[var(--surface-header)] hover:bg-[var(--nord6)] text-[var(--nord0)] border border-[var(--nord4)] transition-colors shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                Actualizar Expediente
                            </button>
                            
                            <button v-if="can?.assignCita" @click="isCitaModalOpen = true" class="w-full py-2 text-[12px] font-medium rounded-lg flex items-center justify-center gap-2 bg-[var(--surface-header)] hover:bg-[var(--nord6)] text-[var(--nord0)] border border-[var(--nord4)] transition-colors shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                Agendar Próxima Cita
                            </button>
                            
                            <button v-if="can?.closeExpediente" @click="isCierreModalOpen = true" class="w-full py-2 text-[12px] font-medium rounded-lg flex items-center justify-center gap-2 bg-[var(--surface-header)] hover:bg-[var(--aurora-red)] text-[var(--aurora-red)] hover:text-white border border-[var(--aurora-red)] hover:border-transparent transition-colors shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                Cerrar Expediente
                            </button>
                            <Link v-if="hasAnyExpediente" :href="'/pacientes/' + paciente.codigo + '/historial'" class="w-full py-2 text-[12px] font-medium rounded-lg flex items-center justify-center gap-2 bg-[var(--surface-header)] hover:bg-[var(--surface-subtle)] text-[var(--nord3)] hover:text-[var(--nord0)] border border-[var(--nord4)] transition-colors shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                                Ver Historial Multidisciplinario
                            </Link>
                        </div>
                    </div>
                    <div v-else-if="expedienteCerrado" class="px-6 py-6">
                        <div class="text-center mb-4">
                            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest mb-3"
                                style="background: linear-gradient(135deg, var(--nord4)/20, var(--nord3)/10); border: 1px solid var(--nord4); color: var(--nord3);">
                                Expediente cerrado
                            </div>
                            <h3 class="text-[14px] font-semibold text-[var(--nord0)] mb-1">Modo consulta</h3>
                            <p class="text-[12px] text-[var(--nord3)]">Solo lectura. No se permiten nuevas ediciones clínicas.</p>
                        </div>
                        <div class="space-y-3 text-left rounded-xl border border-[var(--nord4)] bg-[var(--surface-subtle)] p-4">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[var(--nord3)]">Resultado final</p>
                                <p class="text-[13px] text-[var(--nord0)] mt-1 whitespace-pre-line">{{ expedienteCerrado.resultado_final || 'No registrado' }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[var(--nord3)]">Motivo de cierre</p>
                                <p class="text-[13px] text-[var(--nord0)] mt-1 whitespace-pre-line">{{ expedienteCerrado.motivo_cierre || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[var(--nord3)]">Fecha de cierre</p>
                                <p class="text-[13px] text-[var(--nord0)] mt-1">{{ formatDateTime(expedienteCerrado.fecha_cierre) || '—' }}</p>
                            </div>
                        </div>
                        <Link v-if="hasAnyExpediente" :href="'/pacientes/' + paciente.codigo + '/historial'" class="mt-4 w-full py-2 text-[12px] font-medium rounded-lg flex items-center justify-center gap-2 bg-[var(--surface-header)] hover:bg-[var(--surface-subtle)] text-[var(--nord3)] hover:text-[var(--nord0)] border border-[var(--nord4)] transition-colors shadow-sm">
                            Ver Historial Multidisciplinario
                        </Link>
                    </div>
                    <div v-else class="relative px-6 py-8 text-center overflow-hidden">
                        <!-- Fondo decorativo -->
                        <div class="absolute inset-0 opacity-[0.03]" style="background: repeating-linear-gradient(45deg, var(--nord0) 0, var(--nord0) 1px, transparent 0, transparent 50%); background-size: 12px 12px;"></div>
                        <div class="relative z-10">
                            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest mb-4"
                                style="background: linear-gradient(135deg, var(--aurora-yellow)/15, var(--aurora-orange)/10); border: 1px solid var(--aurora-yellow)/30; color: var(--aurora-yellow);">
                                <span class="w-1.5 h-1.5 rounded-full bg-current animate-pulse"></span>
                                Sin Expediente
                            </div>
                            <div class="w-14 h-14 mx-auto mb-4 rounded-2xl flex items-center justify-center"
                                style="background: linear-gradient(135deg, var(--nord4) 0%, var(--nord5) 100%);">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-[var(--nord3)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <h3 class="text-[14px] font-semibold text-[var(--nord0)] mb-1">Módulo de Expedientes</h3>
                            <p class="text-[12px] text-[var(--nord3)] mb-5 leading-relaxed">
                                El paciente no tiene un expediente<br>activo en su área clínica.
                            </p>
                            <div class="space-y-3">
                                <button disabled class="w-full py-2 text-[12px] font-medium rounded-lg cursor-not-allowed flex items-center justify-center gap-2"
                                    style="background: var(--surface-subtle); color: var(--nord4); border: 1px dashed var(--nord4);">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                    Crear Nueva Sesión
                                </button>
                                <Link v-if="hasAnyExpediente" :href="'/pacientes/' + paciente.codigo + '/historial'" class="w-full py-2 text-[12px] font-medium rounded-lg flex items-center justify-center gap-2 bg-[var(--surface-header)] hover:bg-[var(--surface-subtle)] text-[var(--nord3)] hover:text-[var(--nord0)] border border-[var(--nord4)] transition-colors shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                                    Ver Historial Multidisciplinario
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DerivacionModal
            :show="isDerivacionModalOpen"
            :paciente="paciente"
            :areas="areasDisponibles"
            @close="isDerivacionModalOpen = false"
        />

        <CierreExpedienteModal
            :show="isCierreModalOpen"
            :expediente="expedienteActivo"
            @close="isCierreModalOpen = false"
        />

        <ActualizarExpedienteModal
            :show="isActualizarModalOpen"
            :expediente="expedienteActivo"
            @close="isActualizarModalOpen = false"
        />
        
        <AgendarCitaModal
            :show="isCitaModalOpen"
            :expediente="expedienteActivo"
            :consulta-id="consultaActivaId || page.props.flash?.prompt_cita_consulta_id"
            @close="isCitaModalOpen = false"
        />

        <GestionarCitaModal
            v-if="selectedCita && expedienteActivo"
            :show="isGestionarCitaModalOpen"
            :mode="gestionarCitaMode"
            :cita="selectedCita"
            :expediente="expedienteActivo"
            @close="isGestionarCitaModalOpen = false"
        />
    </div>
</template>
