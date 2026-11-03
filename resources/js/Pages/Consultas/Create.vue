<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';
import Breadcrumbs from '@/Components/UI/Breadcrumbs.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import { addDaysYmd, todayLocalYmd } from '@/utils/dates';

defineOptions({ layout: ClinicalLayout });

const props = defineProps({
    expediente: { type: Object, required: true },
    esPrimeraConsulta: { type: Boolean, default: false },
    indicadoresTendenciaSuicida: { type: Array, default: () => [] },
});

const hoy = todayLocalYmd();
const haceUnAno = addDaysYmd(hoy, -(usePage().props.limites?.consulta_antiguedad_max_dias ?? 365));

const form = useForm({
    motivo_consulta: '',
    crisis_psicologica: null,
    indicadores_tendencia_suicida: [],
    notas_clinicas: '',
    diagnostico: '',
    plan_atencion: '',
    tecnica_utilizada: '',
    referencia_externa: null,
    tipo_referencia: '',
    institucion_destino: '',
    prioridad_referencia: '',
    motivo_referencia: '',
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

// Referral fields are only sent when the toggle is "Sí" (server rejects them otherwise).
form.transform((data) => {
    if (data.referencia_externa === true) {
        return data;
    }
    return {
        ...data,
        referencia_externa: false,
        tipo_referencia: null,
        institucion_destino: null,
        prioridad_referencia: null,
        motivo_referencia: null,
    };
});

const TIPOS_REFERENCIA = [
    { value: 'hospital', label: 'Hospital' },
    { value: 'clinica', label: 'Clínica' },
    { value: 'especialista_externo', label: 'Especialista externo' },
    { value: 'otra', label: 'Otra' },
];

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
    crisis_psicologica: 'Crisis psicológica',
    indicadores_tendencia_suicida: 'Indicadores de tendencia suicida',
    fecha_consulta: 'Fecha de la consulta',
    tecnica_utilizada: 'Técnica utilizada',
    diagnostico: 'Diagnóstico',
    notas_clinicas: 'Observación / Notas clínicas',
    plan_atencion: 'Plan de atención',
    referencia_externa: 'Referencia externa',
    tipo_referencia: 'Tipo de referencia',
    institucion_destino: 'Institución de destino',
    prioridad_referencia: 'Prioridad de la referencia',
    motivo_referencia: 'Motivo de la referencia',
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
    'crisis_psicologica',
    'indicadores_tendencia_suicida',
    'referencia_externa',
    'tipo_referencia',
    'institucion_destino',
    'prioridad_referencia',
    'motivo_referencia',
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
    if (typeof form.crisis_psicologica !== 'boolean') {
        errors.crisis_psicologica = 'Selecciona Sí o No para indicar si hubo una crisis psicológica.';
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

    if (form.referencia_externa === true) {
        if (isBlank(form.tipo_referencia)) {
            errors.tipo_referencia = 'El tipo de referencia es obligatorio.';
        }
        if (isBlank(form.institucion_destino)) {
            errors.institucion_destino = 'La institución de destino es obligatoria.';
        }
        if (isBlank(form.prioridad_referencia)) {
            errors.prioridad_referencia = 'La prioridad de la referencia es obligatoria.';
        }
        if (isBlank(form.motivo_referencia)) {
            errors.motivo_referencia = 'El motivo de la referencia es obligatorio.';
        } else if (String(form.motivo_referencia).trim().length < 10) {
            errors.motivo_referencia = 'El motivo de la referencia debe tener al menos 10 caracteres.';
        }
    }

    if (isBlank(form.diagnostico) && isBlank(form.notas_clinicas)) {
        errors.diagnostico = 'Completa el diagnóstico o las notas clínicas; al menos uno es obligatorio.';
        errors.notas_clinicas = 'Completa el diagnóstico o las notas clínicas; al menos uno es obligatorio.';
    }

    if (props.esPrimeraConsulta) {
        for (const campo of Object.values(ACCORDION_FIELDS).flat()) {
            const key = `evaluacion_inicial.${campo}`;
            if (isBlank(form.evaluacion_inicial[campo])) {
                errors[key] = `Completa ${FIELD_LABELS[key] || campo}; es obligatorio en la primera consulta.`;
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
        clientBanner.value = 'No se pudo guardar. Completa los campos obligatorios marcados.';
        await focusFirstError(clientErrors);
        return;
    }

    form.clearErrors();

    form.post(`/expedientes/${props.expediente.id}/consultas`, {
        preserveScroll: true,
        onError: async (errors) => {
            clientBanner.value = 'No se pudo guardar la consulta. Revisa los campos señalados:';
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
    () => form.crisis_psicologica,
    () => clearFieldError('crisis_psicologica'),
);
watch(
    () => form.indicadores_tendencia_suicida,
    () => clearFieldError('indicadores_tendencia_suicida'),
    { deep: true },
);
for (const campo of ['referencia_externa', 'tipo_referencia', 'institucion_destino', 'prioridad_referencia', 'motivo_referencia']) {
    watch(
        () => form[campo],
        () => clearFieldError(campo),
    );
}
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

    <div class="max-w-6xl mx-auto space-y-6 pb-10">
        <Breadcrumbs :items="[
            { label: 'Panel Clínico', href: '/dashboard' },
            { label: 'Pacientes', href: '/pacientes' },
            { label: expediente.paciente.carnet, href: '/pacientes/' + expediente.paciente.carnet },
            { label: 'Nueva Consulta' },
        ]" />

        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-[26px] font-bold tracking-tight text-pd-text-primary">Registrar nueva consulta</h1>
                <p class="text-[14px] text-pd-text-secondary mt-1">Completando atención para {{ expediente.paciente.nombre_completo }}</p>
            </div>
            <span class="hidden sm:inline-flex items-center rounded-full bg-pd-brand-soft px-3 py-1 text-[11px] font-medium text-pd-brand">Cifrado JSON extremo a extremo</span>
        </div>

        <div class="rounded-xl border border-pd-border bg-pd-surface px-5 py-3 flex items-center gap-3">
            <span class="text-[14px] font-semibold text-pd-text-primary">{{ expediente.paciente.nombre_completo }}</span>
            <span class="text-[12px] font-mono text-pd-text-secondary">{{ expediente.paciente.carnet }}</span>
        </div>

        <div>
            <form novalidate @submit.prevent="submit" class="space-y-5">
                <div
                    v-if="hasAnyErrors || clientBanner"
                    class="rounded-xl border border-pd-danger/40 bg-pd-danger-soft px-4 py-3"
                    role="alert"
                    aria-live="polite"
                >
                    <p class="text-[13px] font-semibold text-pd-danger">
                        {{ clientBanner || 'No se pudo guardar la consulta. Revisa los campos señalados:' }}
                    </p>
                    <ul v-if="errorSummary.length" class="mt-2 space-y-1">
                        <li v-for="item in errorSummary" :key="item.key">
                            <button
                                type="button"
                                class="text-left text-[12px] text-pd-danger hover:underline"
                                @click="focusField(item.key)"
                            >
                                → {{ item.label }}: {{ item.message }}
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="grid grid-cols-1 gap-5" :class="esPrimeraConsulta ? 'lg:grid-cols-[minmax(0,1fr)_380px]' : ''">
                    <div v-if="esPrimeraConsulta">
                <!-- Evaluación Inicial -->
                <div v-if="esPrimeraConsulta" class="border border-pd-border rounded-xl overflow-hidden shadow-sm">
                    <div class="bg-pd-surface-subtle px-4 py-3 border-b border-pd-border">
                        <h2 class="text-[14px] font-semibold text-pd-text-primary flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-pd-brand" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
                            </svg>
                            Evaluación Inicial (Requerido - Primera Cita)
                        </h2>
                        <p class="text-[12px] text-pd-text-secondary mt-1">
                            Los campos marcados con <span class="text-pd-danger" aria-hidden="true">*</span><span class="sr-only">asterisco</span> son obligatorios.
                        </p>
                    </div>
                    <div class="p-0 bg-pd-surface">
                        <div
                            v-for="(fields, index) in ACCORDION_FIELDS"
                            :key="index"
                            :class="Number(index) < 2 ? 'border-b border-pd-border' : ''"
                        >
                            <button
                                type="button"
                                class="w-full text-left px-4 py-3 font-medium text-[13px] flex justify-between items-center hover:bg-pd-surface-subtle transition-colors"
                                :class="accordionPendingCount(Number(index)) > 0 ? 'text-pd-danger border-l-4 border-l-pd-danger' : 'text-pd-text-primary'"
                                :aria-expanded="activeAccordion === Number(index)"
                                @click="activeAccordion = Number(index)"
                            >
                                <span class="inline-flex items-center gap-2">
                                    <svg
                                        v-if="accordionPendingCount(Number(index)) > 0"
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4 text-pd-danger shrink-0"
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
                                <svg :class="{ 'rotate-180': activeAccordion === Number(index) }" class="h-4 w-4 text-pd-text-secondary transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div v-show="activeAccordion === Number(index)" class="p-4 bg-pd-surface grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div v-for="campo in fields" :key="campo">
                                    <label
                                        :for="fieldId('evaluacion_inicial.' + campo)"
                                        class="block text-[12px] font-semibold text-pd-text-primary mb-1.5"
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
                                        <span class="text-pd-danger" aria-hidden="true"> *</span>
                                        <span class="sr-only">(obligatorio)</span>
                                    </label>
                                    <input
                                        :id="fieldId('evaluacion_inicial.' + campo)"
                                        v-model="form.evaluacion_inicial[campo]"
                                        type="text"
                                        aria-required="true"
                                        :aria-invalid="hasError('evaluacion_inicial.' + campo) ? 'true' : 'false'"
                                        :aria-describedby="hasError('evaluacion_inicial.' + campo) ? fieldId('evaluacion_inicial.' + campo) + '-error' : undefined"
                                        class="w-full rounded-lg border bg-pd-surface-subtle px-3 py-2 text-[13px] focus:border-pd-brand focus:ring-0"
                                        :class="hasError('evaluacion_inicial.' + campo) ? 'border-pd-danger' : 'border-pd-border'"
                                    />
                                    <p
                                        v-if="hasError('evaluacion_inicial.' + campo)"
                                        :id="fieldId('evaluacion_inicial.' + campo) + '-error'"
                                        class="text-pd-danger text-[11px] mt-1"
                                    >
                                        {{ errorMessage('evaluacion_inicial.' + campo) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                    </div>
                    <div class="space-y-5">
                        <section class="rounded-xl border border-pd-border bg-pd-surface p-5 shadow-sm">
                            <h2 class="mb-3 text-[15px] font-semibold text-pd-text-primary">Datos de la atención</h2>
                            <div class="space-y-4">
                <!-- Fecha de consulta -->
                <div>
                    <label for="fecha_consulta" class="block text-[13px] font-semibold text-pd-text-primary mb-1.5">
                        Fecha de la consulta <span class="text-pd-danger" aria-hidden="true">*</span><span class="sr-only">(obligatorio)</span>
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
                        class="w-full rounded-lg border bg-pd-surface-subtle px-3 py-2 text-[14px] text-pd-text-primary focus:border-pd-brand focus:ring-0"
                        :class="hasError('fecha_consulta') ? 'border-pd-danger' : 'border-pd-border'"
                    />
                    <p class="text-[11px] text-pd-text-secondary mt-1">Fecha de la atención clínica. No puede ser futura.</p>
                    <p v-if="hasError('fecha_consulta')" id="fecha_consulta-error" class="text-pd-danger text-[12px] mt-1">{{ errorMessage('fecha_consulta') }}</p>
                </div>
                    <div>
                        <label for="tecnica_utilizada" class="block text-[13px] font-semibold text-pd-text-primary mb-1.5">
                            Técnica Utilizada <span class="text-pd-danger" aria-hidden="true">*</span><span class="sr-only">(obligatorio)</span>
                        </label>
                        <input
                            id="tecnica_utilizada"
                            v-model="form.tecnica_utilizada"
                            type="text"
                            aria-required="true"
                            :aria-invalid="hasError('tecnica_utilizada') ? 'true' : 'false'"
                            :aria-describedby="hasError('tecnica_utilizada') ? 'tecnica_utilizada-error' : undefined"
                            class="w-full rounded-lg border bg-pd-surface-subtle px-3 py-2 text-[14px] text-pd-text-primary focus:border-pd-brand focus:ring-0"
                            :class="hasError('tecnica_utilizada') ? 'border-pd-danger' : 'border-pd-border'"
                        />
                        <p v-if="hasError('tecnica_utilizada')" id="tecnica_utilizada-error" class="text-pd-danger text-[12px] mt-1">{{ errorMessage('tecnica_utilizada') }}</p>
                    </div>
                            </div>
                        </section>
                        <section class="rounded-xl border border-pd-border bg-pd-surface p-5 shadow-sm">
                            <h2 class="mb-3 text-[15px] font-semibold text-pd-text-primary">Registro clínico</h2>
                            <div class="space-y-4">
                <!-- Motivo de Consulta -->
                <div>
                    <label for="motivo_consulta" class="block text-[13px] font-semibold text-pd-text-primary mb-1.5">
                        Motivo de Consulta <span class="text-pd-danger" aria-hidden="true">*</span><span class="sr-only">(obligatorio)</span>
                    </label>
                    <textarea
                        id="motivo_consulta"
                        v-model="form.motivo_consulta"
                        rows="2"
                        aria-required="true"
                        :aria-invalid="hasError('motivo_consulta') ? 'true' : 'false'"
                        :aria-describedby="hasError('motivo_consulta') ? 'motivo_consulta-error' : undefined"
                        class="w-full rounded-lg border bg-pd-surface-subtle px-3 py-2.5 text-[14px] text-pd-text-primary focus:border-pd-brand focus:ring-0"
                        :class="hasError('motivo_consulta') ? 'border-pd-danger' : 'border-pd-border'"
                    ></textarea>
                    <p v-if="hasError('motivo_consulta')" id="motivo_consulta-error" class="text-pd-danger text-[12px] mt-1">{{ errorMessage('motivo_consulta') }}</p>
                </div>
                    <div>
                        <label for="diagnostico" class="block text-[13px] font-semibold text-pd-text-primary mb-1.5">
                            Diagnóstico
                        </label>
                        <input
                            id="diagnostico"
                            v-model="form.diagnostico"
                            type="text"
                            :aria-invalid="hasError('diagnostico') ? 'true' : 'false'"
                            :aria-describedby="hasError('diagnostico') ? 'diagnostico-error dx-obs-hint' : 'dx-obs-hint'"
                            class="w-full rounded-lg border bg-pd-surface-subtle px-3 py-2 text-[14px] text-pd-text-primary focus:border-pd-brand focus:ring-0"
                            :class="hasError('diagnostico') ? 'border-pd-danger' : 'border-pd-border'"
                        />
                        <p v-if="hasError('diagnostico')" id="diagnostico-error" class="text-pd-danger text-[12px] mt-1">{{ errorMessage('diagnostico') }}</p>
                    </div>
                <p id="dx-obs-hint" class="text-[12px] text-pd-text-secondary -mt-2">
                        Completa el diagnóstico o las notas clínicas; al menos uno es obligatorio <span class="text-pd-danger" aria-hidden="true">*</span><span class="sr-only">(obligatorio al menos uno)</span>
                </p>
                <div>
                    <label for="notas_clinicas" class="block text-[13px] font-semibold text-pd-text-primary mb-1.5">
                        Observación / Notas clínicas
                    </label>
                    <textarea
                        id="notas_clinicas"
                        v-model="form.notas_clinicas"
                        rows="4"
                        :aria-invalid="hasError('notas_clinicas') ? 'true' : 'false'"
                        :aria-describedby="hasError('notas_clinicas') ? 'notas_clinicas-error dx-obs-hint' : 'dx-obs-hint'"
                        class="w-full rounded-lg border bg-pd-surface-subtle px-3 py-2.5 text-[14px] text-pd-text-primary focus:border-pd-brand focus:ring-0"
                        :class="hasError('notas_clinicas') ? 'border-pd-danger' : 'border-pd-border'"
                    ></textarea>
                    <p v-if="hasError('notas_clinicas')" id="notas_clinicas-error" class="text-pd-danger text-[12px] mt-1">{{ errorMessage('notas_clinicas') }}</p>
                </div>
                            </div>
                        </section>
                        <section class="rounded-xl border border-pd-border bg-pd-surface p-5 shadow-sm">
                            <h2 class="mb-3 text-[15px] font-semibold text-pd-text-primary">Referencia externa</h2>
                            <div class="space-y-4">
                                <fieldset
                                    id="referencia_externa"
                                    tabindex="-1"
                                    class="rounded-lg border border-pd-border bg-pd-surface-subtle px-3 py-2"
                                    :aria-invalid="hasError('referencia_externa') ? 'true' : 'false'"
                                    :aria-describedby="hasError('referencia_externa') ? 'referencia_externa-error' : undefined"
                                >
                                    <legend class="text-[13px] font-semibold text-pd-text-primary">¿Se refiere a una institución externa?</legend>
                                    <div class="mt-2 flex gap-6">
                                        <label for="referencia_externa-si" class="inline-flex items-center gap-2 text-[13px] text-pd-text-primary">
                                            <input id="referencia_externa-si" v-model="form.referencia_externa" type="radio" name="referencia_externa" :value="true" class="text-pd-brand focus:ring-pd-brand" />
                                            Sí
                                        </label>
                                        <label for="referencia_externa-no" class="inline-flex items-center gap-2 text-[13px] text-pd-text-primary">
                                            <input id="referencia_externa-no" v-model="form.referencia_externa" type="radio" name="referencia_externa" :value="false" class="text-pd-brand focus:ring-pd-brand" />
                                            No
                                        </label>
                                    </div>
                                    <p v-if="hasError('referencia_externa')" id="referencia_externa-error" class="text-pd-danger text-[12px] mt-1">{{ errorMessage('referencia_externa') }}</p>
                                </fieldset>

                                <template v-if="form.referencia_externa === true">
                                    <div>
                                        <label for="tipo_referencia" class="block text-[13px] font-semibold text-pd-text-primary mb-1.5">
                                            Tipo <span class="text-pd-danger" aria-hidden="true">*</span><span class="sr-only">(obligatorio)</span>
                                        </label>
                                        <select
                                            id="tipo_referencia"
                                            v-model="form.tipo_referencia"
                                            aria-required="true"
                                            :aria-invalid="hasError('tipo_referencia') ? 'true' : 'false'"
                                    :aria-describedby="hasError('tipo_referencia') ? 'tipo_referencia-error' : undefined"
                                            class="w-full rounded-lg border bg-pd-surface-subtle px-3 py-2 text-[14px] text-pd-text-primary focus:border-pd-brand focus:ring-0"
                                            :class="hasError('tipo_referencia') ? 'border-pd-danger' : 'border-pd-border'"
                                        >
                                            <option value="" disabled>Selecciona un tipo</option>
                                            <option v-for="tipo in TIPOS_REFERENCIA" :key="tipo.value" :value="tipo.value">{{ tipo.label }}</option>
                                        </select>
                                        <p v-if="hasError('tipo_referencia')" id="tipo_referencia-error" class="text-pd-danger text-[12px] mt-1">{{ errorMessage('tipo_referencia') }}</p>
                                    </div>
                                    <div>
                                        <label for="institucion_destino" class="block text-[13px] font-semibold text-pd-text-primary mb-1.5">
                                            Institución de destino <span class="text-pd-danger" aria-hidden="true">*</span><span class="sr-only">(obligatorio)</span>
                                        </label>
                                        <input
                                            id="institucion_destino"
                                            v-model="form.institucion_destino"
                                            type="text"
                                            maxlength="150"
                                            placeholder="Ej.: Hospital Nacional Rosales"
                                            aria-required="true"
                                            :aria-invalid="hasError('institucion_destino') ? 'true' : 'false'"
                                    :aria-describedby="hasError('institucion_destino') ? 'institucion_destino-error' : undefined"
                                            class="w-full rounded-lg border bg-pd-surface-subtle px-3 py-2 text-[14px] text-pd-text-primary focus:border-pd-brand focus:ring-0"
                                            :class="hasError('institucion_destino') ? 'border-pd-danger' : 'border-pd-border'"
                                        />
                                        <p v-if="hasError('institucion_destino')" id="institucion_destino-error" class="text-pd-danger text-[12px] mt-1">{{ errorMessage('institucion_destino') }}</p>
                                    </div>
                                    <fieldset
                                        id="prioridad_referencia"
                                        tabindex="-1"
                                        class="rounded-lg border border-pd-border bg-pd-surface-subtle px-3 py-2"
                                        :aria-invalid="hasError('prioridad_referencia') ? 'true' : 'false'"
                                    :aria-describedby="hasError('prioridad_referencia') ? 'prioridad_referencia-error' : undefined"
                                    >
                                        <legend class="text-[13px] font-semibold text-pd-text-primary">
                                            Prioridad <span class="text-pd-danger" aria-hidden="true">*</span><span class="sr-only">(obligatorio)</span>
                                        </legend>
                                        <div class="mt-2 flex gap-6">
                                            <label for="prioridad_referencia-urgente" class="inline-flex items-center gap-2 text-[13px] text-pd-text-primary">
                                                <input id="prioridad_referencia-urgente" v-model="form.prioridad_referencia" type="radio" name="prioridad_referencia" value="urgente" class="text-pd-brand focus:ring-pd-brand" />
                                                Urgente
                                            </label>
                                            <label for="prioridad_referencia-no_urgente" class="inline-flex items-center gap-2 text-[13px] text-pd-text-primary">
                                                <input id="prioridad_referencia-no_urgente" v-model="form.prioridad_referencia" type="radio" name="prioridad_referencia" value="no_urgente" class="text-pd-brand focus:ring-pd-brand" />
                                                No urgente
                                            </label>
                                        </div>
                                        <p v-if="hasError('prioridad_referencia')" id="prioridad_referencia-error" class="text-pd-danger text-[12px] mt-1">{{ errorMessage('prioridad_referencia') }}</p>
                                    </fieldset>
                                    <div>
                                        <label for="motivo_referencia" class="block text-[13px] font-semibold text-pd-text-primary mb-1.5">
                                            Motivo de la referencia <span class="text-pd-danger" aria-hidden="true">*</span><span class="sr-only">(obligatorio)</span>
                                        </label>
                                        <textarea
                                            id="motivo_referencia"
                                            v-model="form.motivo_referencia"
                                            rows="3"
                                            maxlength="2000"
                                            placeholder="Describe por qué se refiere al paciente (mínimo 10 caracteres)"
                                            aria-required="true"
                                            :aria-invalid="hasError('motivo_referencia') ? 'true' : 'false'"
                                    :aria-describedby="hasError('motivo_referencia') ? 'motivo_referencia-error' : undefined"
                                            class="w-full rounded-lg border bg-pd-surface-subtle px-3 py-2.5 text-[14px] text-pd-text-primary focus:border-pd-brand focus:ring-0"
                                            :class="hasError('motivo_referencia') ? 'border-pd-danger' : 'border-pd-border'"
                                        ></textarea>
                                        <p v-if="hasError('motivo_referencia')" id="motivo_referencia-error" class="text-pd-danger text-[12px] mt-1">{{ errorMessage('motivo_referencia') }}</p>
                                    </div>
                                </template>
                            </div>
                        </section>
                        <section class="rounded-xl border border-pd-border bg-pd-surface p-5 shadow-sm">
                            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4">
                    <fieldset
                        id="crisis_psicologica"
                        tabindex="-1"
                        class="rounded-lg border border-pd-border bg-pd-surface-subtle px-3 py-2"
                        :aria-invalid="hasError('crisis_psicologica') ? 'true' : 'false'"
                        :aria-describedby="hasError('crisis_psicologica') ? 'crisis_psicologica-error' : undefined"
                    >
                        <legend class="text-[13px] font-semibold text-pd-text-primary">
                            Crisis psicológica <span class="text-pd-danger" aria-hidden="true">*</span><span class="sr-only">(obligatorio)</span>
                        </legend>
                        <div class="mt-2 flex gap-6">
                            <label for="crisis_psicologica-si" class="inline-flex items-center gap-2 text-[13px] text-pd-text-primary">
                                <input
                                    id="crisis_psicologica-si"
                                    v-model="form.crisis_psicologica"
                                    type="radio"
                                    name="crisis_psicologica"
                                    required
                                    :value="true"
                                    :aria-invalid="hasError('crisis_psicologica') ? 'true' : 'false'"
                                    :aria-describedby="hasError('crisis_psicologica') ? 'crisis_psicologica-error' : undefined"
                                    class="text-pd-brand focus:ring-pd-brand"
                                />
                                Sí
                            </label>
                            <label for="crisis_psicologica-no" class="inline-flex items-center gap-2 text-[13px] text-pd-text-primary">
                                <input
                                    id="crisis_psicologica-no"
                                    v-model="form.crisis_psicologica"
                                    type="radio"
                                    name="crisis_psicologica"
                                    required
                                    :value="false"
                                    :aria-invalid="hasError('crisis_psicologica') ? 'true' : 'false'"
                                    :aria-describedby="hasError('crisis_psicologica') ? 'crisis_psicologica-error' : undefined"
                                    class="text-pd-brand focus:ring-pd-brand"
                                />
                                No
                            </label>
                        </div>
                        <p v-if="hasError('crisis_psicologica')" id="crisis_psicologica-error" class="text-pd-danger text-[12px] mt-1">{{ errorMessage('crisis_psicologica') }}</p>
                    </fieldset>

                    <fieldset
                        id="indicadores_tendencia_suicida"
                        tabindex="-1"
                        class="rounded-lg border border-pd-border bg-pd-surface-subtle px-3 py-2"
                        :aria-invalid="hasError('indicadores_tendencia_suicida') ? 'true' : 'false'"
                        :aria-describedby="hasError('indicadores_tendencia_suicida') ? 'indicadores_tendencia_suicida-error' : undefined"
                    >
                        <legend class="text-[13px] font-semibold text-pd-text-primary">Indicadores de tendencia suicida</legend>
                        <div v-if="props.indicadoresTendenciaSuicida.length" class="mt-2 space-y-2">
                            <label
                                v-for="(indicador, index) in props.indicadoresTendenciaSuicida"
                                :key="indicador"
                                :for="'indicador-tendencia-' + index"
                                class="inline-flex items-center gap-2 text-[13px] text-pd-text-primary"
                            >
                                <input
                                    :id="'indicador-tendencia-' + index"
                                    v-model="form.indicadores_tendencia_suicida"
                                    type="checkbox"
                                    :value="indicador"
                                    :aria-invalid="hasError('indicadores_tendencia_suicida') ? 'true' : 'false'"
                                    :aria-describedby="hasError('indicadores_tendencia_suicida') ? 'indicadores_tendencia_suicida-error' : undefined"
                                    class="rounded text-pd-brand focus:ring-pd-brand"
                                />
                                <span class="capitalize">{{ indicador.replaceAll('_', ' ') }}</span>
                            </label>
                        </div>
                        <p v-else class="mt-2 text-[12px] text-pd-text-secondary">
                            No hay indicadores de tendencia suicida configurados.
                        </p>
                        <p v-if="hasError('indicadores_tendencia_suicida')" id="indicadores_tendencia_suicida-error" class="text-pd-danger text-[12px] mt-1">{{ errorMessage('indicadores_tendencia_suicida') }}</p>
                    </fieldset>
                </div>
                            </div>
                        </section>
                    </div>
                </div>

                <section class="rounded-xl border border-pd-border bg-pd-surface p-5 shadow-sm">
                <div>
                    <label for="plan_atencion" class="block text-[13px] font-semibold text-pd-text-primary mb-1.5">
                        Plan de atención <span class="text-pd-danger" aria-hidden="true">*</span><span class="sr-only">(obligatorio)</span>
                    </label>
                    <textarea
                        id="plan_atencion"
                        v-model="form.plan_atencion"
                        rows="3"
                        maxlength="1000"
                        placeholder="Indica el plan de atención acordado en esta consulta (mínimo 10 caracteres)"
                        aria-required="true"
                        :aria-invalid="hasError('plan_atencion') ? 'true' : 'false'"
                        :aria-describedby="hasError('plan_atencion') ? 'plan_atencion-error' : undefined"
                        class="w-full rounded-lg border bg-pd-surface-subtle px-3 py-2.5 text-[14px] text-pd-text-primary focus:border-pd-brand focus:ring-0"
                        :class="hasError('plan_atencion') ? 'border-pd-danger' : 'border-pd-border'"
                    ></textarea>
                    <p v-if="hasError('plan_atencion')" id="plan_atencion-error" class="text-pd-danger text-[12px] mt-1">{{ errorMessage('plan_atencion') }}</p>
                </div>
                </section>

                <div class="pt-4 border-t border-pd-border flex items-center justify-end gap-3">
                    <Link
                        :href="'/pacientes/' + expediente.paciente.carnet"
                        class="px-4 py-2 text-[13px] font-medium text-pd-text-secondary hover:bg-pd-surface-subtle rounded-lg transition-colors border border-transparent hover:border-pd-border"
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
