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
const pageTitle = computed(() => seoData.value.title || 'Generador de texto Lorem Ipsum');
const pageDescription = computed(
    () =>
        seoData.value.description ||
        'Genera texto Lorem Ipsum según palabras, párrafos, bytes o listas. Copia y descarga tu contenido en segundos.'
);
const { ogImageUrl } = useOgImage(seoData.value);

const presets = {
    default: [
        '¿Qué es Lorem Ipsum?',
        'Lorem Ipsum es simplemente el texto de relleno de las imprentas y archivos de texto. Ha sido el estándar desde el siglo XVI.',
        '¿Por qué lo usamos?',
        'Es un hecho establecido que un lector se distraerá con el contenido legible de una página cuando examine su diseño.',
        '¿De dónde viene?',
        'Se cree que proviene de una obra de Cicerón escrita en el 45 a.C., y ha sido usado desde entonces para maquetas de impresión.',
        '¿Dónde puedo conseguirlo?',
        'Existen muchas variaciones de pasajes de Lorem Ipsum disponibles en la web que puedes copiar y usar.',
    ],
};

const form = ref({
    mode: 'paragraphs',
    paragraphs: 3,
    words: 120,
    bytes: 512,
    listItems: 5,
    includeTitles: true,
});

const output = ref(presets.default.join('\n\n'));
const copied = ref(false);
const downloadUrl = ref('');
const jsonLdScriptEl = ref(null);

const loremWords = `lorem ipsum dolor sit amet consectetur adipiscing elit sed do eiusmod tempor incididunt ut labore et dolore magna aliqua ut enim ad minim veniam quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur excepteur sint occaecat cupidatat non proident sunt in culpa qui officia deserunt mollit anim id est laborum`;
const loremList = loremWords.split(' ');

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

function randomWord() {
    return loremList[Math.floor(Math.random() * loremList.length)] || 'lorem';
}

function generateWords(count) {
    const words = [];
    for (let i = 0; i < count; i++) {
        words.push(randomWord());
    }
    return capitalize(words.join(' ')) + '.';
}

function capitalize(text) {
    if (!text) return '';
    return text.charAt(0).toUpperCase() + text.slice(1);
}

function generateParagraphs(count, includeTitles = false) {
    const paragraphs = [];
    for (let i = 0; i < count; i++) {
        if (includeTitles) {
            paragraphs.push(capitalize(generateWords(4).replace('.', '')));
        }
        paragraphs.push(generateWords(80));
    }
    return paragraphs.join('\n\n');
}

function generateBytes(targetBytes) {
    let text = '';
    while (new Blob([text]).size < targetBytes) {
        text += `${generateWords(12)} `;
    }
    return text.trim().slice(0, targetBytes);
}

function generateList(count) {
    const items = [];
    for (let i = 0; i < count; i++) {
        items.push(`• ${capitalize(generateWords(10))}`);
    }
    return items.join('\n');
}

function generate() {
    const mode = form.value.mode;
    let result = '';

    if (mode === 'paragraphs') {
        result = generateParagraphs(Math.max(1, Number(form.value.paragraphs) || 1), form.value.includeTitles);
    } else if (mode === 'words') {
        result = generateWords(Math.max(5, Number(form.value.words) || 5));
    } else if (mode === 'bytes') {
        result = generateBytes(Math.max(64, Number(form.value.bytes) || 64));
    } else if (mode === 'list') {
        result = generateList(Math.max(3, Number(form.value.listItems) || 3));
    }

    output.value = result;
    buildDownloadUrl(result);
}

function copyOutput() {
    if (!output.value) return;
    navigator.clipboard
        .writeText(output.value)
        .then(() => {
            copied.value = true;
            setTimeout(() => (copied.value = false), 1500);
        })
        .catch((error) => console.error(error));
}

function buildDownloadUrl(text) {
    const blob = new Blob([text], { type: 'text/plain' });
    if (downloadUrl.value) {
        URL.revokeObjectURL(downloadUrl.value);
    }
    downloadUrl.value = URL.createObjectURL(blob);
}

function downloadFile() {
    if (!downloadUrl.value) return;
    const a = document.createElement('a');
    a.href = downloadUrl.value;
    a.download = 'lorem-ipsum.txt';
    document.body.appendChild(a);
    a.click();
    a.remove();
}

onMounted(() => {
    buildDownloadUrl(output.value);

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
                        <p class="text-uppercase small mb-2 text-info fw-semibold">Texto de prueba</p>
                        <h1 class="display-5 fw-bold mb-3">{{ seoData.h1 }}</h1>
                        <p class="lead text-white-50 mb-3">
                            {{ pageDescription }}
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-info text-dark">Párrafos y palabras</span>
                            <span class="badge bg-info text-dark">Listas y títulos</span>
                            <span class="badge bg-info text-dark">Copia o descarga</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-5">
            <div class="container">
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h2 class="h5 fw-semibold mb-1">Configura tu Lorem Ipsum</h2>
                                        <p class="text-muted small mb-0">Elige el tipo de contenido y cantidad.</p>
                                    </div>
                                    <button class="btn btn-outline-secondary btn-sm" @click="output = presets.default.join('\n\n'); buildDownloadUrl(output)">
                                        Restablecer ejemplo
                                    </button>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Tipo de salida</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button
                                            class="btn btn-sm"
                                            :class="form.mode === 'paragraphs' ? 'btn-primary' : 'btn-outline-primary'"
                                            @click="form.mode = 'paragraphs'"
                                            type="button"
                                        >
                                            Párrafos
                                        </button>
                                        <button
                                            class="btn btn-sm"
                                            :class="form.mode === 'words' ? 'btn-primary' : 'btn-outline-primary'"
                                            @click="form.mode = 'words'"
                                            type="button"
                                        >
                                            Palabras
                                        </button>
                                        <button
                                            class="btn btn-sm"
                                            :class="form.mode === 'bytes' ? 'btn-primary' : 'btn-outline-primary'"
                                            @click="form.mode = 'bytes'"
                                            type="button"
                                        >
                                            Bytes
                                        </button>
                                        <button
                                            class="btn btn-sm"
                                            :class="form.mode === 'list' ? 'btn-primary' : 'btn-outline-primary'"
                                            @click="form.mode = 'list'"
                                            type="button"
                                        >
                                            Lista
                                        </button>
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6" v-if="form.mode === 'paragraphs'">
                                        <label class="form-label small fw-semibold">Cantidad de párrafos</label>
                                        <input type="number" class="form-control" v-model.number="form.paragraphs" min="1" />
                                    </div>
                                    <div class="col-md-6" v-if="form.mode === 'paragraphs'">
                                        <label class="form-label small fw-semibold">Incluir títulos</label>
                                        <div class="form-check form-switch mt-1">
                                            <input class="form-check-input" type="checkbox" v-model="form.includeTitles" />
                                            <label class="form-check-label small">Agregar encabezados cortos</label>
                                        </div>
                                    </div>

                                    <div class="col-md-6" v-if="form.mode === 'words'">
                                        <label class="form-label small fw-semibold">Cantidad de palabras</label>
                                        <input type="number" class="form-control" v-model.number="form.words" min="5" />
                                    </div>

                                    <div class="col-md-6" v-if="form.mode === 'bytes'">
                                        <label class="form-label small fw-semibold">Bytes objetivo</label>
                                        <input type="number" class="form-control" v-model.number="form.bytes" min="64" step="16" />
                                    </div>

                                    <div class="col-md-6" v-if="form.mode === 'list'">
                                        <label class="form-label small fw-semibold">Ítems en la lista</label>
                                        <input type="number" class="form-control" v-model.number="form.listItems" min="3" />
                                    </div>
                                </div>

                                <button class="btn btn-primary w-100" type="button" @click="generate">Generar texto</button>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h2 class="h5 fw-semibold mb-1">Texto generado</h2>
                                        <p class="text-muted small mb-0">Copia o descarga el Lorem Ipsum.</p>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <button class="btn btn-outline-primary btn-sm" type="button" @click="copyOutput" :disabled="!output">
                                            {{ copied ? 'Copiado' : 'Copiar' }}
                                        </button>
                                        <button class="btn btn-outline-success btn-sm" type="button" @click="downloadFile" :disabled="!downloadUrl">
                                            Descargar
                                        </button>
                                    </div>
                                </div>

                                <div class="flex-grow-1 mb-3">
                                    <pre class="form-control h-100 font-monospace bg-light" style="min-height: 320px; white-space: pre-wrap;">{{ output }}</pre>
                                </div>

                                <p class="text-muted small mb-0">Todo se genera en tu navegador: no se envía nada a servidores.</p>
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
                                    <li class="list-group-item small">1) Elige si quieres párrafos, palabras, bytes o lista.</li>
                                    <li class="list-group-item small">2) Define la cantidad y, si deseas, títulos para los párrafos.</li>
                                    <li class="list-group-item small">3) Genera, copia o descarga tu Lorem Ipsum.</li>
                                    <li class="list-group-item small">4) Perfecto para maquetas, tests y diseño.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <h2 class="h5 fw-semibold mb-3">Preguntas frecuentes</h2>
                                <div class="accordion" id="accordionLorem">
                                    <div class="accordion-item" v-for="(item, index) in seoData.faq" :key="index">
                                        <h2 class="accordion-header" :id="`heading-lorem-${index}`">
                                            <button
                                                class="accordion-button collapsed"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                :data-bs-target="`#collapse-lorem-${index}`"
                                                aria-expanded="false"
                                                :aria-controls="`collapse-lorem-${index}`"
                                            >
                                                <span class="small fw-semibold">
                                                    {{ item.question }}
                                                </span>
                                            </button>
                                        </h2>
                                        <div
                                            :id="`collapse-lorem-${index}`"
                                            class="accordion-collapse collapse"
                                            :aria-labelledby="`heading-lorem-${index}`"
                                            data-bs-parent="#accordionLorem"
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
pre.form-control {
    resize: none;
}
</style>
