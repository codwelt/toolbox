<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppLayout from './../../Layouts/AppLayout.vue';
import { useOgImage } from '@/composables/useOgImage';

defineOptions({ layout: AppLayout });

const props = defineProps({ seo: { type: Object, required: true } });

const seoData = computed(() => props.seo || {});
const pageTitle = computed(() => seoData.value.title || 'Generador de códigos de barras online');
const pageDescription = computed(
    () =>
        seoData.value.description ||
        'Genera códigos de barras Code 39 con colores personalizados, fondo y tamaño ajustable. Descarga en PNG sin salir del navegador.'
);
const { ogImageUrl } = useOgImage(seoData.value);

const form = ref({
    text: 'ABC-12345',
    barColor: '#0b2239',
    bgColor: '#ffffff',
    height: 120,
    moduleWidth: 3,
    margin: 12,
    showText: true,
});

const canvasRef = ref(null);
const downloadUrl = ref('');
const copied = ref(false);
const jsonLdScriptEl = ref(null);

const faqStructured = computed(() => {
    const items = seoData.value.faq || [];
    return items.map((item) => ({
        '@type': 'Question',
        name: item.question,
        acceptedAnswer: { '@type': 'Answer', text: item.answer },
    }));
});

const jsonLd = computed(() =>
    JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'WebApplication',
        name: pageTitle.value,
        url: seoData.value.canonical,
        applicationCategory: 'Utility',
        description: pageDescription.value,
        offers: { '@type': 'Offer', price: '0', priceCurrency: 'USD' },
        potentialAction: { '@type': 'UseAction', target: seoData.value.canonical },
        mainEntity: { '@type': 'FAQPage', mainEntity: faqStructured.value },
    })
);

// Code 39 encoding table
const code39 = {
    '0': '101001101101',
    '1': '110100101011',
    '2': '101100101011',
    '3': '110110010101',
    '4': '101001101011',
    '5': '110100110101',
    '6': '101100110101',
    '7': '101001011011',
    '8': '110100101101',
    '9': '101100101101',
    A: '110101001011',
    B: '101101001011',
    C: '110110100101',
    D: '101011001011',
    E: '110101100101',
    F: '101101100101',
    G: '101010011011',
    H: '110101001101',
    I: '101101001101',
    J: '101011001101',
    K: '110101010011',
    L: '101101010011',
    M: '110110101001',
    N: '101011010011',
    O: '110101101001',
    P: '101101101001',
    Q: '101010110011',
    R: '110101011001',
    S: '101101011001',
    T: '101011011001',
    U: '110010101011',
    V: '100110101011',
    W: '110011010101',
    X: '100101101011',
    Y: '110010110101',
    Z: '100110110101',
    '-': '100101011011',
    '.': '110010101101',
    ' ': '100110101101',
    '$': '100100100101',
    '/': '100100101001',
    '+': '100101001001',
    '%': '101001001001',
    '*': '100101101101', // start/stop
};

function sanitizeText(value) {
    return (value || '').toUpperCase().replace(/[^0-9A-Z\. \-$/+%]/g, '');
}

function encodeCode39(data) {
    const cleaned = sanitizeText(data) || 'CODE39';
    const full = `*${cleaned}*`;
    const sequences = [];
    for (const ch of full) {
        const seq = code39[ch];
        if (!seq) continue;
        sequences.push(seq);
    }
    // narrow bar = 1, wide bar = 2.5 * moduleWidth approx using pattern digits (1=bar,0=space) with widths 1/2 for 1/0? Actually code39 uses 9 elements (bars/spaces) widths 1 or 2-3.
    // Here we treat '1' as bar, '0' as space and alternate width 1/2. We'll approximate by single module width and double for wide bits using pairs.
    return { sequences, text: cleaned };
}

function drawBarcode() {
    const { sequences, text } = encodeCode39(form.value.text);
    const moduleWidth = Math.max(1, Number(form.value.moduleWidth) || 2);
    const height = Math.max(60, Number(form.value.height) || 120);
    const margin = Math.max(4, Number(form.value.margin) || 4);

    // Each character is 12 modules (bars/spaces) in our mapping, using width 1 units each. We'll scale by moduleWidth.
    const totalModules = sequences.reduce((sum, seq) => sum + seq.length, 0);
    const width = totalModules * moduleWidth + margin * 2;

    const canvas = canvasRef.value;
    if (!canvas) return;
    canvas.width = width;
    canvas.height = height + (form.value.showText ? 24 : 0) + margin * 2;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    ctx.fillStyle = form.value.bgColor || '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    ctx.fillStyle = form.value.barColor || '#000000';
    let x = margin;
    sequences.forEach((seq) => {
        for (let i = 0; i < seq.length; i++) {
            const bit = seq[i];
            if (bit === '1') {
                ctx.fillRect(x, margin, moduleWidth, height);
            }
            x += moduleWidth;
        }
    });

    if (form.value.showText) {
        ctx.fillStyle = '#333';
        ctx.font = '14px monospace';
        ctx.textAlign = 'center';
        ctx.fillText(text, canvas.width / 2, height + margin + 16);
    }

    updateDownloadUrl();
}

function updateDownloadUrl() {
    const canvas = canvasRef.value;
    if (!canvas) return;
    canvas.toBlob((blob) => {
        if (!blob) return;
        if (downloadUrl.value) URL.revokeObjectURL(downloadUrl.value);
        downloadUrl.value = URL.createObjectURL(blob);
    });
}

function downloadPng() {
    if (!downloadUrl.value) return;
    const a = document.createElement('a');
    a.href = downloadUrl.value;
    a.download = 'codigo-barras.png';
    document.body.appendChild(a);
    a.click();
    a.remove();
}

function copyPng() {
    const canvas = canvasRef.value;
    if (!canvas || !navigator.clipboard || !window.ClipboardItem) return;
    canvas.toBlob((blob) => {
        if (!blob) return;
        navigator.clipboard
            .write([new ClipboardItem({ 'image/png': blob })])
            .then(() => {
                copied.value = true;
                setTimeout(() => (copied.value = false), 1500);
            })
            .catch((error) => console.error(error));
    });
}

onMounted(() => {
    drawBarcode();
    const el = document.createElement('script');
    el.type = 'application/ld+json';
    el.text = jsonLd.value;
    document.head.appendChild(el);
    jsonLdScriptEl.value = el;
});

onBeforeUnmount(() => {
    if (jsonLdScriptEl.value && jsonLdScriptEl.value.parentNode) jsonLdScriptEl.value.parentNode.removeChild(jsonLdScriptEl.value);
    if (downloadUrl.value) URL.revokeObjectURL(downloadUrl.value);
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
                        <p class="text-uppercase small mb-2 text-info fw-semibold">Barras personalizadas</p>
                        <h1 class="display-5 fw-bold mb-3">{{ seoData.h1 }}</h1>
                        <p class="lead text-white-50 mb-3">
                            {{ pageDescription }}
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-info text-dark">Code 39</span>
                            <span class="badge bg-info text-dark">Color y fondo</span>
                            <span class="badge bg-info text-dark">Descarga en PNG</span>
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
                                        <h2 class="h5 fw-semibold mb-1">Configura tu código de barras</h2>
                                        <p class="text-muted small mb-0">Texto, colores y dimensiones.</p>
                                    </div>
                                    <button class="btn btn-outline-secondary btn-sm" type="button" @click="drawBarcode">Generar</button>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Texto (Code 39)</label>
                                    <input type="text" class="form-control" v-model="form.text" @input="form.text = sanitizeText(form.text); drawBarcode()" />
                                    <small class="text-muted">Se permiten letras, números y - . $ / + % espacio.</small>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Color de barras</label>
                                        <input type="color" class="form-control form-control-color" v-model="form.barColor" @input="drawBarcode" />
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Color de fondo</label>
                                        <input type="color" class="form-control form-control-color" v-model="form.bgColor" @input="drawBarcode" />
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Alto (px)</label>
                                        <input type="number" class="form-control" v-model.number="form.height" min="60" max="240" @input="drawBarcode" />
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Grosor (px)</label>
                                        <input type="number" class="form-control" v-model.number="form.moduleWidth" min="1" max="8" @input="drawBarcode" />
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Margen (px)</label>
                                        <input type="number" class="form-control" v-model.number="form.margin" min="4" max="48" @input="drawBarcode" />
                                    </div>
                                    <div class="col-6 d-flex align-items-center">
                                        <div class="form-check mt-3">
                                            <input class="form-check-input" type="checkbox" v-model="form.showText" id="showText" @change="drawBarcode" />
                                            <label class="form-check-label small" for="showText">Mostrar texto</label>
                                        </div>
                                    </div>
                                </div>

                                <button class="btn btn-primary w-100" type="button" @click="drawBarcode">Generar código de barras</button>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h2 class="h5 fw-semibold mb-1">Vista previa</h2>
                                        <p class="text-muted small mb-0">Copia o descarga tu barcode.</p>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <button class="btn btn-outline-primary btn-sm" type="button" @click="copyPng" :disabled="!downloadUrl">
                                            {{ copied ? 'Copiado' : 'Copiar' }}
                                        </button>
                                        <button class="btn btn-outline-success btn-sm" type="button" @click="downloadPng" :disabled="!downloadUrl">
                                            Descargar
                                        </button>
                                    </div>
                                </div>

                                <div class="barcode-preview mb-3 d-flex justify-content-center align-items-center flex-grow-1">
                                    <canvas ref="canvasRef" class="img-fluid shadow-sm"></canvas>
                                </div>

                                <p class="text-muted small mb-0">Todo se genera en tu navegador: no almacenamos tu información.</p>
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
                                    <li class="list-group-item small">1) Escribe el texto (Code 39) que quieres codificar.</li>
                                    <li class="list-group-item small">2) Ajusta colores, tamaño, margen y decide si mostrar el texto debajo.</li>
                                    <li class="list-group-item small">3) Genera y copia o descarga el PNG.</li>
                                    <li class="list-group-item small">4) Úsalo en etiquetas, envíos o inventario.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <h2 class="h5 fw-semibold mb-3">Preguntas frecuentes</h2>
                                <div class="accordion" id="accordionBarcode">
                                    <div class="accordion-item" v-for="(item, index) in seoData.faq" :key="index">
                                        <h2 class="accordion-header" :id="`heading-barcode-${index}`">
                                            <button
                                                class="accordion-button collapsed"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                :data-bs-target="`#collapse-barcode-${index}`"
                                                aria-expanded="false"
                                                :aria-controls="`collapse-barcode-${index}`"
                                            >
                                                <span class="small fw-semibold">
                                                    {{ item.question }}
                                                </span>
                                            </button>
                                        </h2>
                                        <div
                                            :id="`collapse-barcode-${index}`"
                                            class="accordion-collapse collapse"
                                            :aria-labelledby="`heading-barcode-${index}`"
                                            data-bs-parent="#accordionBarcode"
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
.barcode-preview {
    min-height: 260px;
    background: #f8f9fa;
    border-radius: 12px;
    padding: 16px;
}

canvas {
    max-width: 100%;
    height: auto;
}
</style>
