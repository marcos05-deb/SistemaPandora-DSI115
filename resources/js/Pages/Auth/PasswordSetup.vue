<script setup>
import { ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";

const showPassword = ref(false);

const form = useForm({
  password: "",
  password_confirmation: "",
});

function submitSetup() {
  form.post("/password/setup", {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  });
}
</script>

<template>
  <Head title="Configurar Contraseña" />

  <div
    class="min-h-screen bg-[#F0EBE1] bg-cover bg-center bg-no-repeat flex flex-col items-center justify-center p-4 relative overflow-hidden font-sans"
    style="background-image: url(&quot;/images/background.png&quot;)"
  >
    <div class="w-full max-w-[450px] z-10 space-y-4">
      <div class="bg-white p-8 pb-7 shadow-lg rounded-2xl">
        <h2
          class="text-[22px] font-semibold text-[#1A1816] text-center mb-2 tracking-tight"
        >
          Bienvenido a PANDORA
        </h2>
        <p class="text-center text-[13px] text-gray-500 mb-8">
            Por seguridad, debes establecer una contraseña definitiva antes de ingresar al sistema por primera vez.
        </p>

        <form @submit.prevent="submitSetup" class="space-y-6">
          <!-- Password Field -->
          <div class="relative">
            <input
              id="setup-password"
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="new-password"
              placeholder="Nueva contraseña"
              class="w-full border-0 border-b border-gray-300 px-1 py-2 pr-10 text-[15px] text-gray-900 focus:border-[#1A1816] focus:ring-0 bg-transparent transition-colors placeholder:text-gray-400"
              :class="{ 'border-red-500': form.errors.password }"
            />
            <button
              type="button"
              class="absolute inset-y-0 right-0 flex items-center pr-2 text-gray-400 hover:text-gray-700 transition-colors"
              @click="showPassword = !showPassword"
            >
              <svg v-if="!showPassword" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
            </button>
            <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">
              {{ form.errors.password }}
            </p>
          </div>

          <!-- Password Confirmation Field -->
          <div class="relative">
            <input
              id="setup-password-confirmation"
              v-model="form.password_confirmation"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="new-password"
              placeholder="Confirmar nueva contraseña"
              class="w-full border-0 border-b border-gray-300 px-1 py-2 text-[15px] text-gray-900 focus:border-[#1A1816] focus:ring-0 bg-transparent transition-colors placeholder:text-gray-400"
            />
          </div>

          <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
              <h4 class="text-xs font-semibold text-gray-700 mb-2 uppercase">Política de Seguridad</h4>
              <ul class="text-[11px] text-gray-500 space-y-1 list-disc pl-4">
                  <li>Mínimo 8 caracteres de longitud.</li>
                  <li>Al menos una letra mayúscula y minúscula.</li>
                  <li>Al menos un número (0-9).</li>
                  <li>Al menos un símbolo especial (!@#$%, etc.).</li>
              </ul>
          </div>

          <div class="pt-2">
            <button
              type="submit"
              :disabled="form.processing"
              class="w-full bg-[#2F2B28] text-white py-2.5 px-4 rounded-xl text-[15px] font-medium hover:bg-[#1A1816] transition-colors disabled:opacity-70 flex justify-center items-center"
            >
              <span v-if="form.processing" class="flex items-center gap-2">
                <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                Configurando...
              </span>
              <span v-else>Establecer Contraseña e Ingresar</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
