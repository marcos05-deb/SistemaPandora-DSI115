<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/Components/UI/Breadcrumbs.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';
import { formatElSalvadorDateTime, formatLocalDate } from '@/utils/dates';

defineOptions({ layout: ClinicalLayout });

const props = defineProps({
    version: { type: Object, required: true },
});

const page = usePage();
const flashError = computed(() => page.props.flash?.variant === 'error' ? page.props.flash.message : '');
const versionStatus = computed(() => ({
    vigente: { label: 'Vigente', variant: 'active' },
    programada: { label: 'Programada', variant: 'inactive' },
    historica: { label: 'Histórica', variant: 'specialist' },
    demo: { label: 'DEMO', variant: 'inactive' },
}[props.version.estado_vigencia] ?? { label: 'Sin vigencia', variant: 'inactive' }));

function signalLabel(type) {
    return {
        ausencia_cita: 'Ausencia a cita',
        crisis_psicologica: 'Crisis psicológica',
        tendencia_suicida: 'Tendencia suicida',
    }[type] ?? type;
}

function modeLabel(mode) {
    return { frecuencia: 'Frecuencia', acumulacion: 'Acumulación', puntaje: 'Puntaje' }[mode] ?? mode;
}
</script>

<template>
    <Head :title="`Rúbrica — versión ${version.numero_version}`" />

    <div class="space-y-5">
        <Breadcrumbs :items="[
            { label: 'Panel clínico', href: '/dashboard' },
            { label: 'Rúbrica de riesgo', href: '/rubricas' },
            { label: `Versión ${version.numero_version}` },
        ]" />

        <header class="flex flex-col gap-4 rounded-xl border border-[var(--nord4)] bg-white p-5 shadow-sm sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-semibold tracking-tight text-[var(--nord0)]">Versión {{ version.numero_version }}</h1>
                    <StatusBadge :label="versionStatus.label" :variant="versionStatus.variant" />
                </div>
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-[var(--nord3)]">
                    Esta versión es inmutable. Los cambios se registran en una nueva versión.
                </p>
            </div>
            <Link
                href="/rubricas"
                class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-[var(--nord4)] px-4 py-2 text-sm font-semibold text-[var(--nord10)] transition-colors hover:border-[var(--nord10)] hover:bg-[var(--surface-subtle)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)]"
            >
                Volver al historial
            </Link>
        </header>

        <div v-if="flashError" role="alert" class="rounded-lg border border-[var(--aurora-red)]/30 bg-[var(--aurora-red)]/10 px-4 py-3 text-sm text-[var(--nord0)]">
            {{ flashError }}
        </div>

        <section aria-labelledby="metadata-heading" class="overflow-hidden rounded-xl border border-[var(--nord4)] bg-white shadow-sm">
            <div class="border-b border-[var(--nord4)] bg-[var(--surface-header)] px-5 py-4">
                <h2 id="metadata-heading" class="text-base font-semibold text-[var(--nord0)]">Vigencia y validación</h2>
            </div>
            <dl class="grid grid-cols-1 gap-x-8 gap-y-5 p-5 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-[var(--nord3)]">Vigente desde (hora de El Salvador)</dt>
                    <dd class="mt-1 text-sm font-semibold text-[var(--nord0)]">{{ formatElSalvadorDateTime(version.vigente_desde) }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-[var(--nord3)]">Fecha de validación</dt>
                    <dd class="mt-1 text-sm font-semibold text-[var(--nord0)]">{{ formatLocalDate(version.validada_en) }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-sm font-medium text-[var(--nord3)]">Referencia documental</dt>
                    <dd class="mt-1 break-words text-sm text-[var(--nord0)]">{{ version.referencia_validacion }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-sm font-medium text-[var(--nord3)]">Responsable</dt>
                    <dd class="mt-1 text-sm font-semibold text-[var(--nord0)]">{{ version.responsable_profesional?.nombre || 'No registrado' }}</dd>
                </div>
                <div v-if="version.justificacion_cambio" class="sm:col-span-2">
                    <dt class="text-sm font-medium text-[var(--nord3)]">Justificación del cambio</dt>
                    <dd class="mt-1 whitespace-pre-line break-words text-sm leading-relaxed text-[var(--nord0)]">{{ version.justificacion_cambio }}</dd>
                </div>
            </dl>
        </section>

        <section aria-labelledby="signals-heading" class="overflow-hidden rounded-xl border border-[var(--nord4)] bg-white shadow-sm">
            <div class="border-b border-[var(--nord4)] bg-[var(--surface-header)] px-5 py-4">
                <h2 id="signals-heading" class="text-base font-semibold text-[var(--nord0)]">Señales y umbrales</h2>
                <p class="mt-1 text-sm text-[var(--nord3)]">Las señales se interpretan por separado; esta rúbrica no produce un puntaje global.</p>
            </div>
            <div v-if="version.variables?.length" class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2">
                <article v-for="variable in version.variables" :key="variable.id" class="rounded-lg border border-[var(--nord4)] p-4">
                    <h3 class="font-semibold text-[var(--nord0)]">{{ signalLabel(variable.tipo_senal) }}</h3>
                    <p v-if="variable.clave_indicador" class="mt-1 text-sm text-[var(--nord3)]">Indicador: {{ variable.clave_indicador }}</p>
                    <p class="mt-3 text-sm text-[var(--nord1)]">
                        Umbral: <span class="font-semibold">{{ variable.umbral }}</span>
                        <span v-if="variable.ventana_dias"> en {{ variable.ventana_dias }} días</span>
                    </p>
                    <p class="mt-1 text-sm text-[var(--nord3)]">Cálculo: {{ modeLabel(variable.modo_calculo) }} · Ponderación: {{ variable.ponderacion }}</p>
                </article>
            </div>
            <p v-else class="px-5 py-8 text-sm text-[var(--nord3)]">Esta versión no tiene señales registradas.</p>
        </section>
    </div>
</template>
