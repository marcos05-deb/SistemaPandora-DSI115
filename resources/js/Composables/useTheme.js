import { ref, watch, onMounted } from 'vue';

export function useTheme() {
    const isDark = ref(false);

    // Initial load
    onMounted(() => {
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            isDark.value = savedTheme === 'dark';
        } else {
            // Check system preference
            isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches;
        }
        applyTheme();
    });

    watch(isDark, () => {
        localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
        applyTheme();
    });

    function applyTheme() {
        if (isDark.value) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }

    function toggleTheme() {
        isDark.value = !isDark.value;
    }

    return {
        isDark,
        toggleTheme
    };
}
