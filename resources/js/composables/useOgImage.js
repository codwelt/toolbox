import { computed } from 'vue';

export function useOgImage(seo) {
    const defaultOgImagePath = '/toolsbox.png';

    const ogImageUrl = computed(() => {
        const base = seo?.canonical || (typeof window !== 'undefined' ? window.location.origin : '');
        try {
            return new URL(defaultOgImagePath, base).toString();
        } catch (e) {
            return defaultOgImagePath;
        }
    });

    return { ogImageUrl };
}
