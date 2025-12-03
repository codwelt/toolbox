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
const pageTitle = computed(() => seoData.value.title || 'Convertir JSON a array online');
const pageDescription = computed(
    () =>
        seoData.value.description ||
        'Pega tu JSON, detecta errores y obtén el array listo para copiar, junto al JSON corregido.'
);
const { ogImageUrl } = useOgImage(seoData.value);

const jsonInput = ref(`{"name":"Ada Lovelace","skills":["math","programming"],"active":true}`);
const formattedJson = ref('');
const arrayResult = ref('');
const errors = ref([]);
const copiedJson = ref(false);
const copiedArray = ref(false);
const downloadUrlJson = ref('');
const downloadUrlArray = ref('');

const stats = computed(() => ({
    originalLength: jsonInput.value.length,
    jsonLength: formattedJson.value.length,
    arrayLength: arrayResult.value.length,
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
        applicationCategory: 'DeveloperApplication',
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

function parseJson(text) {
    try {
        return JSON.parse(text);
    } catch (err) {
        return { __error: err.message || 'JSON inválido.' };
    }
}

function buildArrayString(data) {
    if (Array.isArray(data)) {
        return JSON.stringify(data, null, 4);
    }
    if (data && typeof data === 'object') {
        return JSON.stringify(Object.entries(data), null, 4);
    }
    return JSON.stringify([data], null, 4);
}

function formatJsonToArray() {
    errors.value = [];
    formattedJson.value = '';
    arrayResult.value = '';
    copiedJson.value = false;
    copiedArray.value = false;

    if (downloadUrlJson.value) {
        URL.revokeObjectURL(downloadUrlJson.value);
    }
    if (downloadUrlArray.value) {
        URL.revokeObjectURL(downloadUrlArray.value);
    }
    downloadUrlJson.value = '';
    downloadUrlArray.value = '';

    const input = (jsonInput.value || '').trim();
    if (!input) {
        errors.value = ['Pega algún JSON para convertir.'];
        return;
    }

    const parsed = parseJson(input);
    if (parsed && parsed.__error) {
        errors.value = [parsed.__error];
        return;
    }

    try {
        const prettyJson = JSON.stringify(parsed, null, 4);
        formattedJson.value = prettyJson;
        arrayResult.value = buildArrayString(parsed);

        const blobJson = new Blob([prettyJson], { type: 'application/json' });
        const blobArray = new Blob([arrayResult.value], { type: 'application/json' });
        downloadUrlJson.value = URL.createObjectURL(blobJson);
        downloadUrlArray.value = URL.createObjectURL(blobArray);
    } catch (error) {
        console.error(error);
        errors.value = ['No pudimos procesar el JSON.'];
    }
}

async function copyText(value, target) {
    if (!value) return;
    try {
        await navigator.clipboard.writeText(value);
        if (target === 'json') {
            copiedJson.value = true;
            setTimeout(() => (copiedJson.value = false), 1500);
        } else {
            copiedArray.value = true;
            setTimeout(() => (copiedArray.value = false), 1500);
        }
    } catch (error) {
        console.error(error);
    }
}

function downloadFile(url, filename) {
    if (!url) return;
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
}

const previewJson = computed(
    () => formattedJson.value || 'El JSON formateado aparecerá aquí después de procesarlo.'
);
const previewArray = computed(
    () => arrayResult.value || 'El array aparecerá aquí después de convertir el JSON.'
);

onMounted(() => {
    formatJsonToArray();

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
    if (downloadUrlJson.value) {
        URL.revokeObjectURL(downloadUrlJson.value);
    }
    if (downloadUrlArray.value) {
        URL.revokeObjectURL(downloadUrlArray.value);
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
                        <p class="text-uppercase small mb-2 text-info fw-semibold">JSON a array</p>
                        <h1 class="display-5 fw-bold mb-3">{{ seoData.h1 }}</h1>
                        <p class="lead text-white-50 mb-3">
                            {{ pageDescription }}
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-info text-dark">Detecta errores</span>
                            <span class="badge bg-info text-dark">Array listo para copiar</span>
                            <span class="badge bg-info text-dark">Optimizado SEO</span>
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
                                        <h2 class="h5 fw-semibold mb-1">JSON de entrada</h2>
                                        <p class="text-muted small mb-0">Pega tu JSON o escríbelo directamente.</p>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" @click="jsonInput = ''">
                                            Limpiar
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm" @click="formatJsonToArray">
                                            Convertir y validar
                                        </button>
                                    </div>
                                </div>

                                <textarea
                                    v-model="jsonInput"
                                    spellcheck="false"
                                    rows="16"
                                    class="form-control font-monospace"
                                    placeholder='Pega tu JSON aquí...'
                                ></textarea>

                                <div v-if="errors.length" class="alert alert-warning mt-3 mb-0" role="alert">
                                    <p class="fw-semibold mb-2 small">Posibles problemas detectados:</p>
                                    <ul class="mb-0 ps-3 small">
                                        <li v-for="(err, idx) in errors" :key="idx">
                                            {{ err }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h2 class="h5 fw-semibold mb-1">Array (JSON) resultante</h2>
                                        <p class="text-muted small mb-0">Copia o descarga el array o el JSON limpio.</p>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-sm"
                                            @click="copyText(arrayResult, 'array')"
                                            :disabled="!arrayResult"
                                        >
                                            {{ copiedArray ? 'Array copiado' : 'Copiar array' }}
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-outline-success btn-sm"
                                            @click="downloadFile(downloadUrlArray, 'array.json')"
                                            :disabled="!downloadUrlArray"
                                        >
                                            Descargar array
                                        </button>
                                        <span class="badge bg-dark text-white">
                                            {{ stats.arrayLength }} chars
                                        </span>
                                    </div>
                                </div>

                                <div class="flex-grow-1 mb-3">
                                    <pre class="form-control h-100 font-monospace bg-light" style="min-height: 240px; white-space: pre-wrap;">{{ previewArray }}</pre>
                                </div>

                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h3 class="h6 fw-semibold mb-1">JSON formateado</h3>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-sm"
                                            @click="copyText(formattedJson, 'json')"
                                            :disabled="!formattedJson"
                                        >
                                            {{ copiedJson ? 'JSON copiado' : 'Copiar JSON' }}
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-outline-success btn-sm"
                                            @click="downloadFile(downloadUrlJson, 'json-limpio.json')"
                                            :disabled="!downloadUrlJson"
                                        >
                                            Descargar JSON
                                        </button>
                                        <span class="badge bg-secondary text-dark">
                                            {{ stats.jsonLength }} chars
                                        </span>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <pre class="form-control h-100 font-monospace bg-light" style="min-height: 160px; white-space: pre-wrap;">{{ previewJson }}</pre>
                                </div>

                                <p class="text-muted small mt-3 mb-0">
                                    Todo se procesa en tu navegador: no subimos ni guardamos tu JSON.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mt-4">
                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <h2 class="h5 fw-semibold mb-3">Cómo usar esta herramienta</h2>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item small">
                                        1) Pega tu JSON y haz clic en “Convertir y validar”.
                                    </li>
                                    <li class="list-group-item small">
                                        2) Si hay errores de sintaxis, verás el mensaje exacto del parser.
                                    </li>
                                    <li class="list-group-item small">
                                        3) Copia o descarga el array resultante o el JSON corregido.
                                    </li>
                                    <li class="list-group-item small">
                                        4) Todo sucede en el navegador: tu código no se envía a servidores externos.
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <h2 class="h5 fw-semibold mb-3">Preguntas frecuentes</h2>
                                <div class="accordion" id="accordionJsonToArray">
                                    <div class="accordion-item" v-for="(item, index) in seoData.faq" :key="index">
                                        <h2 class="accordion-header" :id="`heading-json-array-${index}`">
                                            <button
                                                class="accordion-button collapsed"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                :data-bs-target="`#collapse-json-array-${index}`"
                                                aria-expanded="false"
                                                :aria-controls="`collapse-json-array-${index}`"
                                            >
                                                <span class="small fw-semibold">
                                                    {{ item.question }}
                                                </span>
                                            </button>
                                        </h2>
                                        <div
                                            :id="`collapse-json-array-${index}`"
                                            class="accordion-collapse collapse"
                                            :aria-labelledby="`heading-json-array-${index}`"
                                            data-bs-parent="#accordionJsonToArray"
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
