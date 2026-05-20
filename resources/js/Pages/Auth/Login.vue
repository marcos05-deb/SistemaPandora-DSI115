<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import Modal from '@/Components/UI/Modal.vue';

defineOptions({ layout: GuestLayout });

const showForgot = ref(false);

const form = useForm({
    email: '',
    password: '',
});

function submitLogin() {
    form.post('/login', {
        preserveScroll: true,
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

            <form class="space-y-5" @submit.prevent="submitLogin">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Usuario o Correo Electrónico
                    </label>
                    <input
                        v-model="form.email"
                        type="text"
                        placeholder="ej. dr.martinez@clinica.org"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                        :class="{ 'border-red-300': form.errors.email }"
                    />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Contraseña
                    </label>
                    <input
                        v-model="form.password"
                        type="password"
                        placeholder="••••••••"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                        :class="{ 'border-red-300': form.errors.password }"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                </div>

                <PrimaryButton type="submit" class="w-full" :disabled="form.processing">
                    Iniciar Sesión
                </PrimaryButton>

                <button
                    type="button"
                    class="text-sm text-blue-600 hover:text-blue-800"
                    @click="showForgot = true"
                >
                    ¿Olvidaste tu contraseña?
                </button>
            </form>
        </div>

        <div class="mt-6 w-full max-w-md rounded-lg bg-orange-50 border border-orange-200 px-4 py-3 text-sm text-orange-900">
            Por seguridad, su cuenta se bloqueará tras 3 intentos fallidos por 15 minutos.
        </div>

        <p class="mt-8 text-center text-xs text-gray-400">
            Acceso rápido (demo) —
            <Link href="/usuarios" class="text-blue-600 hover:underline">Usuarios</Link>
            ·
            <Link href="/codigos-privacidad" class="text-blue-600 hover:underline">Códigos</Link>
            ·
            <Link href="/registro-paciente" class="text-blue-600 hover:underline">Registro</Link>
            ·
            <Link href="/busqueda-segura" class="text-blue-600 hover:underline">Búsqueda</Link>
            ·
            <Link href="/cierre-sesion" class="text-blue-600 hover:underline">Sesión</Link>
        </p>
    </div>

    <Modal :show="showForgot" title="Recuperar contraseña" @close="showForgot = false">
        <p class="text-sm text-gray-600">
            En producción se enviará un enlace al correo registrado. Esta acción es solo demostrativa.
        </p>
        <template #footer>
            <PrimaryButton type="button" @click="showForgot = false">Entendido</PrimaryButton>
        </template>
    </Modal>
</template>
