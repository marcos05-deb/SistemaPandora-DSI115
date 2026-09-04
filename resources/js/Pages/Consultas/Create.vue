<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';
import Breadcrumbs from '@/Components/UI/Breadcrumbs.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';

defineOptions({ layout: ClinicalLayout });

const props = defineProps({
    expediente: { type: Object, required: true },
});

const form = useForm({
    motivo_consulta: '',
    notas_clinicas: '',
    diagnostico: '',
});

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
                        Cifrado de Extremo a Extremo
                    </span>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-6 space-y-6">
                <!-- Motivo de Consulta -->
                <div>
                    <label for="motivo_consulta" class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5">
                        Motivo de Consulta <span class="text-[var(--aurora-red)]">*</span>
                    </label>
                    <textarea 
                        id="motivo_consulta"
                        v-model="form.motivo_consulta"
                        @input="form.clearErrors('motivo_consulta')"
                        rows="3"
                        class="w-full rounded-lg border bg-[var(--nord6)] px-3 py-2.5 text-[14px] text-[var(--nord0)] focus:outline-none focus:ring-2 focus:ring-[var(--nord8)]/30 transition-all placeholder:text-[var(--nord4)]"
                        :class="form.errors.motivo_consulta ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)] focus:border-[var(--nord8)]'"
                        placeholder="Describa el motivo principal de la atención médica..."
                    ></textarea>
                    <p v-if="form.errors.motivo_consulta" class="text-[var(--aurora-red)] text-[12px] mt-1.5 flex items-center gap-1.5 font-medium">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        {{ form.errors.motivo_consulta }}
                    </p>
                </div>

                <!-- Notas Clínicas -->
                <div>
                    <label for="notas_clinicas" class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5">
                        Notas Clínicas / Evolución <span class="text-[var(--aurora-red)]">*</span>
                    </label>
                    <textarea 
                        id="notas_clinicas"
                        v-model="form.notas_clinicas"
                        @input="form.clearErrors('notas_clinicas')"
                        rows="6"
                        class="w-full rounded-lg border bg-[var(--nord6)] px-3 py-2.5 text-[14px] text-[var(--nord0)] focus:outline-none focus:ring-2 focus:ring-[var(--nord8)]/30 transition-all placeholder:text-[var(--nord4)]"
                        :class="form.errors.notas_clinicas ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)] focus:border-[var(--nord8)]'"
                        placeholder="Registre la evolución, hallazgos y detalles relevantes de la consulta..."
                    ></textarea>
                    <p v-if="form.errors.notas_clinicas" class="text-[var(--aurora-red)] text-[12px] mt-1.5 flex items-center gap-1.5 font-medium">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        {{ form.errors.notas_clinicas }}
                    </p>
                </div>

                <!-- Diagnóstico -->
                <div>
                    <label for="diagnostico" class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5">
                        Diagnóstico <span class="text-[var(--aurora-red)]">*</span>
                    </label>
                    <textarea 
                        id="diagnostico"
                        v-model="form.diagnostico"
                        @input="form.clearErrors('diagnostico')"
                        rows="3"
                        class="w-full rounded-lg border bg-[var(--nord6)] px-3 py-2.5 text-[14px] text-[var(--nord0)] focus:outline-none focus:ring-2 focus:ring-[var(--nord8)]/30 transition-all placeholder:text-[var(--nord4)]"
                        :class="form.errors.diagnostico ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)] focus:border-[var(--nord8)]'"
                        placeholder="Diagnóstico clínico presuntivo o definitivo..."
                    ></textarea>
                    <p v-if="form.errors.diagnostico" class="text-[var(--aurora-red)] text-[12px] mt-1.5 flex items-center gap-1.5 font-medium">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        {{ form.errors.diagnostico }}
                    </p>
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
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Guardar Consulta
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </div>
</template>
