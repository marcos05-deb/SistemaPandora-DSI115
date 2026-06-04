<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';

defineOptions({ layout: ClinicalLayout });

const STORAGE_KEY = 'pandora_carnet_history';

const props = defineProps({
    results: { type: Object, default: null },
});

const page = usePage();
const canCreatePatient = computed(() => {
    const roles = page.props.auth?.user?.roles || [];
    return roles.includes('psychosocial_referent');
});

const form = useForm({
    carnet: ''
});

const searchHistory = ref([]);

function loadHistory() {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        searchHistory.value = stored ? JSON.parse(stored) : [];
    } catch {
        searchHistory.value = [];
    }
}

function saveToHistory(carnet) {
    if (!carnet) return;
    const upper = carnet.toUpperCase();
    const filtered = searchHistory.value.filter(h => h !== upper);
    filtered.unshift(upper);
    const sliced = filtered.slice(0, 10);
    searchHistory.value = sliced;
    localStorage.setItem(STORAGE_KEY, JSON.stringify(sliced));
}

function search() {
    saveToHistory(form.carnet);
    form.get('/pacientes', {
        preserveState: true,
        preserveScroll: true,
    });
}

function autofill(value) {
    form.carnet = value;
    form.clearErrors('carnet');
}

loadHistory();
</script>

<template>
    <Head title="Búsqueda de Pacientes" />

    <div class="max-w-2xl mx-auto mt-10">
        <div class="bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] overflow-hidden">
            <div class="px-6 py-5 border-b border-[var(--nord4)] bg-[var(--nord6)] flex justify-between items-center">
                <h2 class="text-[15px] font-medium text-[var(--nord0)] flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--nord10)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    Búsqueda por Carnet
                </h2>
                <div class="flex items-center gap-4">
                    <Link href="/busqueda-segura" class="text-[12px] text-[var(--nord10)] hover:text-[var(--nord8)] transition-colors">
                        Buscar por UUID →
                    </Link>
                    <Link v-if="canCreatePatient" href="/pacientes/create" class="text-[12px] font-medium text-[var(--nord10)] hover:text-[var(--nord8)] transition-colors flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Nuevo Paciente
                    </Link>
                </div>
            </div>
            
            <div class="p-8">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-[var(--nord6)] rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[var(--nord3)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                        </svg>
                    </div>
                    <p class="text-[13px] text-[var(--nord3)]">
                        Ingrese el carnet estudiantil para localizar el expediente. 
                        Por normativas de privacidad, solo puede acceder a los expedientes creados por usted.
                    </p>
                </div>

                <form @submit.prevent="search" class="max-w-md mx-auto relative">
                    <div class="relative w-full">
                        <input 
                            id="carnet" 
                            v-model="form.carnet" 
                            @input="form.clearErrors('carnet')"
                            type="text" 
                            placeholder="Ej: AB12345"
                            list="carnet-history"
                            autocomplete="off"
                            class="w-full border-2 rounded-full pl-6 pr-14 py-3.5 text-[15px] uppercase tracking-wider font-mono focus:ring-0 focus:outline-none transition-colors"
                            :class="form.errors.carnet ? 'border-[var(--aurora-red)] text-[var(--aurora-red)] focus:border-[var(--aurora-red)]' : 'border-[var(--nord4)] text-[var(--nord0)] focus:border-[var(--nord10)]'"
                            required 
                            autofocus
                        />
                        <datalist id="carnet-history">
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
                    <div v-if="searchHistory.length > 0 && !form.errors.carnet && !results" class="mt-2 flex flex-wrap gap-1 justify-center">
                        <button
                            v-for="item in searchHistory.slice(0, 5)"
                            :key="item"
                            type="button"
                            @click="autofill(item)"
                            class="text-[11px] px-2 py-0.5 rounded-full border border-[var(--nord4)] text-[var(--nord3)] hover:bg-[var(--nord6)] hover:text-[var(--nord0)] transition-colors font-mono"
                        >
                            {{ item }}
                        </button>
                    </div>
                    <div v-if="form.errors.carnet" class="text-[12px] text-[var(--aurora-red)] mt-3 text-center border border-[var(--aurora-red)] bg-transparent py-2 px-3 rounded-lg flex items-center justify-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        {{ form.errors.carnet }}
                    </div>
                </form>
            </div>
        </div>

        <!-- Resultado de búsqueda -->
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
                    <dt class="text-[11px] font-medium text-[var(--nord3)] uppercase tracking-wider">Carnet Estudiantil</dt>
                    <dd class="text-[14px] font-mono font-semibold text-[var(--nord0)] mt-1">{{ results.carnet }}</dd>
                </div>
                <p class="text-[12px] text-[var(--nord3)] mt-5 text-center">
                    Los datos personales del paciente no se muestran en esta vista.
                    Acceda al expediente para consultar la información clínica completa.
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
                </div>
            </div>
        </div>

        <p class="mt-6 text-[12px] text-[var(--nord3)] text-center">
            Los datos se muestran de forma anonimizada. Solo personal autorizado puede acceder al expediente completo.
        </p>
    </div>
</template>
