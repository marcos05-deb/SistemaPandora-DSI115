<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';
import Breadcrumbs from '@/Components/UI/Breadcrumbs.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';

defineOptions({ layout: ClinicalLayout });

const props = defineProps({
    expediente: { type: Object, required: true },
    esPrimeraConsulta: { type: Boolean, default: false }
});

const form = useForm({
    motivo_consulta: '',
    notas_clinicas: '',
    diagnostico: '',
    tecnica_utilizada: '',
    evaluacion_inicial: {
        apariencia_externa: '',
        voz: '',
        patrones_habla: '',
        expresiones_faciales: '',
        ademanes: '',
        actitudes_tratamiento: '',
        impresion: '',
        plan_tratamiento: '',
        pronostico: ''
    }
});

const activeAccordion = ref(0);

const submit = () => {
    form.post(`/expedientes/${props.expediente.id}/consultas`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Registrar Consulta Clínica" />

    <div class="max-w-4xl mx-auto space-y-6 pb-10">
        <Breadcrumbs :items="[
            { label: 'Panel Clínico', href: '/dashboard' },
            { label: 'Pacientes', href: '/pacientes' },
            { label: expediente.paciente.carnet, href: '/pacientes/' + expediente.paciente.carnet },
            { label: 'Nueva Consulta' },
        ]" />

        <div class="bg-white rounded-xl shadow-sm border border-[var(--nord4)] overflow-hidden">
            <div class="px-6 py-5 border-b border-[var(--nord4)] bg-[var(--surface-header)] flex items-center justify-between">
                <div>
                    <h1 class="text-[18px] font-semibold text-[var(--nord0)] tracking-tight flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--nord8)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Registrar Nueva Consulta
                    </h1>
                    <p class="text-[13px] text-[var(--nord3)] mt-1 ml-7">
                        Completando atención para {{ expediente.paciente.nombre_completo }}
                    </p>
                </div>
                <div class="hidden sm:block">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-medium bg-[var(--aurora-green)]/10 text-[var(--aurora-green)] border border-[var(--aurora-green)]/20">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Cifrado JSON Extremo a Extremo
                    </span>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-6 space-y-6">
                <!-- Evaluacion Inicial (Acordeon, solo si es primera consulta) -->
                <div v-if="esPrimeraConsulta" class="border border-[var(--nord4)] rounded-xl overflow-hidden shadow-sm">
                    <div class="bg-[var(--nord6)] px-4 py-3 border-b border-[var(--nord4)]">
                        <h2 class="text-[14px] font-semibold text-[var(--nord0)] flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--nord9)]" viewBox="0 0 20 20" fill="currentColor">
                              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
                            </svg>
                            Evaluación Inicial (Requerido - Primera Cita)
                        </h2>
                    </div>
                    <div class="p-0 bg-white">
                        <!-- Pestaña 1 -->
                        <div class="border-b border-[var(--nord4)]">
                            <button type="button" @click="activeAccordion = 0" class="w-full text-left px-4 py-3 font-medium text-[13px] text-[var(--nord0)] flex justify-between items-center hover:bg-[var(--nord6)] transition-colors">
                                1. Observaciones Físicas y Conductuales
                                <svg :class="{'rotate-180': activeAccordion === 0}" class="h-4 w-4 text-[var(--nord3)] transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div v-show="activeAccordion === 0" class="p-4 bg-white grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div v-for="campo in ['apariencia_externa', 'voz', 'patrones_habla', 'expresiones_faciales', 'ademanes']" :key="campo">
                                    <label class="block text-[12px] font-semibold text-[var(--nord0)] mb-1.5 capitalize">{{ campo.replace('_', ' ') }}</label>
                                    <input type="text" v-model="form.evaluacion_inicial[campo]"
                                        class="w-full rounded-lg border bg-[var(--nord6)] px-3 py-2 text-[13px] border-[var(--nord4)] focus:border-[var(--nord8)] focus:ring-0" />
                                    <p v-if="form.errors[`evaluacion_inicial.${campo}`]" class="text-[var(--aurora-red)] text-[11px] mt-1">{{ form.errors[`evaluacion_inicial.${campo}`] }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Pestaña 2 -->
                        <div class="border-b border-[var(--nord4)]">
                            <button type="button" @click="activeAccordion = 1" class="w-full text-left px-4 py-3 font-medium text-[13px] text-[var(--nord0)] flex justify-between items-center hover:bg-[var(--nord6)] transition-colors">
                                2. Impresión y Diagnóstico
                                <svg :class="{'rotate-180': activeAccordion === 1}" class="h-4 w-4 text-[var(--nord3)] transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div v-show="activeAccordion === 1" class="p-4 bg-white grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div v-for="campo in ['actitudes_tratamiento', 'impresion']" :key="campo">
                                    <label class="block text-[12px] font-semibold text-[var(--nord0)] mb-1.5 capitalize">{{ campo.replace('_', ' ') }}</label>
                                    <input type="text" v-model="form.evaluacion_inicial[campo]"
                                        class="w-full rounded-lg border bg-[var(--nord6)] px-3 py-2 text-[13px] border-[var(--nord4)] focus:border-[var(--nord8)] focus:ring-0" />
                                    <p v-if="form.errors[`evaluacion_inicial.${campo}`]" class="text-[var(--aurora-red)] text-[11px] mt-1">{{ form.errors[`evaluacion_inicial.${campo}`] }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña 3 -->
                        <div>
                            <button type="button" @click="activeAccordion = 2" class="w-full text-left px-4 py-3 font-medium text-[13px] text-[var(--nord0)] flex justify-between items-center hover:bg-[var(--nord6)] transition-colors">
                                3. Plan y Pronóstico
                                <svg :class="{'rotate-180': activeAccordion === 2}" class="h-4 w-4 text-[var(--nord3)] transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div v-show="activeAccordion === 2" class="p-4 bg-white grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div v-for="campo in ['plan_tratamiento', 'pronostico']" :key="campo">
                                    <label class="block text-[12px] font-semibold text-[var(--nord0)] mb-1.5 capitalize">{{ campo.replace('_', ' ') }}</label>
                                    <input type="text" v-model="form.evaluacion_inicial[campo]"
                                        class="w-full rounded-lg border bg-[var(--nord6)] px-3 py-2 text-[13px] border-[var(--nord4)] focus:border-[var(--nord8)] focus:ring-0" />
                                    <p v-if="form.errors[`evaluacion_inicial.${campo}`]" class="text-[var(--aurora-red)] text-[11px] mt-1">{{ form.errors[`evaluacion_inicial.${campo}`] }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Motivo de Consulta -->
                <div>
                    <label for="motivo_consulta" class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5">
                        Motivo de Consulta <span class="text-[var(--aurora-red)]">*</span>
                    </label>
                    <textarea 
                        id="motivo_consulta"
                        v-model="form.motivo_consulta"
                        rows="2"
                        class="w-full rounded-lg border bg-[var(--nord6)] px-3 py-2.5 text-[14px] text-[var(--nord0)] focus:border-[var(--nord8)] focus:ring-0"
                        :class="form.errors.motivo_consulta ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)]'"
                    ></textarea>
                    <p v-if="form.errors.motivo_consulta" class="text-[var(--aurora-red)] text-[12px] mt-1">{{ form.errors.motivo_consulta }}</p>
                </div>


                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Técnica Utilizada -->
                    <div>
                        <label for="tecnica_utilizada" class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5">
                            Técnica Utilizada <span class="text-[var(--aurora-red)]">*</span>
                        </label>
                        <input type="text"
                            id="tecnica_utilizada"
                            v-model="form.tecnica_utilizada"
                            class="w-full rounded-lg border bg-[var(--nord6)] px-3 py-2 text-[14px] text-[var(--nord0)] focus:border-[var(--nord8)] focus:ring-0"
                            :class="form.errors.tecnica_utilizada ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)]'" />
                        <p v-if="form.errors.tecnica_utilizada" class="text-[var(--aurora-red)] text-[12px] mt-1">{{ form.errors.tecnica_utilizada }}</p>
                    </div>

                    <!-- Diagnóstico -->
                    <div>
                        <label for="diagnostico" class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5">
                            Diagnóstico <span class="text-[var(--aurora-red)]">*</span>
                        </label>
                        <input type="text"
                            id="diagnostico"
                            v-model="form.diagnostico"
                            class="w-full rounded-lg border bg-[var(--nord6)] px-3 py-2 text-[14px] text-[var(--nord0)] focus:border-[var(--nord8)] focus:ring-0"
                            :class="form.errors.diagnostico ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)]'" />
                        <p v-if="form.errors.diagnostico" class="text-[var(--aurora-red)] text-[12px] mt-1">{{ form.errors.diagnostico }}</p>
                    </div>
                </div>

                <!-- Notas Clínicas -->
                <div>
                    <label for="notas_clinicas" class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5">
                        Notas Clínicas / Evolución <span class="text-[var(--aurora-red)]">*</span>
                    </label>
                    <textarea 
                        id="notas_clinicas"
                        v-model="form.notas_clinicas"
                        rows="4"
                        class="w-full rounded-lg border bg-[var(--nord6)] px-3 py-2.5 text-[14px] text-[var(--nord0)] focus:border-[var(--nord8)] focus:ring-0"
                        :class="form.errors.notas_clinicas ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)]'"
                    ></textarea>
                    <p v-if="form.errors.notas_clinicas" class="text-[var(--aurora-red)] text-[12px] mt-1">{{ form.errors.notas_clinicas }}</p>
                </div>

                <div class="pt-4 border-t border-[var(--nord4)] flex items-center justify-end gap-3">
                    <Link :href="'/pacientes/' + expediente.paciente.carnet" 
                        class="px-4 py-2 text-[13px] font-medium text-[var(--nord3)] hover:bg-[var(--nord6)] rounded-lg transition-colors border border-transparent hover:border-[var(--nord4)]">
                        Cancelar
                    </Link>
                    <PrimaryButton type="submit" :disabled="form.processing" class="gap-2 px-5 text-[13px]">
                        <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Guardar Consulta
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </div>
</template>
