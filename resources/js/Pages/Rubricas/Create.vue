<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Breadcrumbs from '@/Components/UI/Breadcrumbs.vue';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';
import { formatElSalvadorDateTimeInput } from '@/utils/dates';

defineOptions({ layout: ClinicalLayout });

const props = defineProps({
    rubrica: { type: Object, required: true },
    latestVersion: { type: Object, default: null },
});

const hasPreviousVersion = Boolean(props.latestVersion);
const nextVersionNumber = computed(() => Number(props.latestVersion?.numero_version || 0) + 1);
const variableDefaults = [
    { tipo_senal: 'ausencia_cita', clave_indicador: null, modo_calculo: 'frecuencia', ventana_dias: 30, ponderacion: 1, umbral: 2 },
    { tipo_senal: 'crisis_psicologica', clave_indicador: null, modo_calculo: 'frecuencia', ventana_dias: 90, ponderacion: 1, umbral: 1 },
    { tipo_senal: 'tendencia_suicida', clave_indicador: 'ideas_suicidas', modo_calculo: 'puntaje', ventana_dias: null, ponderacion: 1, umbral: 1 },
];
const latestVariables = props.latestVersion?.variables ?? [];
const initialVariables = [
    ...latestVariables.map(variable => ({
        ...variable,
        clave_indicador: variable.tipo_senal === 'tendencia_suicida'
            ? variable.clave_indicador || 'ideas_suicidas'
            : null,
        modo_calculo: variable.tipo_senal === 'tendencia_suicida' ? 'puntaje' : variable.modo_calculo,
        ventana_dias: variable.tipo_senal === 'tendencia_suicida' || variable.modo_calculo !== 'frecuencia'
            ? null
            : variable.ventana_dias,
    })),
    ...variableDefaults
        .filter(defaults => !latestVariables.some(variable => variable.tipo_senal === defaults.tipo_senal))
        .map(defaults => ({ ...defaults })),
];
const newVariableType = ref('ausencia_cita');

function suggestedEffectiveDate() {
    const now = new Date();
    let next = new Date(now.getTime() + 60_000);
    const previous = props.latestVersion?.vigente_desde ? new Date(props.latestVersion.vigente_desde) : null;
    if (previous && !Number.isNaN(previous.getTime()) && previous >= next) {
        next = new Date(previous.getTime() + 60_000);
    }
    return formatElSalvadorDateTimeInput(next);
}

const form = useForm({
    vigente_desde: suggestedEffectiveDate(),
    umbral_puntaje_global: null,
    validada_en: '',
    referencia_validacion: '',
    justificacion_cambio: hasPreviousVersion ? '' : null,
    variables: initialVariables,
});

function fieldError(key) {
    return form.errors[key] || '';
}

function signalLabel(type) {
    return {
        ausencia_cita: 'Ausencia a cita',
        crisis_psicologica: 'Crisis psicológica',
        tendencia_suicida: 'Tendencia suicida',
    }[type] ?? type;
}

function variableCount(type) {
    return form.variables.filter(variable => variable.tipo_senal === type).length;
}

function addVariable() {
    const defaults = variableDefaults.find(variable => variable.tipo_senal === newVariableType.value);
    if (defaults) form.variables.push({ ...defaults });
}

function removeVariable(index) {
    const variable = form.variables[index];
    if (variableCount(variable.tipo_senal) > 1) form.variables.splice(index, 1);
}

function updateType(variable, type) {
    if (variable.tipo_senal !== type && variableCount(variable.tipo_senal) <= 1) return;
    variable.tipo_senal = type;
    if (type === 'tendencia_suicida') {
        variable.clave_indicador = variable.clave_indicador || 'ideas_suicidas';
        variable.modo_calculo = 'puntaje';
        variable.ventana_dias = null;
        return;
    }
    variable.clave_indicador = null;
    if (variable.modo_calculo === 'puntaje') {
        variable.modo_calculo = 'frecuencia';
        variable.ventana_dias = variableDefaults.find(defaults => defaults.tipo_senal === type)?.ventana_dias ?? null;
    } else if (variable.modo_calculo !== 'frecuencia') {
        variable.ventana_dias = null;
    }
}

function updateMode(variable) {
    if (variable.tipo_senal === 'tendencia_suicida') {
        variable.modo_calculo = 'puntaje';
        variable.clave_indicador = variable.clave_indicador || 'ideas_suicidas';
        variable.ventana_dias = null;
        return;
    }
    variable.clave_indicador = null;
    if (variable.modo_calculo === 'frecuencia') {
        variable.ventana_dias ??= variableDefaults.find(defaults => defaults.tipo_senal === variable.tipo_senal)?.ventana_dias ?? null;
    } else {
        variable.ventana_dias = null;
    }
}

function submit() {
    form.transform(data => ({
        ...data,
        variables: data.variables.map(variable => ({
            tipo_senal: variable.tipo_senal,
            clave_indicador: variable.tipo_senal === 'tendencia_suicida' ? variable.clave_indicador : null,
            modo_calculo: variable.tipo_senal === 'tendencia_suicida' ? 'puntaje' : variable.modo_calculo,
            ventana_dias: variable.tipo_senal === 'tendencia_suicida' || variable.modo_calculo !== 'frecuencia'
                ? null
                : variable.ventana_dias,
            ponderacion: variable.ponderacion,
            umbral: variable.umbral,
        })),
    })).post('/rubricas/versiones', { preserveScroll: true });
}
</script>

<template>
    <Head title="Crear versión de rúbrica" />

    <div class="space-y-5">
        <Breadcrumbs :items="[
            { label: 'Panel clínico', href: '/dashboard' },
            { label: 'Rúbrica de riesgo', href: '/rubricas' },
            { label: `Nueva versión ${nextVersionNumber}` },
        ]" />

        <header class="rounded-xl border border-[var(--nord4)] bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <h1 class="text-xl font-semibold tracking-tight text-[var(--nord0)]">
                    {{ rubrica.nombre || 'Rúbrica institucional' }}
                </h1>
                <span class="rounded-full border border-[var(--nord4)] bg-[var(--surface-subtle)] px-3 py-1 text-xs font-semibold text-[var(--nord3)]">
                    Nueva versión {{ nextVersionNumber }}
                </span>
            </div>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-[var(--nord3)]">
                Las versiones existentes no se modifican. Registra la validación institucional y define cada señal por separado.
            </p>
        </header>

        <div v-if="Object.keys(form.errors).length" role="alert" aria-live="assertive" class="rounded-lg border border-[var(--aurora-red)]/30 bg-[var(--aurora-red)]/10 px-4 py-3 text-sm text-[var(--nord0)]">
            No se pudo guardar la versión. Revisa los campos señalados e inténtalo de nuevo.
        </div>

        <form class="space-y-5" novalidate @submit.prevent="submit">
            <section aria-labelledby="validation-heading" class="rounded-xl border border-[var(--nord4)] bg-white shadow-sm">
                <div class="border-b border-[var(--nord4)] bg-[var(--surface-header)] px-5 py-4">
                    <h2 id="validation-heading" class="text-base font-semibold text-[var(--nord0)]">Vigencia y validación</h2>
                    <p class="mt-1 text-sm text-[var(--nord3)]">La referencia escrita de validación es obligatoria antes de registrar la versión.</p>
                </div>
                <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
                    <div>
                        <label for="vigente-desde" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Vigente desde (hora de El Salvador)</label>
                        <input
                            id="vigente-desde"
                            v-model="form.vigente_desde"
                            type="datetime-local"
                            required
                            :aria-invalid="Boolean(fieldError('vigente_desde'))"
                            :aria-describedby="fieldError('vigente_desde') ? 'vigente-desde-error' : undefined"
                            class="min-h-11 w-full rounded-lg border bg-white px-3 py-2 text-sm text-[var(--nord0)] outline-none focus-visible:ring-2 focus-visible:ring-[var(--nord10)]/30"
                            :class="fieldError('vigente_desde') ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)] focus:border-[var(--nord10)]'"
                        />
                        <p v-if="fieldError('vigente_desde')" id="vigente-desde-error" class="mt-1.5 text-sm text-[var(--aurora-red)]">{{ fieldError('vigente_desde') }}</p>
                    </div>
                    <div>
                        <label for="validada-en" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Fecha de validación</label>
                        <input
                            id="validada-en"
                            v-model="form.validada_en"
                            type="date"
                            required
                            :aria-invalid="Boolean(fieldError('validada_en'))"
                            :aria-describedby="fieldError('validada_en') ? 'validada-en-error' : undefined"
                            class="min-h-11 w-full rounded-lg border bg-white px-3 py-2 text-sm text-[var(--nord0)] outline-none focus-visible:ring-2 focus-visible:ring-[var(--nord10)]/30"
                            :class="fieldError('validada_en') ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)] focus:border-[var(--nord10)]'"
                        />
                        <p v-if="fieldError('validada_en')" id="validada-en-error" class="mt-1.5 text-sm text-[var(--aurora-red)]">{{ fieldError('validada_en') }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="referencia-validacion" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Referencia documental de validación</label>
                        <input
                            id="referencia-validacion"
                            v-model="form.referencia_validacion"
                            type="text"
                            required
                            maxlength="255"
                            autocomplete="off"
                            :aria-invalid="Boolean(fieldError('referencia_validacion'))"
                            :aria-describedby="fieldError('referencia_validacion') ? 'referencia-validacion-error' : undefined"
                            class="min-h-11 w-full rounded-lg border bg-white px-3 py-2 text-sm text-[var(--nord0)] outline-none focus-visible:ring-2 focus-visible:ring-[var(--nord10)]/30"
                            :class="fieldError('referencia_validacion') ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)] focus:border-[var(--nord10)]'"
                        />
                        <p v-if="fieldError('referencia_validacion')" id="referencia-validacion-error" class="mt-1.5 text-sm text-[var(--aurora-red)]">{{ fieldError('referencia_validacion') }}</p>
                    </div>
                    <div v-if="hasPreviousVersion" class="sm:col-span-2">
                        <label for="justificacion-cambio" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Justificación del cambio</label>
                        <textarea
                            id="justificacion-cambio"
                            v-model="form.justificacion_cambio"
                            rows="3"
                            required
                            maxlength="2000"
                            :aria-invalid="Boolean(fieldError('justificacion_cambio'))"
                            :aria-describedby="fieldError('justificacion_cambio') ? 'justificacion-cambio-error' : undefined"
                            class="w-full rounded-lg border bg-white px-3 py-2 text-sm text-[var(--nord0)] outline-none focus-visible:ring-2 focus-visible:ring-[var(--nord10)]/30"
                            :class="fieldError('justificacion_cambio') ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)] focus:border-[var(--nord10)]'"
                        />
                        <p v-if="fieldError('justificacion_cambio')" id="justificacion-cambio-error" class="mt-1.5 text-sm text-[var(--aurora-red)]">{{ fieldError('justificacion_cambio') }}</p>
                    </div>
                </div>
            </section>

            <section aria-labelledby="variables-heading" class="rounded-xl border border-[var(--nord4)] bg-white shadow-sm">
                <div class="border-b border-[var(--nord4)] bg-[var(--surface-header)] px-5 py-4">
                    <h2 id="variables-heading" class="text-base font-semibold text-[var(--nord0)]">Señales y umbrales</h2>
                    <p class="mt-1 text-sm text-[var(--nord3)]">Cada señal se evalúa de forma independiente; no hay puntaje global.</p>
                </div>
                <p v-if="fieldError('variables')" role="alert" class="px-5 pt-4 text-sm text-[var(--aurora-red)]">{{ fieldError('variables') }}</p>

                <div class="flex flex-col gap-3 border-b border-[var(--nord5)] p-5 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <label for="new-variable-type" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Tipo de señal adicional</label>
                        <select
                            id="new-variable-type"
                            v-model="newVariableType"
                            class="min-h-11 w-full rounded-lg border border-[var(--nord4)] bg-white px-3 py-2 text-sm text-[var(--nord0)] outline-none focus-visible:ring-2 focus-visible:ring-[var(--nord10)]/30 sm:max-w-sm"
                        >
                            <option v-for="defaults in variableDefaults" :key="defaults.tipo_senal" :value="defaults.tipo_senal">
                                {{ signalLabel(defaults.tipo_senal) }}
                            </option>
                        </select>
                    </div>
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center justify-center rounded-lg border border-[var(--nord10)] px-4 py-2 text-sm font-semibold text-[var(--nord10)] transition-colors hover:bg-[var(--nord10)]/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)]"
                        @click="addVariable"
                    >
                        Agregar señal
                    </button>
                </div>

                <ol class="divide-y divide-[var(--nord5)]">
                    <li v-for="(variable, index) in form.variables" :key="index" class="space-y-4 p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <div class="flex-1">
                                <label :for="`variable-${index}-tipo`" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Tipo de señal</label>
                                <select
                                    :id="`variable-${index}-tipo`"
                                    :value="variable.tipo_senal"
                                    :aria-invalid="Boolean(fieldError(`variables.${index}.tipo_senal`))"
                                    :aria-describedby="fieldError(`variables.${index}.tipo_senal`) ? `variable-${index}-tipo-error` : undefined"
                                    class="min-h-11 w-full rounded-lg border bg-white px-3 py-2 text-sm text-[var(--nord0)] outline-none focus-visible:ring-2 focus-visible:ring-[var(--nord10)]/30 sm:max-w-sm"
                                    :class="fieldError(`variables.${index}.tipo_senal`) ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)]'"
                                    @change="updateType(variable, $event.target.value)"
                                >
                                    <option
                                        v-for="defaults in variableDefaults"
                                        :key="defaults.tipo_senal"
                                        :value="defaults.tipo_senal"
                                        :disabled="defaults.tipo_senal !== variable.tipo_senal && variableCount(variable.tipo_senal) <= 1"
                                    >
                                        {{ signalLabel(defaults.tipo_senal) }}
                                    </option>
                                </select>
                                <p v-if="fieldError(`variables.${index}.tipo_senal`)" :id="`variable-${index}-tipo-error`" class="mt-1.5 text-sm text-[var(--aurora-red)]">
                                    {{ fieldError(`variables.${index}.tipo_senal`) }}
                                </p>
                            </div>
                            <button
                                type="button"
                                :disabled="variableCount(variable.tipo_senal) <= 1"
                                :aria-label="`Eliminar señal: ${signalLabel(variable.tipo_senal)}`"
                                class="inline-flex min-h-11 items-center justify-center rounded-lg border border-[var(--aurora-red)]/40 px-4 py-2 text-sm font-semibold text-[var(--aurora-red)] transition-colors hover:bg-[var(--aurora-red)]/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--aurora-red)] disabled:cursor-not-allowed disabled:opacity-50"
                                @click="removeVariable(index)"
                            >
                                Eliminar señal
                            </button>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <div v-if="variable.tipo_senal === 'tendencia_suicida'">
                                <label :for="`variable-${index}-clave-indicador`" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Indicador</label>
                                <input
                                    :id="`variable-${index}-clave-indicador`"
                                    v-model="variable.clave_indicador"
                                    type="text"
                                    required
                                    maxlength="120"
                                    autocomplete="off"
                                    :aria-invalid="Boolean(fieldError(`variables.${index}.clave_indicador`))"
                                    :aria-describedby="fieldError(`variables.${index}.clave_indicador`) ? `variable-${index}-clave-indicador-error` : undefined"
                                    class="min-h-11 w-full rounded-lg border bg-white px-3 py-2 text-sm text-[var(--nord0)] outline-none focus-visible:ring-2 focus-visible:ring-[var(--nord10)]/30"
                                    :class="fieldError(`variables.${index}.clave_indicador`) ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)] focus:border-[var(--nord10)]'"
                                />
                                <p v-if="fieldError(`variables.${index}.clave_indicador`)" :id="`variable-${index}-clave-indicador-error`" class="mt-1.5 text-sm text-[var(--aurora-red)]">{{ fieldError(`variables.${index}.clave_indicador`) }}</p>
                            </div>
                            <div>
                                <label :for="`variable-${index}-modo`" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Modo de cálculo</label>
                                <select
                                    :id="`variable-${index}-modo`"
                                    v-model="variable.modo_calculo"
                                    required
                                    :disabled="variable.tipo_senal === 'tendencia_suicida'"
                                    :aria-invalid="Boolean(fieldError(`variables.${index}.modo_calculo`))"
                                    :aria-describedby="fieldError(`variables.${index}.modo_calculo`) ? `variable-${index}-modo-error` : undefined"
                                    class="min-h-11 w-full rounded-lg border bg-white px-3 py-2 text-sm text-[var(--nord0)] outline-none focus-visible:ring-2 focus-visible:ring-[var(--nord10)]/30 disabled:bg-[var(--surface-subtle)]"
                                    :class="fieldError(`variables.${index}.modo_calculo`) ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)] focus:border-[var(--nord10)]'"
                                    @change="updateMode(variable)"
                                >
                                    <option value="frecuencia">Frecuencia</option>
                                    <option value="acumulacion">Acumulación</option>
                                    <option value="puntaje">Puntaje</option>
                                </select>
                                <p v-if="fieldError(`variables.${index}.modo_calculo`)" :id="`variable-${index}-modo-error`" class="mt-1.5 text-sm text-[var(--aurora-red)]">{{ fieldError(`variables.${index}.modo_calculo`) }}</p>
                            </div>
                            <div v-if="variable.modo_calculo === 'frecuencia'">
                                <label :for="`variable-${index}-ventana`" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Ventana (días)</label>
                                <input
                                    :id="`variable-${index}-ventana`"
                                    v-model.number="variable.ventana_dias"
                                    type="number"
                                    min="1"
                                    max="3650"
                                    step="1"
                                    required
                                    :aria-invalid="Boolean(fieldError(`variables.${index}.ventana_dias`))"
                                    :aria-describedby="fieldError(`variables.${index}.ventana_dias`) ? `variable-${index}-ventana-error` : undefined"
                                    class="min-h-11 w-full rounded-lg border bg-white px-3 py-2 text-sm text-[var(--nord0)] outline-none focus-visible:ring-2 focus-visible:ring-[var(--nord10)]/30"
                                    :class="fieldError(`variables.${index}.ventana_dias`) ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)] focus:border-[var(--nord10)]'"
                                />
                                <p v-if="fieldError(`variables.${index}.ventana_dias`)" :id="`variable-${index}-ventana-error`" class="mt-1.5 text-sm text-[var(--aurora-red)]">{{ fieldError(`variables.${index}.ventana_dias`) }}</p>
                            </div>
                            <p v-else class="self-center text-sm text-[var(--nord3)]">Sin ventana de días para este cálculo.</p>
                            <div>
                                <label :for="`variable-${index}-umbral`" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Umbral</label>
                                <input
                                    :id="`variable-${index}-umbral`"
                                    v-model.number="variable.umbral"
                                    type="number"
                                    min="0.01"
                                    max="999999.99"
                                    step="0.01"
                                    required
                                    :aria-invalid="Boolean(fieldError(`variables.${index}.umbral`))"
                                    :aria-describedby="fieldError(`variables.${index}.umbral`) ? `variable-${index}-umbral-error` : undefined"
                                    class="min-h-11 w-full rounded-lg border bg-white px-3 py-2 text-sm text-[var(--nord0)] outline-none focus-visible:ring-2 focus-visible:ring-[var(--nord10)]/30"
                                    :class="fieldError(`variables.${index}.umbral`) ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)] focus:border-[var(--nord10)]'"
                                />
                                <p v-if="fieldError(`variables.${index}.umbral`)" :id="`variable-${index}-umbral-error`" class="mt-1.5 text-sm text-[var(--aurora-red)]">{{ fieldError(`variables.${index}.umbral`) }}</p>
                            </div>
                            <div>
                                <label :for="`variable-${index}-ponderacion`" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Ponderación</label>
                                <input
                                    :id="`variable-${index}-ponderacion`"
                                    v-model.number="variable.ponderacion"
                                    type="number"
                                    min="0.01"
                                    max="9999.99"
                                    step="0.01"
                                    required
                                    :aria-invalid="Boolean(fieldError(`variables.${index}.ponderacion`))"
                                    :aria-describedby="fieldError(`variables.${index}.ponderacion`) ? `variable-${index}-ponderacion-error` : undefined"
                                    class="min-h-11 w-full rounded-lg border bg-white px-3 py-2 text-sm text-[var(--nord0)] outline-none focus-visible:ring-2 focus-visible:ring-[var(--nord10)]/30"
                                    :class="fieldError(`variables.${index}.ponderacion`) ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)] focus:border-[var(--nord10)]'"
                                />
                                <p v-if="fieldError(`variables.${index}.ponderacion`)" :id="`variable-${index}-ponderacion-error`" class="mt-1.5 text-sm text-[var(--aurora-red)]">{{ fieldError(`variables.${index}.ponderacion`) }}</p>
                            </div>
                        </div>
                    </li>
                </ol>
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Link
                    href="/rubricas"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg border border-[var(--nord4)] px-5 py-2 text-sm font-semibold text-[var(--nord1)] transition-colors hover:bg-[var(--surface-subtle)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)]"
                >
                    Cancelar
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg bg-[var(--nord10)] px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-[var(--nord9)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)] disabled:cursor-wait disabled:opacity-60"
                >
                    {{ form.processing ? 'Guardando versión…' : 'Guardar versión' }}
                </button>
            </div>
            <p v-if="form.processing" role="status" aria-live="polite" class="text-right text-sm text-[var(--nord3)]">Guardando la nueva versión.</p>
        </form>
    </div>
</template>
