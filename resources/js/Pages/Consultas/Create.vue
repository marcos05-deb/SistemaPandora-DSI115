<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';
import Breadcrumbs from '@/Components/UI/Breadcrumbs.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import { todayLocalYmd } from '@/utils/dates';

defineOptions({ layout: ClinicalLayout });

const props = defineProps({
    expediente: { type: Object, required: true },
    esPrimeraConsulta: { type: Boolean, default: false },
});

const hoy = todayLocalYmd();
const haceUnAno = (() => {
    const d = new Date();
    d.setFullYear(d.getFullYear() - 1);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
})();

const form = useForm({
    motivo_consulta: '',
    notas_clinicas: '',
    diagnostico: '',
    plan_atencion: '',
    tecnica_utilizada: '',
    fecha_consulta: hoy,
    evaluacion_inicial: {
        apariencia_externa: '',
        voz: '',
        patrones_habla: '',
        expresiones_faciales: '',
        ademanes: '',
        actitudes_tratamiento: '',
        impresion: '',
        plan_tratamiento: '',
        pronostico: '',
    },
});

const maxFechaConsulta = hoy;
const minFechaConsulta = haceUnAno;
const activeAccordion = ref(0);
const clientBanner = ref('');

const ACCORDION_FIELDS = {
    0: ['apariencia_externa', 'voz', 'patrones_habla', 'expresiones_faciales', 'ademanes'],
    1: ['actitudes_tratamiento', 'impresion'],
    2: ['plan_tratamiento', 'pronostico'],
};

const FIELD_LABELS = {
    motivo_consulta: 'Motivo de consulta',
    fecha_consulta: 'Fecha de la consulta',
    tecnica_utilizada: 'Técnica utilizada',
    diagnostico: 'Diagnóstico',
    notas_clinicas: 'Observación / Notas clínicas',
    plan_atencion: 'Plan de atención',
    'evaluacion_inicial.apariencia_externa': 'Apariencia externa',
    'evaluacion_inicial.voz': 'Voz',
    'evaluacion_inicial.patrones_habla': 'Patrones de habla',
    'evaluacion_inicial.expresiones_faciales': 'Expresiones faciales',
    'evaluacion_inicial.ademanes': 'Ademanes',
    'evaluacion_inicial.actitudes_tratamiento': 'Actitudes ante el tratamiento',
    'evaluacion_inicial.impresion': 'Impresión',
    'evaluacion_inicial.plan_tratamiento': 'Plan de tratamiento de la evaluación inicial',
    'evaluacion_inicial.pronostico': 'Pronóstico',
};

const VISUAL_FIELD_ORDER = [
    'evaluacion_inicial.apariencia_externa',
    'evaluacion_inicial.voz',
    'evaluacion_inicial.patrones_habla',
    'evaluacion_inicial.expresiones_faciales',
    'evaluacion_inicial.ademanes',
    'evaluacion_inicial.actitudes_tratamiento',
    'evaluacion_inicial.impresion',
    'evaluacion_inicial.plan_tratamiento',
    'evaluacion_inicial.pronostico',
    'motivo_consulta',
    'fecha_consulta',
    'tecnica_utilizada',
    'diagnostico',
    'notas_clinicas',
    'plan_atencion',
];

const ACCORDION_TITLES = {
    0: 'Observaciones físicas y conductuales',
    1: 'Impresión y diagnóstico',
    2: 'Plan y Pronóstico',
};

function fieldId(key) {
    return key.replaceAll('.', '-');
}

function errorKey(key) {
    return key;
}

function accordionForField(key) {
    if (!key.startsWith('evaluacion_inicial.')) {
        return null;
    }
    const campo = key.replace('evaluacion_inicial.', '');
    for (const [idx, fields] of Object.entries(ACCORDION_FIELDS)) {
        if (fields.includes(campo)) {
            return Number(idx);
        }
    }
    return null;
}

function hasError(key) {
    return Boolean(form.errors[errorKey(key)]);
}

function errorMessage(key) {
    return form.errors[errorKey(key)] || '';
}

function clearFieldError(key) {
    if (form.errors[key]) {
        form.clearErrors(key);
    }
    clientBanner.value = '';
}

function isBlank(value) {
    return !String(value ?? '').trim();
}

function runClientValidation() {
    const errors = {};

    if (isBlank(form.motivo_consulta)) {
        errors.motivo_consulta = 'El motivo de la consulta es obligatorio.';
    }
    if (isBlank(form.fecha_consulta)) {
        errors.fecha_consulta = 'La fecha de la consulta es obligatoria.';
    }
    if (isBlank(form.tecnica_utilizada)) {
        errors.tecnica_utilizada = 'La técnica utilizada es obligatoria.';
    }
    if (isBlank(form.plan_atencion)) {
        errors.plan_atencion = 'El plan de atención es obligatorio.';
    } else if (String(form.plan_atencion).trim().length < 10) {
        errors.plan_atencion = 'El plan de atención debe tener al menos 10 caracteres.';
    }

    if (isBlank(form.diagnostico) && isBlank(form.notas_clinicas)) {
        errors.diagnostico = 'Complete al menos el diagnóstico o la observación clínica.';
        errors.notas_clinicas = 'Complete al menos el diagnóstico o la observación clínica.';
    }

    if (props.esPrimeraConsulta) {
        for (const campo of Object.values(ACCORDION_FIELDS).flat()) {
            const key = `evaluacion_inicial.${campo}`;
            if (isBlank(form.evaluacion_inicial[campo])) {
                errors[key] = `El campo ${FIELD_LABELS[key] || campo} es obligatorio en la primera consulta.`;
            }
        }
    }

    return errors;
}

const errorSummary = computed(() => {
    const seen = new Set();
    const items = [];

    for (const key of VISUAL_FIELD_ORDER) {
        const message = form.errors[key];
        if (!message) continue;
        const dedupe = `${key}:${message}`;
        if (seen.has(dedupe) || seen.has(message)) continue;
        seen.add(dedupe);
        seen.add(message);
        items.push({
            key,
            label: FIELD_LABELS[key] || key,
            message,
        });
    }

    // Cualquier error no mapeado
    for (const [key, message] of Object.entries(form.errors)) {
        if (!message) continue;
        const dedupe = `${key}:${message}`;
        if (seen.has(dedupe) || seen.has(message)) continue;
        seen.add(dedupe);
        items.push({ key, label: FIELD_LABELS[key] || key, message });
    }

    return items;
});

const hasAnyErrors = computed(() => errorSummary.value.length > 0);

function accordionPendingCount(index) {
    return ACCORDION_FIELDS[index].filter((campo) => hasError(`evaluacion_inicial.${campo}`)).length;
}

async function focusField(key) {
    const accordion = accordionForField(key);
    if (accordion !== null) {
        activeAccordion.value = accordion;
    }

    await nextTick();

    const el = document.getElementById(fieldId(key));
    if (!el) return;

    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    if (typeof el.focus === 'function') {
        el.focus({ preventScroll: true });
    }
}

async function focusFirstError(errorsObject) {
    const keys = Object.keys(errorsObject || form.errors || {});
    if (!keys.length) return;

    const ordered = VISUAL_FIELD_ORDER.filter((k) => keys.includes(k));
    const first = ordered[0] || keys[0];
    await focusField(first);
}

const submit = async () => {
    if (form.processing) return;

    clientBanner.value = '';

    const clientErrors = runClientValidation();
    if (Object.keys(clientErrors).length > 0) {
        form.clearErrors();
        form.setError(clientErrors);
        clientBanner.value = 'No se pudo guardar. Complete los campos obligatorios resaltados.';
        await focusFirstError(clientErrors);
        return;
    }

    form.clearErrors();

    form.post(`/expedientes/${props.expediente.id}/consultas`, {
        preserveScroll: true,
        onError: async (errors) => {
            clientBanner.value = 'No se pudo guardar la consulta. Revise los siguientes campos:';
            await focusFirstError(errors);
        },
        onFinish: () => {
            // form.processing se restaura solo; mantenemos datos y errores.
        },
    });
};

watch(
    () => form.motivo_consulta,
    () => clearFieldError('motivo_consulta'),
);
watch(
    () => form.fecha_consulta,
    () => clearFieldError('fecha_consulta'),
);
watch(
    () => form.tecnica_utilizada,
    () => clearFieldError('tecnica_utilizada'),
);
watch(
    () => form.plan_atencion,
    () => clearFieldError('plan_atencion'),
);
watch(
    () => form.diagnostico,
    () => {
        clearFieldError('diagnostico');
        if (!isBlank(form.diagnostico) || !isBlank(form.notas_clinicas)) {
            clearFieldError('notas_clinicas');
        }
    },
);
watch(
    () => form.notas_clinicas,
    () => {
        clearFieldError('notas_clinicas');
        if (!isBlank(form.diagnostico) || !isBlank(form.notas_clinicas)) {
            clearFieldError('diagnostico');
        }
    },
);

for (const campo of Object.values(ACCORDION_FIELDS).flat()) {
    watch(
        () => form.evaluacion_inicial[campo],
        () => clearFieldError(`evaluacion_inicial.${campo}`),
    );
}
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

            <form novalidate @submit.prevent="submit" class="p-6 space-y-6">
                <div
                    v-if="hasAnyErrors || clientBanner"
                    class="rounded-xl border border-[var(--aurora-red)]/40 bg-[var(--aurora-red)]/5 px-4 py-3"
                    role="alert"
                    aria-live="polite"
                >
                    <p class="text-[13px] font-semibold text-[var(--aurora-red)]">
                        {{ clientBanner || 'No se pudo guardar la consulta. Revise los siguientes campos:' }}
                    </p>
                    <ul v-if="errorSummary.length" class="mt-2 space-y-1">
                        <li v-for="item in errorSummary" :key="item.key">
                            <button
                                type="button"
                                class="text-left text-[12px] text-[var(--aurora-red)] hover:underline"
                                @click="focusField(item.key)"
                            >
                                → {{ item.label }}: {{ item.message }}
                            </button>
                        </li>
                    </ul>
                </div>

                <!-- Evaluación Inicial -->
                <div v-if="esPrimeraConsulta" class="border border-[var(--nord4)] rounded-xl overflow-hidden shadow-sm">
                    <div class="bg-[var(--nord6)] px-4 py-3 border-b border-[var(--nord4)]">
                        <h2 class="text-[14px] font-semibold text-[var(--nord0)] flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--nord9)]" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
                            </svg>
                            Evaluación Inicial (Requerido - Primera Cita)
                        </h2>
                        <p class="text-[12px] text-[var(--nord3)] mt-1">
                            Los campos marcados con <span class="text-[var(--aurora-red)]" aria-hidden="true">*</span><span class="sr-only">asterisco</span> son obligatorios.
                        </p>
                    </div>
                    <div class="p-0 bg-white">
                        <div
                            v-for="(fields, index) in ACCORDION_FIELDS"
                            :key="index"
                            :class="Number(index) < 2 ? 'border-b border-[var(--nord4)]' : ''"
                        >
                            <button
                                type="button"
                                class="w-full text-left px-4 py-3 font-medium text-[13px] flex justify-between items-center hover:bg-[var(--nord6)] transition-colors"
                                :class="accordionPendingCount(Number(index)) > 0 ? 'text-[var(--aurora-red)] border-l-4 border-l-[var(--aurora-red)]' : 'text-[var(--nord0)]'"
                                :aria-expanded="activeAccordion === Number(index)"
                                @click="activeAccordion = Number(index)"
                            >
                                <span class="inline-flex items-center gap-2">
                                    <svg
                                        v-if="accordionPendingCount(Number(index)) > 0"
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4 text-[var(--aurora-red)] shrink-0"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                        aria-hidden="true"
                                    >
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l6.518 11.597c.75 1.335-.213 2.999-1.742 2.999H3.48c-1.53 0-2.492-1.664-1.742-2.999L8.257 3.1zM11 14a1 1 0 10-2 0 1 1 0 002 0zm-1-2a1 1 0 01-1-1V8a1 1 0 112 0v3a1 1 0 01-1 1z" clip-rule="evenodd" />
                                    </svg>
                                    <span>
                                        {{ Number(index) + 1 }}. {{ ACCORDION_TITLES[Number(index)] }}
                                        <template v-if="accordionPendingCount(Number(index)) > 0">
                                            — {{ accordionPendingCount(Number(index)) }} campo{{ accordionPendingCount(Number(index)) === 1 ? '' : 's' }} pendiente{{ accordionPendingCount(Number(index)) === 1 ? '' : 's' }}
                                        </template>
                                    </span>
                                </span>
                                <svg :class="{ 'rotate-180': activeAccordion === Number(index) }" class="h-4 w-4 text-[var(--nord3)] transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div v-show="activeAccordion === Number(index)" class="p-4 bg-white grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div v-for="campo in fields" :key="campo">
                                    <label
                                        :for="fieldId('evaluacion_inicial.' + campo)"
                                        class="block text-[12px] font-semibold text-[var(--nord0)] mb-1.5"
                                    >
                                        <template v-if="campo === 'apariencia_externa'">Apariencia externa</template>
                                        <template v-else-if="campo === 'patrones_habla'">Patrones de habla</template>
                                        <template v-else-if="campo === 'expresiones_faciales'">Expresiones faciales</template>
                                        <template v-else-if="campo === 'actitudes_tratamiento'">Actitudes ante el tratamiento</template>
                                        <template v-else-if="campo === 'impresion'">Impresión</template>
                                        <template v-else-if="campo === 'plan_tratamiento'">Plan de tratamiento de la evaluación inicial</template>
                                        <template v-else-if="campo === 'pronostico'">Pronóstico</template>
                                        <template v-else-if="campo === 'voz'">Voz</template>
                                        <template v-else-if="campo === 'ademanes'">Ademanes</template>
                                        <template v-else>{{ campo.replaceAll('_', ' ') }}</template>
                                        <span class="text-[var(--aurora-red)]" aria-hidden="true"> *</span>
                                        <span class="sr-only">(obligatorio)</span>
                                    </label>
                                    <input
                                        :id="fieldId('evaluacion_inicial.' + campo)"
                                        v-model="form.evaluacion_inicial[campo]"
                                        type="text"
                                        aria-required="true"
                                        :aria-invalid="hasError('evaluacion_inicial.' + campo) ? 'true' : 'false'"
                                        :aria-describedby="hasError('evaluacion_inicial.' + campo) ? fieldId('evaluacion_inicial.' + campo) + '-error' : undefined"
                                        class="w-full rounded-lg border bg-[var(--nord6)] px-3 py-2 text-[13px] focus:border-[var(--nord8)] focus:ring-0"
                                        :class="hasError('evaluacion_inicial.' + campo) ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)]'"
                                    />
                                    <p
                                        v-if="hasError('evaluacion_inicial.' + campo)"
                                        :id="fieldId('evaluacion_inicial.' + campo) + '-error'"
                                        class="text-[var(--aurora-red)] text-[11px] mt-1"
                                    >
                                        {{ errorMessage('evaluacion_inicial.' + campo) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Motivo de Consulta -->
                <div>
                    <label for="motivo_consulta" class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5">
                        Motivo de Consulta <span class="text-[var(--aurora-red)]" aria-hidden="true">*</span><span class="sr-only">(obligatorio)</span>
                    </label>
                    <textarea
                        id="motivo_consulta"
                        v-model="form.motivo_consulta"
                        rows="2"
                        aria-required="true"
                        :aria-invalid="hasError('motivo_consulta') ? 'true' : 'false'"
                        :aria-describedby="hasError('motivo_consulta') ? 'motivo_consulta-error' : undefined"
                        class="w-full rounded-lg border bg-[var(--nord6)] px-3 py-2.5 text-[14px] text-[var(--nord0)] focus:border-[var(--nord8)] focus:ring-0"
                        :class="hasError('motivo_consulta') ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)]'"
                    ></textarea>
                    <p v-if="hasError('motivo_consulta')" id="motivo_consulta-error" class="text-[var(--aurora-red)] text-[12px] mt-1">{{ errorMessage('motivo_consulta') }}</p>
                </div>

                <!-- Fecha de consulta -->
                <div>
                    <label for="fecha_consulta" class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5">
                        Fecha de la consulta <span class="text-[var(--aurora-red)]" aria-hidden="true">*</span><span class="sr-only">(obligatorio)</span>
                    </label>
                    <input
                        id="fecha_consulta"
                        v-model="form.fecha_consulta"
                        type="date"
                        :min="minFechaConsulta"
                        :max="maxFechaConsulta"
                        aria-required="true"
                        :aria-invalid="hasError('fecha_consulta') ? 'true' : 'false'"
                        :aria-describedby="hasError('fecha_consulta') ? 'fecha_consulta-error' : undefined"
                        class="w-full sm:w-64 rounded-lg border bg-[var(--nord6)] px-3 py-2 text-[14px] text-[var(--nord0)] focus:border-[var(--nord8)] focus:ring-0"
                        :class="hasError('fecha_consulta') ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)]'"
                    />
                    <p class="text-[11px] text-[var(--nord3)] mt-1">Fecha clínica de la atención (no puede ser futura).</p>
                    <p v-if="hasError('fecha_consulta')" id="fecha_consulta-error" class="text-[var(--aurora-red)] text-[12px] mt-1">{{ errorMessage('fecha_consulta') }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="tecnica_utilizada" class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5">
                            Técnica Utilizada <span class="text-[var(--aurora-red)]" aria-hidden="true">*</span><span class="sr-only">(obligatorio)</span>
                        </label>
                        <input
                            id="tecnica_utilizada"
                            v-model="form.tecnica_utilizada"
                            type="text"
                            aria-required="true"
                            :aria-invalid="hasError('tecnica_utilizada') ? 'true' : 'false'"
                            :aria-describedby="hasError('tecnica_utilizada') ? 'tecnica_utilizada-error' : undefined"
                            class="w-full rounded-lg border bg-[var(--nord6)] px-3 py-2 text-[14px] text-[var(--nord0)] focus:border-[var(--nord8)] focus:ring-0"
                            :class="hasError('tecnica_utilizada') ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)]'"
                        />
                        <p v-if="hasError('tecnica_utilizada')" id="tecnica_utilizada-error" class="text-[var(--aurora-red)] text-[12px] mt-1">{{ errorMessage('tecnica_utilizada') }}</p>
                    </div>

                    <div>
                        <label for="diagnostico" class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5">
                            Diagnóstico
                        </label>
                        <input
                            id="diagnostico"
                            v-model="form.diagnostico"
                            type="text"
                            :aria-invalid="hasError('diagnostico') ? 'true' : 'false'"
                            :aria-describedby="hasError('diagnostico') ? 'diagnostico-error dx-obs-hint' : 'dx-obs-hint'"
                            class="w-full rounded-lg border bg-[var(--nord6)] px-3 py-2 text-[14px] text-[var(--nord0)] focus:border-[var(--nord8)] focus:ring-0"
                            :class="hasError('diagnostico') ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)]'"
                        />
                        <p v-if="hasError('diagnostico')" id="diagnostico-error" class="text-[var(--aurora-red)] text-[12px] mt-1">{{ errorMessage('diagnostico') }}</p>
                    </div>
                </div>

                <p id="dx-obs-hint" class="text-[12px] text-[var(--nord3)] -mt-2">
                    Complete al menos el diagnóstico o la observación clínica <span class="text-[var(--aurora-red)]" aria-hidden="true">*</span><span class="sr-only">(obligatorio al menos uno)</span>
                </p>

                <div>
                    <label for="notas_clinicas" class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5">
                        Observación / Notas clínicas
                    </label>
                    <textarea
                        id="notas_clinicas"
                        v-model="form.notas_clinicas"
                        rows="4"
                        :aria-invalid="hasError('notas_clinicas') ? 'true' : 'false'"
                        :aria-describedby="hasError('notas_clinicas') ? 'notas_clinicas-error dx-obs-hint' : 'dx-obs-hint'"
                        class="w-full rounded-lg border bg-[var(--nord6)] px-3 py-2.5 text-[14px] text-[var(--nord0)] focus:border-[var(--nord8)] focus:ring-0"
                        :class="hasError('notas_clinicas') ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)]'"
                    ></textarea>
                    <p v-if="hasError('notas_clinicas')" id="notas_clinicas-error" class="text-[var(--aurora-red)] text-[12px] mt-1">{{ errorMessage('notas_clinicas') }}</p>
                </div>

                <div>
                    <label for="plan_atencion" class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5">
                        Plan de atención <span class="text-[var(--aurora-red)]" aria-hidden="true">*</span><span class="sr-only">(obligatorio)</span>
                    </label>
                    <textarea
                        id="plan_atencion"
                        v-model="form.plan_atencion"
                        rows="3"
                        maxlength="1000"
                        placeholder="Indique el plan de atención acordado en esta consulta (mín. 10 caracteres)"
                        aria-required="true"
                        :aria-invalid="hasError('plan_atencion') ? 'true' : 'false'"
                        :aria-describedby="hasError('plan_atencion') ? 'plan_atencion-error' : undefined"
                        class="w-full rounded-lg border bg-[var(--nord6)] px-3 py-2.5 text-[14px] text-[var(--nord0)] focus:border-[var(--nord8)] focus:ring-0"
                        :class="hasError('plan_atencion') ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)]'"
                    ></textarea>
                    <p v-if="hasError('plan_atencion')" id="plan_atencion-error" class="text-[var(--aurora-red)] text-[12px] mt-1">{{ errorMessage('plan_atencion') }}</p>
                </div>

                <div class="pt-4 border-t border-[var(--nord4)] flex items-center justify-end gap-3">
                    <Link
                        :href="'/pacientes/' + expediente.paciente.carnet"
                        class="px-4 py-2 text-[13px] font-medium text-[var(--nord3)] hover:bg-[var(--nord6)] rounded-lg transition-colors border border-transparent hover:border-[var(--nord4)]"
                    >
                        Cancelar
                    </Link>
                    <PrimaryButton type="submit" :disabled="form.processing" class="gap-2 px-5 text-[13px]">
                        <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ form.processing ? 'Guardando...' : 'Guardar Consulta' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border-width: 0;
}
</style>
