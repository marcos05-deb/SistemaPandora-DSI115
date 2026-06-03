<script setup>
import { ref } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
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

const showExpedienteOptions = ref(false);
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
    <Head title="Busqueda Segura" />

    <div class="max-w-2xl mx-auto mt-10">
        <div class="bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] overflow-hidden">
            <div class="px-6 py-5 border-b border-[var(--nord4)] bg-[var(--nord6)] flex justify-between items-center">
                <h2 class="text-[15px] font-medium text-[var(--nord0)] flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--nord10)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    Busqueda Segura de Expedientes
                </h2>
            </div>

            <div class="p-8">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-[var(--nord6)] rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[var(--nord3)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <p class="text-[13px] text-[var(--nord3)]">
                        Ingrese el codigo unico (UUID) del expediente para consultarlo. 
                        La identidad del paciente permanece protegida hasta que acceda al detalle.
                    </p>
                </div>

                <form @submit.prevent="submitSearch" class="max-w-md mx-auto relative">
                    <div class="relative w-full">
                        <input
                            id="code"
                            v-model="form.code"
                            @input="form.clearErrors('code')"
                            type="text"
                            placeholder="Ej: a3f7c9e1-b2d4-4f5a-8c6e-1d2f3a4b5c6d"
                            class="w-full border-2 rounded-full pl-6 pr-14 py-3.5 text-[13px] font-mono focus:ring-0 focus:outline-none transition-colors"
                            :class="form.errors.code ? 'border-[var(--aurora-red)] text-[var(--aurora-red)] focus:border-[var(--aurora-red)]' : 'border-[var(--nord4)] text-[var(--nord0)] focus:border-[var(--nord10)]'"
                            required
                            autofocus
                        />
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="absolute right-2 top-2 bottom-2 w-10 flex items-center justify-center text-[var(--nord3)] hover:text-[var(--nord10)] hover:bg-[var(--nord6)] rounded-full transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                            title="Buscar"
                        >
                            <svg v-if="!form.processing" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <span v-else class="inline-block animate-spin w-5 h-5 border-2 border-current border-t-transparent rounded-full"></span>
                        </button>
                    </div>
                    <div v-if="form.errors.code" class="text-[12px] text-[var(--aurora-red)] mt-3 text-center border border-[var(--aurora-red)] bg-transparent py-2 px-3 rounded-lg flex items-center justify-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        {{ form.errors.code }}
                    </div>
                </form>
            </div>
        </div>

        <div
            v-if="results && results.found"
            class="mt-6 bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] overflow-hidden"
        >
            <div class="px-6 py-5 border-b border-[var(--nord4)] bg-[var(--nord6)]">
                <div class="flex justify-between items-center">
                    <h3 class="text-[15px] font-medium text-[var(--nord0)] flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--aurora-green)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Expediente Encontrado
                    </h3>
                </div>
            </div>

            <div class="p-8">
                <div class="border border-[var(--nord4)] rounded-lg px-5 py-4 bg-[var(--nord6)]">
                    <dt class="text-[11px] font-medium text-[var(--nord3)] uppercase tracking-wider">Codigo de Expediente</dt>
                    <dd class="text-[14px] font-mono font-semibold text-[var(--nord0)] mt-1">{{ results.code }}</dd>
                </div>

                <p class="text-[12px] text-[var(--nord3)] mt-5 text-center">
                    Los datos personales del paciente no se muestran en esta vista.
                    Acceda al expediente para consultar la informacion clinica completa.
                </p>

                <div class="flex justify-center gap-3 mt-5">
                    <Link
                        :href="`/pacientes/${results.carnet}`"
                        class="inline-flex items-center px-5 py-2.5 text-[13px] font-medium rounded-lg bg-[var(--nord10)] text-white hover:bg-[var(--nord9)] transition-colors shadow-sm"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Ver Expediente
                    </Link>
                    <button
                        type="button"
                        @click="showHistorial = true"
                        class="inline-flex items-center px-5 py-2.5 text-[13px] font-medium rounded-lg border border-[var(--nord4)] text-[var(--nord3)] hover:bg-[var(--nord6)] hover:text-[var(--nord0)] transition-colors"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Historial de Citas
                    </button>
                </div>
            </div>
        </div>

        <p class="mt-6 text-[12px] text-[var(--nord3)] text-center">
            Los datos se muestran de forma anonimizada. Solo personal autorizado puede acceder al expediente completo.
        </p>
    </div>

    <Modal :show="showHistorial" title="Historial de citas" max-width="max-w-2xl" @close="showHistorial = false">
        <p class="text-sm text-gray-500 mb-4">
            El modulo de Citas estara disponible en un proximo sprint.
        </p>
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b">
                    <th class="py-2 pr-4">Fecha</th>
                    <th class="py-2 pr-4">Tipo</th>
                    <th class="py-2">Estado</th>
                </tr>
            </thead>
            <tbody class="text-gray-400">
                <tr>
                    <td colspan="3" class="py-4 text-center">Sin registros disponibles</td>
                </tr>
            </tbody>
        </table>
        <template #footer>
            <PrimaryButton type="button" @click="showHistorial = false">Cerrar</PrimaryButton>
        </template>
    </Modal>
</template>
