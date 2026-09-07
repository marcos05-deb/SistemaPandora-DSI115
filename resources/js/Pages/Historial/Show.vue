<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';
import Breadcrumbs from '@/Components/UI/Breadcrumbs.vue';

defineOptions({ layout: ClinicalLayout });

defineProps({
    paciente: { type: Object, required: true },
    historial: { type: Object, required: true }
});
</script>

<template>
    <Head :title="`Historial Clínico - ${paciente.carnet}`" />

    <div class="max-w-4xl mx-auto space-y-6 pb-12">
        <Breadcrumbs :items="[
            { label: 'Panel Clínico', href: '/dashboard' },
            { label: 'Pacientes', href: '/pacientes' },
            { label: paciente.carnet, href: `/pacientes/${paciente.carnet}` },
            { label: 'Historial Multidisciplinario' },
        ]" />

        <!-- Header -->
        <div class="bg-white rounded-[12px] p-6 shadow-sm border border-[var(--nord4)] flex items-center justify-between">
            <div>
                <h1 class="text-[18px] font-bold text-[var(--nord0)] flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-[var(--nord10)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    Historial Clínico Multidisciplinario
                </h1>
                <p class="text-[13px] text-[var(--nord3)] mt-1 ml-8">Registros de atención consolidados de todas las áreas clínicas autorizadas.</p>
            </div>
            <div class="text-right">
                <p class="text-[12px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-1">Paciente</p>
                <p class="text-[15px] font-bold text-[var(--nord0)]">{{ paciente.nombre_completo }}</p>
                <p class="text-[13px] text-[var(--nord3)] font-mono mt-0.5">{{ paciente.carnet }}</p>
            </div>
        </div>

        <!-- Timeline -->
        <div v-if="historial.data && historial.data.length > 0" class="relative pt-6">
            <!-- Timeline line -->
            <div class="absolute left-[27px] top-6 bottom-0 w-[2px] bg-[var(--nord4)]"></div>

            <div class="space-y-8">
                <div v-for="(consulta, index) in historial.data" :key="consulta.id" class="relative pl-16">
                    <!-- Timeline dot -->
                    <div class="absolute left-[16px] top-1.5 w-6 h-6 rounded-full border-4 border-white flex items-center justify-center z-10"
                        :class="consulta.area.color" style="box-shadow: 0 0 0 1px var(--nord4);">
                    </div>

                    <!-- Content Card -->
                    <div class="bg-white rounded-[12px] shadow-sm border border-[var(--nord4)] overflow-hidden hover:border-[var(--nord9)] transition-colors">
                        <!-- Card Header -->
                        <div class="px-5 py-3 border-b border-[var(--nord4)] flex justify-between items-center bg-[var(--surface-header)]">
                            <div class="flex items-center gap-3">
                                <span class="px-2.5 py-1 rounded-md text-[11px] font-bold text-white uppercase tracking-wider" :class="consulta.area.color">
                                    {{ consulta.area.nombre }}
                                </span>
                                <span class="text-[13px] font-medium text-[var(--nord0)]">
                                    {{ consulta.profesional.nombre }}
                                </span>
                            </div>
                            <div class="text-[12px] font-medium text-[var(--nord3)] flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                {{ consulta.fecha_consulta.split(' ')[0] }}
                                <span class="ml-1 opacity-70">{{ consulta.fecha_consulta.split(' ')[1] }}</span>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="p-5 space-y-4">
                            <div>
                                <h3 class="text-[11px] font-bold text-[var(--nord3)] uppercase tracking-wider mb-1.5 flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-[var(--nord8)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                    </svg>
                                    Motivo de Consulta
                                </h3>
                                <p class="text-[14px] text-[var(--nord0)]">{{ consulta.motivo_consulta }}</p>
                            </div>

                            <div v-if="consulta.evaluacion_inicial" class="p-5 rounded-[8px] bg-[var(--surface-subtle)] border border-[var(--nord4)]">
                                <h3 class="text-[11px] font-bold text-[var(--nord3)] uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--aurora-purple)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    Evaluación Inicial (Primera Consulta)
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-y-3 gap-x-6 text-[13px]">
                                    <div v-if="consulta.evaluacion_inicial.apariencia_externa"><span class="font-semibold text-[var(--nord3)]">Apariencia Externa:</span> <span class="text-[var(--nord0)]">{{ consulta.evaluacion_inicial.apariencia_externa }}</span></div>
                                    <div v-if="consulta.evaluacion_inicial.voz"><span class="font-semibold text-[var(--nord3)]">Voz:</span> <span class="text-[var(--nord0)]">{{ consulta.evaluacion_inicial.voz }}</span></div>
                                    <div v-if="consulta.evaluacion_inicial.patrones_habla"><span class="font-semibold text-[var(--nord3)]">Patrones de Habla:</span> <span class="text-[var(--nord0)]">{{ consulta.evaluacion_inicial.patrones_habla }}</span></div>
                                    <div v-if="consulta.evaluacion_inicial.expresiones_faciales"><span class="font-semibold text-[var(--nord3)]">Expresiones Faciales:</span> <span class="text-[var(--nord0)]">{{ consulta.evaluacion_inicial.expresiones_faciales }}</span></div>
                                    <div v-if="consulta.evaluacion_inicial.ademanes"><span class="font-semibold text-[var(--nord3)]">Ademanes:</span> <span class="text-[var(--nord0)]">{{ consulta.evaluacion_inicial.ademanes }}</span></div>
                                    <div v-if="consulta.evaluacion_inicial.actitudes_tratamiento"><span class="font-semibold text-[var(--nord3)]">Actitudes al Trat.:</span> <span class="text-[var(--nord0)]">{{ consulta.evaluacion_inicial.actitudes_tratamiento }}</span></div>
                                    
                                    <div v-if="consulta.evaluacion_inicial.impresion" class="md:col-span-2 pt-2 border-t border-[var(--nord4)]/60"><span class="font-semibold text-[var(--nord3)]">Impresión:</span> <span class="text-[var(--nord0)]">{{ consulta.evaluacion_inicial.impresion }}</span></div>
                                    <div v-if="consulta.evaluacion_inicial.plan_tratamiento" class="md:col-span-2"><span class="font-semibold text-[var(--nord3)]">Plan de Tratamiento:</span> <span class="text-[var(--nord0)]">{{ consulta.evaluacion_inicial.plan_tratamiento }}</span></div>
                                    <div v-if="consulta.evaluacion_inicial.pronostico" class="md:col-span-2"><span class="font-semibold text-[var(--nord3)]">Pronóstico:</span> <span class="text-[var(--nord0)]">{{ consulta.evaluacion_inicial.pronostico }}</span></div>
                                </div>
                            </div>
                            
                            <div v-if="consulta.tecnica_utilizada">
                                <h3 class="text-[11px] font-bold text-[var(--nord3)] uppercase tracking-wider mb-1.5 flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-[var(--aurora-green)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                    </svg>
                                    Técnica Utilizada
                                </h3>
                                <p class="text-[14px] text-[var(--nord0)]">{{ consulta.tecnica_utilizada }}</p>
                            </div>
                            
                            <div class="p-4 rounded-[8px] bg-[var(--surface-subtle)] border border-[var(--nord4)]">
                                <h3 class="text-[11px] font-bold text-[var(--nord3)] uppercase tracking-wider mb-2 flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-[var(--aurora-yellow)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Notas Clínicas
                                </h3>
                                <p class="text-[13px] text-[var(--nord0)] whitespace-pre-line leading-relaxed">{{ consulta.notas_clinicas }}</p>
                            </div>

                            <div v-if="consulta.diagnostico" class="pt-2">
                                <h3 class="text-[11px] font-bold text-[var(--nord3)] uppercase tracking-wider mb-1.5 flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-[var(--aurora-red)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Diagnóstico
                                </h3>
                                <p class="text-[14px] font-medium text-[var(--nord0)]">{{ consulta.diagnostico }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="bg-white rounded-[12px] p-10 shadow-sm border border-[var(--nord4)] text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-2xl flex items-center justify-center bg-[var(--surface-header)] border border-[var(--nord4)]">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[var(--nord3)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
            </div>
            <h3 class="text-[16px] font-bold text-[var(--nord0)] mb-2">Sin Registros Clínicos</h3>
            <p class="text-[14px] text-[var(--nord3)] max-w-md mx-auto leading-relaxed">
                No se encontraron registros de consultas previas para este paciente en las áreas a las que tienes acceso autorizado.
            </p>
            <div class="mt-6">
                <Link :href="`/pacientes/${paciente.carnet}`" class="px-5 py-2.5 bg-[var(--surface-header)] hover:bg-[var(--surface-subtle)] text-[var(--nord0)] text-[13px] font-medium rounded-lg border border-[var(--nord4)] transition-colors shadow-sm inline-flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    Volver al Perfil
                </Link>
            </div>
        </div>
    </div>
</template>
