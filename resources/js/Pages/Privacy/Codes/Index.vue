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
    setTimeout(() => { localNotice.value = null; }, 4000);
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
        day: '2-digit', month: 'short', year: 'numeric',
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
            class="mb-4 rounded-lg border border-[var(--aurora-green)] bg-[var(--aurora-green)]/5 px-4 py-3 text-sm text-[var(--aurora-green)]"
        >
            {{ localNotice }}
        </div>

        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-[var(--nord0)]">Códigos de Privacidad</h1>
                <p class="text-sm text-[var(--nord3)] mt-1">
                    Gestión de identidad y anonimización de pacientes
                </p>
            </div>
            <div class="flex items-center gap-4">
                <PrimaryButton type="button" @click="openGenerate">+ Generar Nuevo Código</PrimaryButton>
                <span class="text-sm text-[var(--nord3)] whitespace-nowrap">
                    Total: {{ totalDisplay }} códigos generados
                </span>
            </div>
        </div>

        <div class="bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-[var(--surface-header)]">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-[var(--nord3)] uppercase tracking-wider">Código PND</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-[var(--nord3)] uppercase tracking-wider">Paciente (Anon.)</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-[var(--nord3)] uppercase tracking-wider">Facultad</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-[var(--nord3)] uppercase tracking-wider">Fecha Creación</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-[var(--nord3)] uppercase tracking-wider">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--nord5)]">
                    <tr v-for="row in codesList" :key="row.code" class="hover:bg-[var(--nord6)] transition-colors">
                        <td class="px-6 py-4 text-sm font-medium text-[var(--frost4)]">{{ row.code }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--nord0)]">{{ row.patient }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--nord3)]">{{ row.faculty }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--nord3)]">{{ row.createdAt }}</td>
                        <td class="px-6 py-4">
                            <StatusBadge :label="row.status" :variant="row.statusVariant" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Modal :show="showGenerate" title="Generar código de privacidad" @close="showGenerate = false">
        <p class="text-sm text-[var(--nord3)] mb-4">
            Se creará un código PND sin exponer datos identificables en pantalla (demo).
        </p>
        <div class="space-y-4">
            <div>
                <label class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Paciente (alias anonimizado)</label>
                <input
                    v-model="generateForm.patient"
                    type="text"
                    placeholder="Ej: Estudiante #42"
                    class="w-full rounded-[7px] border border-[var(--nord4)] px-[10px] py-[7px] text-[13px] text-[var(--nord0)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)] outline-none transition-colors"
                />
            </div>
            <div>
                <label class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Facultad</label>
                <select v-model="generateForm.faculty" class="w-full rounded-[7px] border border-[var(--nord4)] px-[10px] py-[7px] text-[13px] text-[var(--nord0)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)] outline-none transition-colors">
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
