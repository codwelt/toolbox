import { computed } from 'vue';

export function useOgImage(seo) {
    const ogImageUrl = computed(() => {
        const base = seo?.canonical || (typeof window !== 'undefined' ? window.location.origin : '');
        try {
            return new URL('/unicornio.png', base).toString();
        } catch (e) {
            return '/unicornio.png';
        }
    });

    return { ogImageUrl };
}
