import { onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

const INACTIVITY_LIMIT_MS = 60 * 60 * 1000;

const ACTIVITY_EVENTS = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'];

export function useAutoLogout() {
    let timer = null;

    function resetTimer() {
        if (timer) {
            clearTimeout(timer);
        }
        timer = setTimeout(() => {
            router.post('/logout');
        }, INACTIVITY_LIMIT_MS);
    }

    onMounted(() => {
        ACTIVITY_EVENTS.forEach((event) => {
            document.addEventListener(event, resetTimer, { capture: true, passive: true });
        });
        resetTimer();
    });

    onUnmounted(() => {
        if (timer) {
            clearTimeout(timer);
        }
        ACTIVITY_EVENTS.forEach((event) => {
            document.removeEventListener(event, resetTimer, { capture: true });
        });
    });
}
