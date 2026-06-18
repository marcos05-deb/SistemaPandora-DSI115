<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';

defineOptions({ layout: ClinicalLayout });

const props = defineProps({
    results: { type: Object, default: null },
});

const page = usePage();
const STORAGE_KEY = computed(() => `pandora_carnet_history_${page.props.auth.user.id}`);
const canCreatePatient = computed(() => {
    const roles = page.props.auth?.user?.roles || [];
    return roles.includes('psychosocial_referent');
});

const canSeeUuidSearch = computed(() => {
    const roles = page.props.auth?.user?.roles || [];
    return !roles.includes('psychosocial_referent') && !roles.includes('specialist');
});

const form = useForm({
    carnet: ''
});

const searchHistory = ref([]);

function loadHistory() {
    try {
        const stored = localStorage.getItem(STORAGE_KEY.value);
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
    localStorage.setItem(STORAGE_KEY.value, JSON.stringify(sliced));
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

    <div class="max-w-2xl mx-auto mt-6">
        <!-- Card principal de búsqueda -->
        <div class="bg-white rounded-2xl shadow-sm border border-[var(--nord4)] overflow-hidden">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-[var(--nord4)] flex justify-between items-center"
                style="background: linear-gradient(135deg, var(--surface-header) 0%, var(--surface) 100%);">
                <h2 class="text-[15px] font-bold text-[var(--nord0)] flex items-center gap-2 tracking-tight">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--nord10)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    Búsqueda por Carnet
                </h2>
                <div class="flex items-center gap-3">
                    <Link v-if="canSeeUuidSearch" href="/busqueda-segura"
                        class="text-[12px] font-medium text-[var(--frost4)] hover:text-[var(--nord10)] transition-colors flex items-center gap-1">
                        UUID
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </Link>
                    <Link v-if="canCreatePatient" href="/pacientes/create"
                        class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-white px-3 py-1.5 rounded-lg transition-all duration-150 shadow-sm"
                        style="background: linear-gradient(135deg, var(--frost4), var(--nord9));">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        Nuevo
                    </Link>
                </div>
            </div>

            <div class="px-8 py-8">
                <!-- Hero con ícono animado -->
                <div class="text-center mb-7">
                    <div class="relative w-20 h-20 mx-auto mb-5">
                        <!-- Anillos de radar -->
                        <span class="absolute inset-0 rounded-full border-2 border-[var(--frost4)]/20 animate-ping" style="animation-duration: 2.5s;"></span>
                        <span class="absolute inset-1 rounded-full border border-[var(--frost4)]/15 animate-ping" style="animation-duration: 2.5s; animation-delay: 0.4s;"></span>
                        <!-- Caja del ícono -->
                        <div class="absolute inset-0 rounded-2xl flex items-center justify-center"
                            style="background: linear-gradient(135deg, var(--surface-subtle) 0%, var(--nord5) 100%); border: 1.5px solid var(--nord4);">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-9 w-9 text-[var(--nord3)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-[13px] text-[var(--nord3)] leading-relaxed max-w-sm mx-auto">
                        Ingrese el carnet estudiantil para localizar el expediente.
                    </p>
                    <!-- Badge de privacidad -->
                    <div class="inline-flex items-center gap-1.5 mt-3 px-3 py-1 rounded-full text-[10px] font-semibold"
                        style="background: color-mix(in srgb, var(--aurora-green) 10%, transparent); color: var(--aurora-green); border: 1px solid color-mix(in srgb, var(--aurora-green) 25%, transparent);">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Acceso verificado por rol · Datos anonimizados
                    </div>
                </div>

                <!-- Input de búsqueda -->
                <form @submit.prevent="search" class="max-w-md mx-auto">
                    <div class="relative w-full group">
                        <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-[var(--nord3)] group-focus-within:text-[var(--frost4)] transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <input
                            id="carnet"
                            v-model="form.carnet"
                            @input="form.clearErrors('carnet')"
                            type="text"
                            placeholder="Ej: AB12345"
                            list="carnet-history"
                            autocomplete="off"
                            class="w-full border-2 rounded-2xl pl-11 pr-14 py-3.5 text-[15px] uppercase tracking-wider font-mono focus:ring-0 focus:outline-none transition-all duration-200"
                            :class="form.errors.carnet
                                ? 'border-[var(--aurora-red)] text-[var(--aurora-red)] focus:border-[var(--aurora-red)]'
                                : 'border-[var(--nord4)] text-[var(--nord0)] focus:border-[var(--frost4)] bg-[var(--surface)]'"
                            required
                            autofocus
                        />
                        <datalist id="carnet-history">
                            <option v-for="item in searchHistory" :key="item" :value="item" />
                        </datalist>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="absolute right-2 top-2 bottom-2 w-10 flex items-center justify-center rounded-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                            :class="form.carnet ? 'text-white shadow-sm' : 'text-[var(--nord3)] hover:text-[var(--frost4)] hover:bg-[var(--nord6)]'"
                            :style="form.carnet ? 'background: linear-gradient(135deg, var(--frost4), var(--nord9));' : ''"
                            title="Buscar"
                        >
                            <svg v-if="!form.processing" xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <span v-else class="inline-block animate-spin w-4 h-4 border-2 border-current border-t-transparent rounded-full"></span>
                        </button>
                    </div>

                    <!-- Historial de búsquedas -->
                    <div v-if="searchHistory.length > 0 && !form.errors.carnet && !results" class="mt-3 flex flex-wrap gap-1.5 justify-center">
                        <span class="text-[10px] text-[var(--nord3)] mr-1 self-center font-medium uppercase tracking-wider">Recientes:</span>
                        <button
                            v-for="item in searchHistory.slice(0, 5)"
                            :key="item"
                            type="button"
                            @click="autofill(item)"
                            class="text-[11px] px-2.5 py-1 rounded-lg border border-[var(--nord4)] text-[var(--nord3)] hover:bg-[var(--frost4)]/8 hover:border-[var(--frost4)]/40 hover:text-[var(--frost4)] transition-all duration-150 font-mono font-semibold"
                        >
                            {{ item }}
                        </button>
                    </div>

                    <!-- Error -->
                    <div v-if="form.errors.carnet" class="mt-3 text-[12px] text-[var(--aurora-red)] text-center border border-[var(--aurora-red)]/30 bg-[var(--aurora-red)]/5 py-2 px-3 rounded-xl flex items-center justify-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        {{ form.errors.carnet }}
                    </div>
                </form>
            </div>
        </div>

        <!-- Resultado de búsqueda -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 translate-y-3"
            enter-to-class="opacity-100 translate-y-0"
        >
            <div v-if="results && results.found" class="mt-4 bg-white rounded-2xl shadow-sm border border-[var(--aurora-green)]/30 overflow-hidden">
                <!-- Header resultado -->
                <div class="px-6 py-4 border-b border-[var(--aurora-green)]/20 flex items-center gap-3"
                    style="background: linear-gradient(135deg, color-mix(in srgb, var(--aurora-green) 8%, transparent), transparent);">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                        style="background: color-mix(in srgb, var(--aurora-green) 15%, transparent);">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--aurora-green)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-[14px] font-bold text-[var(--nord0)] tracking-tight">Expediente Encontrado</h3>
                        <p class="text-[11px] text-[var(--aurora-green)] font-medium">Registro localizado en el sistema</p>
                    </div>
                </div>

                <div class="px-6 py-6">
                    <!-- Carnet con avatar -->
                    <div class="flex items-center gap-4 p-4 rounded-xl border border-[var(--nord4)] bg-[var(--surface-subtle)] mb-4">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-[15px] font-bold text-white shrink-0 shadow-sm"
                            style="background: linear-gradient(135deg, var(--frost4), var(--nord9));">
                            {{ (results.carnet || '?').charAt(0) }}
                        </div>
                        <div>
                            <dt class="text-[10px] font-semibold text-[var(--nord3)] uppercase tracking-wider">Carnet Estudiantil</dt>
                            <dd class="text-[16px] font-mono font-bold text-[var(--nord0)] mt-0.5">{{ results.carnet }}</dd>
                        </div>
                        <div class="ml-auto">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold"
                                style="background: color-mix(in srgb, var(--aurora-green) 12%, transparent); color: var(--aurora-green); border: 1px solid color-mix(in srgb, var(--aurora-green) 30%, transparent);">
                                <span class="w-1.5 h-1.5 rounded-full bg-current animate-pulse"></span>
                                Activo
                            </span>
                        </div>
                    </div>

                    <p class="text-[12px] text-[var(--nord3)] text-center leading-relaxed mb-5">
                        Los datos personales están cifrados. Acceda al expediente para consultar información clínica.
                    </p>
                    <div class="flex justify-center">
                        <Link
                            :href="`/pacientes/${results.carnet}`"
                            class="inline-flex items-center gap-2 px-6 py-2.5 text-[13px] font-semibold rounded-xl text-white transition-all duration-200 shadow-md hover:shadow-lg hover:-translate-y-0.5"
                            style="background: linear-gradient(135deg, var(--frost4) 0%, var(--nord9) 100%);"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Ver Expediente Completo
                        </Link>
                    </div>
                </div>
            </div>
        </Transition>

        <p class="mt-5 text-[11px] text-[var(--nord3)] text-center flex items-center justify-center gap-1.5">
            <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            Datos anonimizados · Solo personal autorizado puede acceder al expediente completo
        </p>
    </div>
</template>
