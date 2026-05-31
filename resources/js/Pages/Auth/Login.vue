<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';

defineOptions({ layout: GuestLayout });

const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
});

const hasErrors = computed(() => {
    return Object.keys(form.errors).length > 0;
});

function submitLogin() {
    form.post('/login', {
        preserveScroll: true,
        onFinish: () => {
            if (form.hasErrors) {
                form.reset('password');
            }
        },
    });
}
</script>

<template>
    <Head title="Iniciar Sesión" />

    <div class="w-full max-w-md">
        <div class="bg-white rounded-lg shadow-md border border-gray-100 p-8">
            <h2 class="text-2xl font-bold text-gray-900">Iniciar Sesión</h2>
            <p class="text-sm text-gray-500 mt-1 mb-6">
                Ingrese sus credenciales para acceder al sistema
            </p>

            <!-- Error general de sesión -->
            <div
                v-if="form.errors.session"
                class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"
            >
                {{ form.errors.session }}
            </div>

            <!-- Aviso de rate limiting -->
            <div
                v-if="form.errors.throttle"
                class="mb-4 rounded-md bg-orange-50 border border-orange-200 px-4 py-3 text-sm text-orange-700"
            >
                {{ form.errors.throttle }}
            </div>

            <form class="space-y-5" @submit.prevent="submitLogin">
                <div>
                    <label for="login-email" class="block text-sm font-medium text-gray-700 mb-1">
                        Correo Electrónico
                    </label>
                    <input
                        id="login-email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        placeholder="correo@ejemplo.com"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                        :class="{ 'border-red-300': form.errors.email }"
                    />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label for="login-password" class="block text-sm font-medium text-gray-700 mb-1">
                        Contraseña
                    </label>
                    <div class="relative">
                        <input
                            id="login-password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="current-password"
                            placeholder="••••••••••••••"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm pr-10"
                            :class="{ 'border-red-300': form.errors.password }"
                        />
                        <button
                            type="button"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
                            @click="showPassword = !showPassword"
                        >
                            <svg v-if="!showPassword" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        </button>
                    </div>
                    <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                </div>

                <PrimaryButton type="submit" class="w-full" :disabled="form.processing">
                    <span v-if="form.processing" class="flex items-center justify-center gap-2">
                        <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        Autenticando...
                    </span>
                    <span v-else>Iniciar Sesión</span>
                </PrimaryButton>
            </form>
        </div>

        <div class="mt-6 w-full max-w-md rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
            <strong>Seguridad:</strong> Su cuenta se bloqueará tras 5 intentos fallidos durante 15 minutos.
        </div>

        <p class="mt-6 text-center text-xs text-gray-400">
            PANDORA — Sistema de Gestión Clínica · v1.0
        </p>
    </div>
</template>
