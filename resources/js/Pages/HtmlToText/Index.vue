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
const pageTitle = computed(() => seoData.value.title || 'Convertir HTML a texto plano online');
const pageDescription = computed(
    () =>
        seoData.value.description ||
        'Convierte código HTML en texto plano sin etiquetas ni estilos directamente en tu navegador.'
);
const { ogImageUrl } = useOgImage(seoData.value);

const htmlInput = ref(`<section>
    <h1>Hola mundo</h1>
    <p>Esto es un pequeño <strong>ejemplo</strong> con enlaces, saltos de línea<br>y listas.</p>
    <ul>
        <li>Primer punto</li>
        <li>Segundo punto</li>
    </ul>
</section>`);
const plainText = ref('');
const collapseWhitespace = ref(true);
const preserveLineBreaks = ref(true);
const errorMessage = ref('');
const copied = ref(false);

const stats = computed(() => ({
    lines: plainText.value ? plainText.value.split(/\n/).length : 0,
    characters: plainText.value.length,
}));

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
        applicationCategory: 'UtilitiesApplication',
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

const jsonLdScriptEl = ref(null);

function normalizeHtml(html) {
    let normalized = html;

    if (preserveLineBreaks.value) {
        normalized = normalized
            .replace(/<(br\s*\/?)>/gi, '$&\n')
            .replace(/<(\/p|\/div|\/li|\/tr|\/h[1-6])>/gi, '$&\n');
    }

    const container = document.createElement('div');
    container.innerHTML = normalized;
    let text = container.textContent || container.innerText || '';

    if (collapseWhitespace.value) {
        text = text.replace(/[ \t]+/g, ' ');
        text = text.replace(/\s*\n\s*/g, '\n');
        text = text.replace(/\n{3,}/g, '\n\n');
        text = text.trim();
    }

    return text;
}

function convertHtml() {
    copied.value = false;
    errorMessage.value = '';
    const value = (htmlInput.value || '').trim();

    if (!value) {
        plainText.value = '';
        errorMessage.value = 'Pega algún fragmento de HTML para convertirlo.';
        return;
    }

    try {
        plainText.value = normalizeHtml(value);
    } catch (error) {
        console.error(error);
        errorMessage.value = 'No pudimos procesar el HTML. Revisa que el código esté completo.';
    }
}

async function copyOutput() {
    if (!plainText.value) return;
    try {
        await navigator.clipboard.writeText(plainText.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1500);
    } catch (error) {
        console.error(error);
    }
}

function clearInput() {
    htmlInput.value = '';
    plainText.value = '';
    errorMessage.value = '';
    copied.value = false;
}

const previewText = computed(
    () => plainText.value || 'El texto plano aparecerá aquí después de convertir tu HTML.'
);

onMounted(() => {
    convertHtml();

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
                            Convierte HTML en texto plano sin etiquetas ni estilos. Todo ocurre en tu navegador, sin subir archivos.
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-info text-dark">Elimina etiquetas</span>
                            <span class="badge bg-info text-dark">Respeta saltos de línea</span>
                            <span class="badge bg-info text-dark">Optimizado para SEO</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-5">
            <div class="container">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h2 class="h5 fw-semibold mb-1">HTML de entrada</h2>
                                        <p class="text-muted small mb-0">Pega tu código o escríbelo directamente.</p>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" @click="clearInput">
                                            Limpiar
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm" @click="convertHtml">
                                            Convertir a texto
                                        </button>
                                    </div>
                                </div>

                                <textarea
                                    v-model="htmlInput"
                                    spellcheck="false"
                                    rows="14"
                                    class="form-control mb-3 font-monospace"
                                    placeholder="<p>Ingresa tu HTML aquí...</p>"
                                ></textarea>

                                <div class="border rounded p-3 bg-light">
                                    <div class="form-check form-check-inline">
                                        <input
                                            v-model="preserveLineBreaks"
                                            class="form-check-input"
                                            type="checkbox"
                                            id="preserveBreaks"
                                        />
                                        <label class="form-check-label" for="preserveBreaks">
                                            Conservar saltos de línea de párrafos y listas
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input
                                            v-model="collapseWhitespace"
                                            class="form-check-input"
                                            type="checkbox"
                                            id="collapseWhitespace"
                                        />
                                        <label class="form-check-label" for="collapseWhitespace">
                                            Compactar espacios y limpiar espacios extra
                                        </label>
                                    </div>
                                </div>

                                <div v-if="errorMessage" class="alert alert-danger mt-3 mb-0" role="alert">
                                    {{ errorMessage }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h2 class="h5 fw-semibold mb-1">Texto plano</h2>
                                        <p class="text-muted small mb-0">Copia o reutiliza el resultado sin etiquetas.</p>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-sm"
                                            @click="copyOutput"
                                            :disabled="!plainText"
                                        >
                                            {{ copied ? 'Copiado' : 'Copiar' }}
                                        </button>
                                        <span class="badge bg-dark text-white">
                                            {{ stats.lines }} líneas · {{ stats.characters }} caracteres
                                        </span>
                                    </div>
                                </div>

                                <div class="flex-grow-1 mb-3">
                                    <pre class="form-control h-100 font-monospace bg-light" style="min-height: 320px; white-space: pre-wrap;">{{ previewText }}</pre>
                                </div>

                                <p class="text-muted small mb-0">
                                    El procesado ocurre en tu navegador: no guardamos ni enviamos tu HTML a ningún servidor.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
