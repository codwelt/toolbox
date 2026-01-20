<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useOgImage } from '@/composables/useOgImage';

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    seo: {
        type: Object,
        required: true,
    },
});

const seoData = computed(() => props.seo || {});
const pageTitle = computed(() => seoData.value.title || 'Codificar Base64 online gratis');
const pageDescription = computed(
    () => seoData.value.description || 'Convierte texto plano a Base64 directamente en tu navegador sin tocar servidores.'
);
const { ogImageUrl } = useOgImage(seoData.value);

const plainText = ref('Ejemplo: codifica este texto a Base64 para usarlo en encabezados o variables.');
const copyFeedback = ref('');

function toBase64(value) {
    if (!value) return '';
    try {
        return btoa(
            encodeURIComponent(value)
                .replace(/%([0-9A-F]{2})/g, (_, hex) => String.fromCharCode(parseInt(hex, 16)))
        );
    } catch {
        return '';
    }
}

const encodedText = computed(() => toBase64(plainText.value));

const stats = computed(() => ({
    characters: plainText.value.length,
    encoded: encodedText.value.length,
}));

async function copyBase64() {
    if (!encodedText.value) return;
    try {
        await navigator.clipboard.writeText(encodedText.value);
        copyFeedback.value = 'Base64 copiado al portapapeles';
        setTimeout(() => (copyFeedback.value = ''), 1800);
    } catch (error) {
        console.error(error);
        copyFeedback.value = 'No se pudo copiar automáticamente';
        setTimeout(() => (copyFeedback.value = ''), 1800);
    }
}

function clearInput() {
    plainText.value = '';
    copyFeedback.value = '';
}
</script>

<template>
    <div class="bg-light">
        <Head :title="pageTitle">
            <meta name="description" :content="pageDescription" />
            <meta v-if="seoData.keywords && seoData.keywords.length" name="keywords" :content="seoData.keywords.join(', ')" />
            <meta name="robots" content="index,follow" />
            <meta property="og:type" content="website" />
            <meta property="og:title" :content="pageTitle" />
            <meta property="og:description" :content="pageDescription" />
            <meta property="og:url" :content="seoData.canonical" />
            <meta property="og:image" :content="ogImageUrl" />
            <meta property="og:image:alt" :content="pageTitle" />
            <meta name="twitter:card" content="summary_large_image" />
            <meta name="twitter:title" :content="pageTitle" />
            <meta name="twitter:description" :content="pageDescription" />
            <meta name="twitter:image" :content="ogImageUrl" />
            <link rel="canonical" :href="seoData.canonical" />
        </Head>

        <section class="py-5 bg-dark text-white">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-10">
                        <p class="text-uppercase small mb-2 text-info fw-semibold">Base64</p>
                        <h1 class="display-5 fw-bold mb-3">{{ seoData.h1 }}</h1>
                        <p class="lead text-white-50 mb-3">
                            {{ pageDescription }}
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-info text-dark">Sin subir archivos</span>
                            <span class="badge bg-info text-dark">Multilenguaje</span>
                            <span class="badge bg-info text-dark">Listo para APIs</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="container py-5">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h2 class="h6 fw-semibold mb-1">Texto a codificar</h2>
                                    <p class="small text-muted mb-0">Escribe o pega cualquier contenido UTF-8.</p>
                                </div>
                                <button type="button" class="btn btn-link btn-sm text-decoration-none" @click="clearInput">
                                    Limpiar
                                </button>
                            </div>
                            <textarea
                                class="form-control flex-grow-1 font-monospace border-0 bg-light p-3"
                                rows="9"
                                v-model="plainText"
                                placeholder="Escribe aquí el texto que quieras codificar..."
                            ></textarea>
                            <div class="d-flex justify-content-between align-items-center mt-3 small text-muted">
                                <span>Caracteres: <strong>{{ stats.characters }}</strong></span>
                                <span>Base64: <strong>{{ stats.encoded }}</strong></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100 d-flex flex-column">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h2 class="h6 fw-semibold mb-1">Salida Base64</h2>
                                    <p class="small text-muted mb-0">Resultado listo para copiar.</p>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" :disabled="!encodedText" @click="copyBase64">
                                    Copiar por lotes
                                </button>
                            </div>
                            <textarea
                                class="form-control flex-grow-1 font-monospace border-0 bg-light p-3"
                                rows="9"
                                :value="encodedText"
                                readonly
                            ></textarea>
                            <div class="d-flex justify-content-between align-items-center mt-3 gap-2 flex-wrap">
                                <span class="small text-muted flex-grow-1">{{ copyFeedback }}</span>
                                <span class="badge bg-secondary-subtle text-dark">Base64 {{ encodedText ? 'generado' : 'vacío' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h3 class="h6 fw-bold mb-3">¿Qué es Base64?</h3>
                            <p class="mb-2 small text-muted">
                                Base64 es un esquema de codificación que convierte datos binarios (texto, imágenes o archivos) en texto plano seguro para incluir en URLs, JSON o encabezados HTTP.
                            </p>
                            <p class="small text-muted mb-0">
                                Esta herramienta trabaja completamente en tu navegador. No almacenamos ni transmitimos ninguno de los textos que ingresas.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
