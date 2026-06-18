<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';
import Modal from '@/Components/UI/Modal.vue';

defineOptions({ layout: ClinicalLayout });

const props = defineProps({
    searchCode: { type: String, default: '' },
    results: { type: Object, default: null },
});

const page = usePage();
const STORAGE_KEY = computed(() => `pandora_uuid_history_${page.props.auth.user.id}`);

const canSeeCarnetSearch = computed(() => {
    const roles = page.props.auth?.user?.roles || [];
    return !roles.includes('area_coordinator');
});

const form = useForm({
    code: props.searchCode || '',
});

const showHistorial = ref(false);
const searchHistory = ref([]);

function loadHistory() {
    try {
        const stored = localStorage.getItem(STORAGE_KEY.value);
        searchHistory.value = stored ? JSON.parse(stored) : [];
    } catch { searchHistory.value = []; }
}

function saveToHistory(code) {
    if (!code) return;
    const filtered = searchHistory.value.filter(h => h !== code);
    filtered.unshift(code);
    searchHistory.value = filtered.slice(0, 10);
    localStorage.setItem(STORAGE_KEY.value, JSON.stringify(searchHistory.value));
}

function submitSearch() {
    saveToHistory(form.code);
    form.post('/busqueda-segura', { preserveScroll: true });
}

function autofill(value) {
    form.code = value;
    form.clearErrors('code');
}

loadHistory();
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
                    Búsqueda por UUID
                </h2>
                <Link v-if="canSeeCarnetSearch" href="/pacientes" class="text-[12px] text-[var(--nord10)] hover:text-[var(--nord9)] transition-colors font-medium">
                    Buscar por Carnet →
                </Link>
            </div>

            <div class="p-8">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-[var(--nord6)] rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[var(--nord3)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <p class="text-[13px] text-[var(--nord3)]">
                        Ingrese el código único (UUID) del expediente para consultarlo. 
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
                            list="uuid-history"
                            autocomplete="off"
                            class="w-full border-2 rounded-full pl-6 pr-14 py-3.5 text-[13px] font-mono focus:ring-0 focus:outline-none transition-colors"
                            :class="form.errors.code ? 'border-[var(--aurora-red)] text-[var(--aurora-red)] focus:border-[var(--aurora-red)]' : 'border-[var(--nord4)] text-[var(--nord0)] focus:border-[var(--nord10)]'"
                            required
                            autofocus
                        />
                        <datalist id="uuid-history">
                            <option v-for="item in searchHistory" :key="item" :value="item" />
                        </datalist>
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
                    <div v-if="searchHistory.length > 0 && !form.errors.code && !results" class="mt-2 flex flex-wrap gap-1 justify-center">
                        <button
                            v-for="item in searchHistory.slice(0, 5)"
                            :key="item"
                            type="button"
                            @click="autofill(item)"
                            class="text-[11px] px-2 py-0.5 rounded-full border border-[var(--nord4)] text-[var(--nord3)] hover:bg-[var(--nord6)] hover:text-[var(--nord0)] transition-colors font-mono truncate max-w-[200px]"
                            :title="item"
                        >
                            {{ item.substring(0, 8) }}...
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

        <div v-if="results && results.found" class="mt-6 bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] overflow-hidden animate-fade-in">
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
                    <dt class="text-[11px] font-medium text-[var(--nord3)] uppercase tracking-wider">Código de Expediente</dt>
                    <dd class="text-[14px] font-mono font-semibold text-[var(--nord0)] mt-1">{{ results.code }}</dd>
                </div>

                <p class="text-[12px] text-[var(--nord3)] mt-5 text-center">Los datos personales del paciente no se muestran en esta vista.</p>

                <div class="flex justify-center gap-3 mt-5">
                    <Link
                        :href="`/pacientes/${results.carnet}`"
                        class="inline-flex items-center px-5 py-2.5 text-[13px] font-medium rounded-lg bg-[var(--nord10)] text-white hover:bg-[var(--nord9)] transition-all duration-200 shadow-sm hover:shadow-md"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Ver Expediente
                    </Link>
                    <button
                        type="button"
                        @click="showHistorial = true"
                        class="inline-flex items-center px-5 py-2.5 text-[13px] font-medium rounded-lg border border-[var(--nord4)] text-[var(--nord3)] hover:bg-[var(--nord6)] hover:text-[var(--nord0)] transition-all duration-200"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Historial de Citas
                    </button>
                </div>
            </div>
        </div>

        <p class="mt-6 text-[12px] text-[var(--nord3)] text-center">Los datos se muestran de forma anonimizada. Solo personal autorizado puede acceder al expediente completo.</p>
    </div>

    <Modal :show="showHistorial" title="Historial de Citas" max-width="max-w-2xl" @close="showHistorial = false">
        <p class="text-[13px] text-[var(--nord3)] mb-4">El módulo de Citas estará disponible en un próximo sprint.</p>
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-[var(--nord3)] border-b border-[var(--nord4)] text-[11px] uppercase tracking-wider">
                    <th class="py-2 pr-4 font-medium">Fecha</th>
                    <th class="py-2 pr-4 font-medium">Tipo</th>
                    <th class="py-2 font-medium">Estado</th>
                </tr>
            </thead>
            <tbody class="text-[var(--nord3)]">
                <tr>
                    <td colspan="3" class="py-8 text-center">Sin registros disponibles</td>
                </tr>
            </tbody>
        </table>
        <template #footer>
            <button type="button" @click="showHistorial = false" class="inline-flex items-center px-5 py-2.5 text-[13px] font-medium rounded-lg bg-[var(--nord10)] text-white hover:bg-[var(--nord9)] transition-colors">Cerrar</button>
        </template>
    </Modal>
</template>
