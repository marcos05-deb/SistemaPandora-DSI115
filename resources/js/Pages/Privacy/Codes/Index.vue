<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import Modal from '@/Components/UI/Modal.vue';

const props = defineProps({
    userLabel: String,
    navigation: Array,
    totalGenerated: Number,
    codes: Array,
});

const codesList = ref(props.codes.map((c) => ({ ...c })));
const showGenerate = ref(false);
const localNotice = ref(null);

const generateForm = ref({
    patient: '',
    faculty: 'Ingeniería y Arquitectura',
});

const faculties = [
    'Ingeniería y Arquitectura',
    'Medicina',
    'Ciencias Sociales',
    'Derecho',
    'Ciencias Naturales',
];

const totalDisplay = computed(() => codesList.value.length);

function notify(message) {
    localNotice.value = message;
    setTimeout(() => {
        localNotice.value = null;
    }, 4000);
}

function nextCode() {
    const nums = codesList.value
        .map((c) => parseInt(String(c.code).replace(/\D/g, ''), 10))
        .filter((n) => !Number.isNaN(n));
    const next = (nums.length ? Math.max(...nums) : 124) + 1;
    return `PND-${String(next).padStart(5, '0')}`;
}

function openGenerate() {
    generateForm.value = { patient: '', faculty: faculties[0] };
    showGenerate.value = true;
}

function confirmGenerate() {
    if (!generateForm.value.patient.trim()) {
        notify('Indique un alias de paciente (anonimizado).');
        return;
    }
    const today = new Date().toLocaleDateString('es-SV', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
    codesList.value.unshift({
        code: nextCode(),
        patient: generateForm.value.patient.trim(),
        faculty: generateForm.value.faculty,
        createdAt: today,
        status: 'Activo',
        statusVariant: 'active',
    });
    showGenerate.value = false;
    notify(`Código ${codesList.value[0].code} generado (demo).`);
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
    <Head title="Códigos de Privacidad" />

    <div class="max-w-5xl">
        <div
            v-if="localNotice"
            class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900"
        >
            {{ localNotice }}
        </div>

        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Códigos de Privacidad</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Gestión de identidad y anonimización de pacientes
                </p>
            </div>
            <div class="flex items-center gap-4">
                <PrimaryButton type="button" @click="openGenerate">+ Generar Nuevo Código</PrimaryButton>
                <span class="text-sm text-gray-500 whitespace-nowrap">
                    Total: {{ totalDisplay }} códigos generados
                </span>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Código PND</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Paciente (Anon.)</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Facultad</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Fecha Creación</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="row in codesList" :key="row.code" class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-blue-600">{{ row.code }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ row.patient }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ row.faculty }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ row.createdAt }}</td>
                        <td class="px-6 py-4">
                            <StatusBadge :label="row.status" :variant="row.statusVariant" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Modal :show="showGenerate" title="Generar código de privacidad" @close="showGenerate = false">
        <p class="text-sm text-gray-500 mb-4">
            Se creará un código PND sin exponer datos identificables en pantalla (demo).
        </p>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Paciente (alias anonimizado)</label>
                <input
                    v-model="generateForm.patient"
                    type="text"
                    placeholder="Ej: Estudiante #42"
                    class="w-full rounded-md border-gray-300 text-sm"
                />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Facultad</label>
                <select v-model="generateForm.faculty" class="w-full rounded-md border-gray-300 text-sm">
                    <option v-for="f in faculties" :key="f" :value="f">{{ f }}</option>
                </select>
            </div>
        </div>
        <template #footer>
            <SecondaryButton type="button" @click="showGenerate = false">Cancelar</SecondaryButton>
            <PrimaryButton type="button" @click="confirmGenerate">Generar</PrimaryButton>
        </template>
    </Modal>
</template>
