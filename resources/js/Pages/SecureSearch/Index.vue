<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import Modal from '@/Components/UI/Modal.vue';

const props = defineProps({
    userLabel: String,
    navigation: Array,
    searchCode: { type: String, default: '' },
    results: { type: Object, default: null },
});

const form = useForm({
    code: props.searchCode || '',
});

const showExpediente = ref(false);
const showHistorial = ref(false);

function submitSearch() {
    form.post('/busqueda-segura', {
        preserveScroll: true,
    });
}

defineOptions({
    layout: (h, page) =>
        h(AuthenticatedLayout, {
            userLabel: page.props.userLabel,
            navigation: page.props.navigation,
        }, () => page),
});
</script>

<template>
    <Head title="Búsqueda Segura" />

    <div class="max-w-4xl">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h1 class="text-xl font-bold text-gray-900">Búsqueda Segura de Expedientes</h1>
            <p class="text-sm text-gray-500 mt-1 mb-5">
                Consulte casos por Código de Privacidad. La identidad del paciente permanece protegida.
            </p>

            <form class="flex flex-wrap items-end gap-4" @submit.prevent="submitSearch">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Código PND:</label>
                    <input
                        v-model="form.code"
                        type="text"
                        placeholder="Ingrese código (ej: PND-00124)"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    />
                </div>
                <PrimaryButton type="submit" :disabled="form.processing">Buscar</PrimaryButton>
            </form>
        </div>

        <div
            v-if="results && !results.notFound"
            class="bg-white rounded-lg shadow-sm border border-gray-200 p-6"
        >
            <div class="flex justify-between items-center mb-5">
                <h2 class="text-lg font-semibold text-gray-900">
                    Resultados para: <span class="text-blue-600">{{ results.code }}</span>
                </h2>
                <span class="text-sm text-gray-500">{{ results.count }} registros encontrados</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <div
                    v-for="field in results.fields"
                    :key="field.label"
                    class="border border-gray-100 rounded-md px-4 py-3 bg-gray-50"
                >
                    <dt class="text-xs font-medium text-gray-500 uppercase">{{ field.label }}</dt>
                    <dd class="text-sm font-semibold text-gray-900 mt-1">{{ field.value }}</dd>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <PrimaryButton type="button" @click="showExpediente = true">Ver Expediente</PrimaryButton>
                <SecondaryButton type="button" @click="showHistorial = true">Historial de Citas</SecondaryButton>
            </div>
        </div>

        <div
            v-else-if="results?.notFound"
            class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 text-sm text-gray-600"
        >
            No se encontraron registros para el código ingresado. Pruebe con <strong>PND-00124</strong>.
        </div>

        <p class="mt-6 text-sm text-red-600">
            Los datos se muestran de forma anonimizada. Solo personal autorizado puede acceder al expediente completo.
        </p>
    </div>

    <Modal :show="showExpediente" title="Expediente clínico (vista demo)" max-width="max-w-2xl" @close="showExpediente = false">
        <p class="text-sm text-gray-600 mb-4">
            Resumen anonimizado del caso <strong>{{ results?.code }}</strong>. La identidad real no se muestra.
        </p>
        <ul class="text-sm text-gray-700 space-y-2 list-disc pl-5">
            <li>Motivo de consulta: Evaluación psicológica de rutina</li>
            <li>Última nota: Seguimiento semanal programado</li>
            <li>Restricción: Solo especialistas autorizados</li>
        </ul>
        <template #footer>
            <PrimaryButton type="button" @click="showExpediente = false">Cerrar</PrimaryButton>
        </template>
    </Modal>

    <Modal :show="showHistorial" title="Historial de citas" max-width="max-w-2xl" @close="showHistorial = false">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b">
                    <th class="py-2 pr-4">Fecha</th>
                    <th class="py-2 pr-4">Tipo</th>
                    <th class="py-2">Estado</th>
                </tr>
            </thead>
            <tbody class="text-gray-800">
                <tr class="border-b border-gray-100">
                    <td class="py-2 pr-4">2026-05-20</td>
                    <td class="py-2 pr-4">Consulta</td>
                    <td class="py-2">Completada</td>
                </tr>
                <tr class="border-b border-gray-100">
                    <td class="py-2 pr-4">2026-05-06</td>
                    <td class="py-2 pr-4">Seguimiento</td>
                    <td class="py-2">Completada</td>
                </tr>
                <tr>
                    <td class="py-2 pr-4">2026-04-22</td>
                    <td class="py-2 pr-4">Primera vez</td>
                    <td class="py-2">Completada</td>
                </tr>
            </tbody>
        </table>
        <template #footer>
            <PrimaryButton type="button" @click="showHistorial = false">Cerrar</PrimaryButton>
        </template>
    </Modal>
</template>
