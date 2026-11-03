<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/Components/UI/Breadcrumbs.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';
import { formatElSalvadorDateTime, formatLocalDate } from '@/utils/dates';

defineOptions({ layout: ClinicalLayout });

const props = defineProps({
    versions: { type: Object, default: () => ({ data: [], links: [] }) },
});

const page = usePage();
const flashError = computed(() => page.props.flash?.variant === 'error' ? page.props.flash.message : '');
const versionRows = computed(() => props.versions?.data ?? []);
const totalVersions = computed(() => props.versions?.total ?? versionRows.value.length);
const paginationLinks = computed(() => props.versions?.links ?? []);

function status(version) {
    return {
        vigente: { label: 'Vigente', variant: 'active' },
        programada: { label: 'Programada', variant: 'inactive' },
        historica: { label: 'Histórica', variant: 'specialist' },
        demo: { label: 'DEMO', variant: 'inactive' },
    }[version.estado_vigencia] ?? { label: 'Sin vigencia', variant: 'inactive' };
}

function pageLabel(link, index) {
    if (index === 0) return 'Anterior';
    if (index === paginationLinks.value.length - 1) return 'Siguiente';
    return link.label;
}
</script>

<template>
    <Head title="Rúbrica de riesgo" />

    <div class="space-y-5">
        <Breadcrumbs :items="[
            { label: 'Panel clínico', href: '/dashboard' },
            { label: 'Rúbrica de riesgo' },
        ]" />

        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-pd-text-primary sm:text-3xl">Rúbrica institucional de riesgo</h1>
                <p class="mt-1 max-w-2xl text-sm leading-relaxed text-pd-text-secondary">
                    Consulta las versiones validadas y conserva el historial de cada umbral clínico.
                </p>
            </div>
            <span class="rounded-full bg-pd-brand-soft px-3 py-1.5 text-xs font-semibold text-pd-brand">Riesgo preventivo</span>
        </header>

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-pd-border bg-pd-surface px-4 py-3">
            <p class="text-sm font-semibold text-pd-text-secondary">{{ totalVersions }} {{ totalVersions === 1 ? 'versión registrada' : 'versiones registradas' }} · sin puntaje global</p>
            <Link
                v-if="page.props.clinicalPermissions?.crear"
                href="/rubricas/versiones/crear"
                class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-pd-brand px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-pd-brand/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pd-brand"
            >
                Crear versión
            </Link>
        </div>

        <div v-if="flashError" role="alert" class="rounded-lg border border-pd-danger/30 bg-pd-danger/10 px-4 py-3 text-sm text-pd-text-primary">
            {{ flashError }}
        </div>

        <section aria-labelledby="versions-heading" class="overflow-hidden rounded-xl border border-pd-border bg-pd-surface">
            <h2 id="versions-heading" class="sr-only">Versiones registradas</h2>

            <div v-if="versionRows.length === 0" class="px-5 py-12 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-pd-surface-subtle text-pd-brand" aria-hidden="true">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <h3 class="mt-4 text-base font-semibold text-pd-text-primary">Aún no hay versiones</h3>
                <p class="mx-auto mt-1 max-w-md text-sm leading-relaxed text-pd-text-muted">
                    Registra una versión con su fecha y referencia de validación para iniciar el historial.
                </p>
                <Link
                    v-if="page.props.clinicalPermissions?.crear"
                    href="/rubricas/versiones/crear"
                    class="mt-5 inline-flex min-h-11 items-center justify-center rounded-lg border border-pd-brand px-4 py-2 text-sm font-semibold text-pd-brand transition-colors hover:bg-pd-brand/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pd-brand"
                >
                    Crear primera versión
                </Link>
            </div>

            <ol v-else class="divide-y divide-pd-border">
                <li v-for="version in versionRows" :key="version.id" class="p-4 sm:p-5">
                    <article class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-semibold text-pd-text-primary">Versión {{ version.numero_version }}</h3>
                                <StatusBadge :label="status(version).label" :variant="status(version).variant" />
                            </div>
                            <dl class="flex flex-wrap gap-x-6 gap-y-1 text-xs">
                                <div>
                                    <dt class="inline text-pd-text-muted">Vigente desde (hora de El Salvador): </dt>
                                    <dd class="inline font-medium text-pd-text-primary">{{ formatElSalvadorDateTime(version.vigente_desde) }}</dd>
                                </div>
                                <div>
                                    <dt class="inline text-pd-text-muted">Validada el: </dt>
                                    <dd class="inline font-medium text-pd-text-primary">{{ formatLocalDate(version.validada_en) }}</dd>
                                </div>
                                <div>
                                    <dt class="inline text-pd-text-muted">Responsable: </dt>
                                    <dd class="inline font-medium text-pd-text-primary">{{ version.responsable_profesional?.nombre || 'No registrado' }}</dd>
                                </div>
                            </dl>
                        </div>
                        <Link
                            :href="`/rubricas/versiones/${version.id}`"
                            class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-pd-border bg-pd-surface px-4 py-2 text-sm font-semibold text-pd-text-primary transition-colors hover:border-pd-brand hover:bg-pd-surface-subtle focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pd-brand"
                            :aria-label="`Ver detalles de la versión ${version.numero_version}`"
                        >
                            Ver versión
                        </Link>
                    </article>
                </li>
            </ol>

            <nav v-if="paginationLinks.length > 3" aria-label="Paginación de versiones" class="flex flex-wrap justify-center gap-2 border-t border-pd-border p-4">
                <template v-for="(link, index) in paginationLinks" :key="`${index}-${link.label}`">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        :aria-current="link.active ? 'page' : undefined"
                        :aria-label="`Ir a ${pageLabel(link, index)}`"
                        class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border px-3 text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pd-brand"
                        :class="link.active ? 'border-pd-brand bg-pd-brand text-white' : 'border-pd-border text-pd-text-primary hover:bg-pd-surface-subtle'"
                    >
                        {{ pageLabel(link, index) }}
                    </Link>
                    <span v-else class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border border-pd-border px-3 text-sm text-pd-text-muted" aria-disabled="true">
                        {{ pageLabel(link, index) }}
                    </span>
                </template>
            </nav>
        </section>

        <p class="rounded-lg bg-pd-brand-soft px-4 py-3 text-xs leading-relaxed text-pd-brand">
            Cada versión conserva sus propias señales y umbrales. No se calcula un puntaje global.
        </p>
    </div>
</template>
