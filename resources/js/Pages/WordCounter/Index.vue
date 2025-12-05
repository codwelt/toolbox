<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppLayout from './../../Layouts/AppLayout.vue';
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
const pageTitle = computed(() => seoData.value.title || 'Herramienta gratuita de contador de palabras');
const pageDescription = computed(
    () =>
        seoData.value.description ||
        'Cuenta palabras, caracteres, oraciones y párrafos al instante. Calcula tiempo de lectura promedio y analiza tu texto para SEO.'
);
const { ogImageUrl } = useOgImage(seoData.value);

const textInput = ref(`La tecnología cambia rápido. Conocer cuántas palabras, caracteres y oraciones tienes ayuda a planificar artículos y posts.

Esta herramienta calcula tiempo de lectura promedio, párrafos y más.`);
const stats = ref({
    words: 0,
    characters: 0,
    charactersNoSpaces: 0,
    sentences: 0,
    paragraphs: 0,
    spaces: 0,
    readingTime: '0 min',
});
const downloadUrl = ref('');
const copied = ref(false);
const jsonLdScriptEl = ref(null);

const previewText = computed(() => textInput.value || 'Pega o escribe tu texto para obtener las métricas.');

const faqStructured = computed(() => {
    const items = seoData.value.faq || [];
    return items.map((item) => ({
        '@type': 'Question',
        name: item.question,
        acceptedAnswer: {
            '@type': 'Answer',
            text: item.answer,
        },
    }));
});

const jsonLd = computed(() =>
    JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'WebApplication',
        name: pageTitle.value,
        url: seoData.value.canonical,
        applicationCategory: 'ProductivityTool',
        description: pageDescription.value,
        offers: {
            '@type': 'Offer',
            price: '0',
            priceCurrency: 'USD',
        },
        potentialAction: {
            '@type': 'UseAction',
            target: seoData.value.canonical,
        },
        mainEntity: {
            '@type': 'FAQPage',
            mainEntity: faqStructured.value,
        },
    })
);

function computeStats(text) {
    const trimmed = text || '';
    const words = (trimmed.match(/\b\w+\b/g) || []).length;
    const characters = trimmed.length;
    const spaces = (trimmed.match(/\s/g) || []).length;
    const charactersNoSpaces = characters - spaces;
    const sentences = (trimmed.match(/[.!?]+/g) || []).length || (trimmed.trim() ? 1 : 0);
    const paragraphs = trimmed.split(/\n+/).filter((p) => p.trim().length > 0).length;
    const minutes = Math.max(1, Math.ceil(words / 200));

    stats.value = {
        words,
        characters,
        charactersNoSpaces: Math.max(0, charactersNoSpaces),
        sentences,
        paragraphs,
        spaces,
        readingTime: `${minutes} min`,
    };
}

function copyText() {
    if (!textInput.value) return;
    navigator.clipboard
        .writeText(textInput.value)
        .then(() => {
            copied.value = true;
            setTimeout(() => (copied.value = false), 1500);
        })
        .catch((error) => console.error(error));
}

function downloadText() {
    const blob = new Blob([textInput.value], { type: 'text/plain' });
    if (downloadUrl.value) {
        URL.revokeObjectURL(downloadUrl.value);
    }
    downloadUrl.value = URL.createObjectURL(blob);

    const a = document.createElement('a');
    a.href = downloadUrl.value;
    a.download = 'texto-analizado.txt';
    document.body.appendChild(a);
    a.click();
    a.remove();
}

onMounted(() => {
    computeStats(textInput.value);

    const el = document.createElement('script');
    el.type = 'application/ld+json';
    el.text = jsonLd.value;
    document.head.appendChild(el);
    jsonLdScriptEl.value = el;
});

onBeforeUnmount(() => {
    if (jsonLdScriptEl.value && jsonLdScriptEl.value.parentNode) {
        jsonLdScriptEl.value.parentNode.removeChild(jsonLdScriptEl.value);
    }
    if (downloadUrl.value) {
        URL.revokeObjectURL(downloadUrl.value);
    }
});
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
                        <p class="text-uppercase small mb-2 text-info fw-semibold">Texto limpio</p>
                        <h1 class="display-5 fw-bold mb-3">{{ seoData.h1 }}</h1>
                        <p class="lead text-white-50 mb-3">
                            {{ pageDescription }}
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-info text-dark">Palabras y caracteres</span>
                            <span class="badge bg-info text-dark">Tiempo de lectura</span>
                            <span class="badge bg-info text-dark">Todo en el navegador</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-5">
            <div class="container">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h2 class="h5 fw-semibold mb-1">Texto de entrada</h2>
                                        <p class="text-muted small mb-0">Pega tu texto o escríbelo directamente.</p>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" @click="textInput = ''">Limpiar</button>
                                        <button type="button" class="btn btn-primary btn-sm" @click="computeStats(textInput)">
                                            Calcular métricas
                                        </button>
                                    </div>
                                </div>

                                <textarea
                                    v-model="textInput"
                                    spellcheck="false"
                                    rows="14"
                                    class="form-control font-monospace"
                                    placeholder="Pega tu texto aquí..."
                                    @input="computeStats(textInput)"
                                ></textarea>

                                <div class="alert alert-info mt-3 mb-0 small" role="alert">
                                    Todo se procesa en tu navegador: no subimos ni guardamos tu texto.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h2 class="h5 fw-semibold mb-1">Resultados</h2>
                                        <p class="text-muted small mb-0">Conteo en tiempo real.</p>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <button type="button" class="btn btn-outline-primary btn-sm" @click="copyText" :disabled="!textInput">
                                            {{ copied ? 'Copiado' : 'Copiar' }}
                                        </button>
                                        <button type="button" class="btn btn-outline-success btn-sm" @click="downloadText" :disabled="!textInput">
                                            Descargar
                                        </button>
                                    </div>
                                </div>

                                <ul class="list-group list-group-flush small flex-grow-1">
                                    <li class="list-group-item d-flex justify-content-between"><span>Palabras</span><span class="fw-semibold">{{ stats.words }}</span></li>
                                    <li class="list-group-item d-flex justify-content-between"><span>Caracteres (con espacios)</span><span class="fw-semibold">{{ stats.characters }}</span></li>
                                    <li class="list-group-item d-flex justify-content-between"><span>Caracteres (sin espacios)</span><span class="fw-semibold">{{ stats.charactersNoSpaces }}</span></li>
                                    <li class="list-group-item d-flex justify-content-between"><span>Espacios</span><span class="fw-semibold">{{ stats.spaces }}</span></li>
                                    <li class="list-group-item d-flex justify-content-between"><span>Oraciones</span><span class="fw-semibold">{{ stats.sentences }}</span></li>
                                    <li class="list-group-item d-flex justify-content-between"><span>Párrafos</span><span class="fw-semibold">{{ stats.paragraphs }}</span></li>
                                    <li class="list-group-item d-flex justify-content-between"><span>Tiempo de lectura</span><span class="fw-semibold">{{ stats.readingTime }}</span></li>
                                </ul>

                                <div class="mt-3 text-muted small">
                                    Estimación de lectura basada en 200 palabras por minuto.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mt-4">
                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <h2 class="h5 fw-semibold mb-3">¿Cómo usar esta herramienta?</h2>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item small">1) Pega tu texto en el área izquierda.</li>
                                    <li class="list-group-item small">2) Mira en vivo las métricas de palabras, caracteres y párrafos.</li>
                                    <li class="list-group-item small">3) Descarga o copia el texto cuando estés listo.</li>
                                    <li class="list-group-item small">4) Úsalo para SEO, publicaciones y guiones.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <h2 class="h5 fw-semibold mb-3">Preguntas frecuentes</h2>
                                <div class="accordion" id="accordionWordCounter">
                                    <div class="accordion-item" v-for="(item, index) in seoData.faq" :key="index">
                                        <h2 class="accordion-header" :id="`heading-word-${index}`">
                                            <button
                                                class="accordion-button collapsed"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                :data-bs-target="`#collapse-word-${index}`"
                                                aria-expanded="false"
                                                :aria-controls="`collapse-word-${index}`"
                                            >
                                                <span class="small fw-semibold">
                                                    {{ item.question }}
                                                </span>
                                            </button>
                                        </h2>
                                        <div
                                            :id="`collapse-word-${index}`"
                                            class="accordion-collapse collapse"
                                            :aria-labelledby="`heading-word-${index}`"
                                            data-bs-parent="#accordionWordCounter"
                                        >
                                            <div class="accordion-body small text-muted">
                                                {{ item.answer }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
textarea.form-control {
    resize: vertical;
    font-family: 'SFMono-Regular', Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
}
</style>
