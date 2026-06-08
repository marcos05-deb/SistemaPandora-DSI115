<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/UI/DangerButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import AlertBox from '@/Components/UI/AlertBox.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import Modal from '@/Components/UI/Modal.vue';

defineProps({
    userLabel: String,
    navigation: Array,
    session: Object,
});

const showLogoutConfirm = ref(false);

function confirmLogout() {
    showLogoutConfirm.value = false;
    router.visit('/login', {
        onSuccess: () => {},
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
    <Head title="Cierre de Sesión" />

    <div class="max-w-3xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-[var(--nord0)]">Cierre de Sesión</h1>
            <p class="text-sm text-[var(--nord3)] mt-1">
                Gestione la seguridad de su sesión activa y cierre cuando sea necesario.
            </p>
        </div>

        <div class="bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] p-8 space-y-6">
            <h2 class="text-lg font-semibold text-[var(--nord0)]">Sesión Activa</h2>

            <dl class="space-y-3 text-sm">
                <div class="flex gap-2">
                    <dt class="font-medium text-[var(--nord3)] w-36">Usuario:</dt>
                    <dd class="text-[var(--nord0)]">{{ session.userName }}</dd>
                </div>
                <div class="flex gap-2 items-center">
                    <dt class="font-medium text-[var(--nord3)] w-36">Rol:</dt>
                    <dd><StatusBadge :label="session.role" variant="director" /></dd>
                </div>
                <div class="flex gap-2 items-center flex-wrap">
                    <dt class="font-medium text-[var(--nord3)] w-36">Token JWT:</dt>
                    <dd class="text-[var(--nord0)] font-mono text-xs">
                        {{ session.tokenPreview }}
                        <span v-if="session.tokenValid" class="text-[var(--aurora-green)] font-sans ml-2">(válido)</span>
                    </dd>
                </div>
                <div class="flex gap-2">
                    <dt class="font-medium text-[var(--nord3)] w-36">Expira en:</dt>
                    <dd class="text-[var(--nord0)]">{{ session.expiresIn }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="font-medium text-[var(--nord3)] w-36">Inicio de sesión:</dt>
                    <dd class="text-[var(--nord0)]">{{ session.startedAt }}</dd>
                </div>
            </dl>

            <div class="pt-2">
                <DangerButton type="button" @click="showLogoutConfirm = true">
                    Cerrar Sesión
                </DangerButton>
            </div>
        </div>

        <div class="mt-6 space-y-4">
            <AlertBox variant="warning">
                Por su seguridad, la sesión se cerrará automáticamente tras 15 minutos de inactividad.
                El token JWT será invalidado en el servidor.
            </AlertBox>
            <AlertBox variant="info">
                Al cerrar sesión, el endpoint invalida su token JWT activo. Deberá iniciar sesión nuevamente
                para acceder al sistema.
            </AlertBox>
        </div>
    </div>

    <Modal :show="showLogoutConfirm" title="Confirmar cierre de sesión" @close="showLogoutConfirm = false">
        <p class="text-sm text-[var(--nord3)]">
            Se invalidará el token JWT de demostración y volverá a la pantalla de inicio de sesión.
        </p>
        <template #footer>
            <SecondaryButton type="button" @click="showLogoutConfirm = false">Cancelar</SecondaryButton>
            <DangerButton type="button" @click="confirmLogout">Cerrar sesión</DangerButton>
        </template>
    </Modal>
</template>
