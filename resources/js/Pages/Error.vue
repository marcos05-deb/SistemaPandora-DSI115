<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useTheme } from '@/Composables/useTheme';

const props = defineProps({
    status: { type: Number, required: true },
    detail: { type: String, default: null },
});

const page = usePage();
const { isDark, toggleTheme } = useTheme();

const user = computed(() => page.props.auth?.user ?? null);

const catalog = {
    403: {
        title: 'Acceso denegado',
        description:
            'No tienes permisos para ver este recurso. Si crees que deberías poder acceder, contacta a tu coordinador o al administrador del sistema.',
        accent: 'var(--aurora-red)',
    },
    404: {
        title: 'Página no encontrada',
        description:
            'La ruta o el recurso solicitado no existe, fue movido o ya no está disponible.',
        accent: 'var(--frost4)',
    },
    419: {
        title: 'Sesión expirada',
        description:
            'La página caducó por seguridad (token CSRF). Vuelve a iniciar sesión o recarga e inténtalo de nuevo.',
        accent: 'var(--aurora-orange)',
    },
    429: {
        title: 'Demasiadas solicitudes',
        description:
            'Has realizado demasiados intentos en poco tiempo. Espera unos minutos e inténtalo nuevamente.',
        accent: 'var(--aurora-yellow)',
    },
    500: {
        title: 'Error del servidor',
        description:
            'Ocurrió un problema interno al procesar la solicitud. El equipo técnico puede revisar los registros; intenta de nuevo en unos momentos.',
        accent: 'var(--aurora-red)',
    },
    503: {
        title: 'Servicio no disponible',
        description:
            'PANDORA está en mantenimiento o temporalmente fuera de servicio. Vuelve a intentar en unos minutos.',
        accent: 'var(--aurora-purple)',
    },
};

const content = computed(() => catalog[props.status] ?? {
    title: 'Error inesperado',
    description: 'Algo salió mal al procesar tu solicitud.',
    accent: 'var(--frost4)',
});

const description = computed(() => {
    if (props.detail && [403, 404, 419, 429].includes(props.status)) {
        return props.detail;
    }
    return content.value.description;
});

const homeHref = computed(() => {
    if (!user.value) {
        return '/login';
    }
    if (user.value.roles?.includes('sysadmin')) {
        return '/admin/dashboard';
    }
    return '/dashboard';
});

const homeLabel = computed(() => (user.value ? 'Ir al panel' : 'Iniciar sesión'));

function goBack() {
    if (window.history.length > 1) {
        window.history.back();
        return;
    }
    window.location.assign(homeHref.value);
}
</script>

<template>
    <Head :title="`${status} — ${content.title}`" />

    <div class="min-h-screen flex font-sans bg-[var(--nord6)] relative overflow-hidden">
        <!-- Ambiente Nord -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden">
            <div
                class="absolute -top-24 -right-24 w-[420px] h-[420px] rounded-full opacity-[0.12] blur-3xl"
                :style="{ background: content.accent }"
            />
            <div
                class="absolute -bottom-32 -left-20 w-[380px] h-[380px] rounded-full opacity-[0.08] blur-3xl"
                style="background: var(--frost2)"
            />
            <div
                class="absolute inset-0 opacity-[0.35]"
                style="background-image: radial-gradient(circle, var(--nord4) 1px, transparent 1px); background-size: 28px 28px;"
            />
        </div>

        <button
            type="button"
            class="absolute top-4 right-4 z-20 p-2 rounded-lg text-[var(--nord3)] hover:bg-[var(--nord5)] transition-colors"
            :title="isDark ? 'Modo claro' : 'Modo oscuro'"
            @click="toggleTheme"
        >
            <svg v-if="isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5M12 19.5V21M4.5 12H3m18 0h-1.5M6.34 6.34l-1.06-1.06M18.72 18.72l-1.06-1.06M6.34 17.66l-1.06 1.06M18.72 5.28l-1.06 1.06M16.5 12a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
            </svg>
            <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
            </svg>
        </button>

        <div class="relative z-10 flex-1 flex flex-col items-center justify-center px-4 py-16">
            <div class="w-full max-w-lg text-center">
                <div class="mb-8">
                    <div
                        class="w-14 h-14 rounded-2xl mx-auto mb-4 flex items-center justify-center border border-[var(--nord4)] shadow-sm"
                        style="background: linear-gradient(135deg, var(--surface) 0%, var(--nord5) 100%);"
                    >
                        <span class="text-[18px] font-bold" :style="{ color: content.accent }">P</span>
                    </div>
                    <p class="text-[12px] font-semibold tracking-[0.2em] uppercase text-[var(--nord3)]">PANDORA</p>
                </div>

                <p
                    class="text-[72px] sm:text-[88px] font-bold leading-none tracking-tight mb-3 tabular-nums"
                    :style="{ color: content.accent }"
                >
                    {{ status }}
                </p>

                <h1 class="text-[22px] sm:text-[26px] font-bold text-[var(--nord0)] tracking-tight mb-3">
                    {{ content.title }}
                </h1>
                <p class="text-[14px] text-[var(--nord3)] leading-relaxed max-w-md mx-auto mb-10">
                    {{ description }}
                </p>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-3">
                    <Link
                        :href="homeHref"
                        class="group relative inline-flex items-center justify-center gap-2 overflow-hidden rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-md transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5"
                        style="background: linear-gradient(135deg, var(--frost4) 0%, var(--nord9) 100%);"
                    >
                        <span class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/15 to-transparent group-hover:translate-x-full transition-transform duration-500 ease-in-out" />
                        {{ homeLabel }}
                    </Link>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold text-[var(--nord0)] bg-[var(--surface)] border border-[var(--nord4)] shadow-sm hover:bg-[var(--nord5)] transition-colors"
                        @click="goBack"
                    >
                        Volver atrás
                    </button>
                </div>

                <p v-if="user" class="mt-8 text-[12px] text-[var(--nord3)]">
                    Sesión: {{ user.name || user.email }}
                    <span v-if="user.role_label"> · {{ user.role_label }}</span>
                </p>
            </div>
        </div>
    </div>
</template>
