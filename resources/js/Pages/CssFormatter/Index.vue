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
const pageTitle = computed(() => seoData.value.title || 'Formatear y validar CSS online');
const pageDescription = computed(
    () =>
        seoData.value.description ||
        'Pega tu CSS, detecta reglas con errores y obtén una versión formateada lista para copiar o descargar.'
);
const { ogImageUrl } = useOgImage(seoData.value);

const cssInput = ref(`body{margin:0;padding:0;background:#f8fafc;}h1{color:#1e3a8a;font-weight:bold;}`);
const formattedCss = ref('');
const errors = ref([]);
const copied = ref(false);
const downloadUrl = ref('');

const stats = computed(() => ({
    originalLength: cssInput.value.length,
    formattedLength: formattedCss.value.length,
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

function detectCssErrors(cssText) {
    // Usa el parser del navegador vía CSSStyleSheet para detectar reglas inválidas
    try {
        const sheet = new CSSStyleSheet();
        sheet.replaceSync(cssText);
        return [];
    } catch (e) {
        if (e instanceof DOMException || e.message) {
            return [e.message.replace(/\s+/g, ' ').trim()];
        }
        return ['No pudimos validar el CSS. Revisa la sintaxis.'];
    }
}

function beautifyCss(cssText) {
    // Formateo simple basado en llaves y puntos y coma
    let indent = 0;
    const indentChar = '    ';
    let buffer = '';
    const lines = [];

    const pushLine = (line) => {
        if (line.trim().length === 0) return;
        lines.push(`${indentChar.repeat(indent)}${line.trim()}`);
    };

    for (let i = 0; i < cssText.length; i++) {
        const ch = cssText[i];

        if (ch === '{') {
            pushLine(buffer + ' {');
            buffer = '';
            indent++;
        } else if (ch === '}') {
            if (buffer.trim()) {
                pushLine(buffer.trim().endsWith(';') ? buffer : `${buffer.trim()};`);
            }
            buffer = '';
            indent = Math.max(indent - 1, 0);
            pushLine('}');
        } else if (ch === ';') {
            pushLine(buffer + ';');
            buffer = '';
        } else {
            buffer += ch;
        }
    }

    if (buffer.trim()) {
        pushLine(buffer.trim());
    }

    return lines
        .join('\n')
        .replace(/\n{3,}/g, '\n\n')
        .replace(/\s+\{/g, ' {');
}

function formatCss() {
    copied.value = false;
    errors.value = [];
    formattedCss.value = '';
    downloadUrl.value = '';

    const input = (cssInput.value || '').trim();
    if (!input) {
        errors.value = ['Pega algún CSS para formatear.'];
        return;
    }

    try {
        const foundErrors = detectCssErrors(input);
        errors.value = foundErrors;

        const pretty = beautifyCss(input);
        formattedCss.value = pretty;

        const blob = new Blob([pretty], { type: 'text/css' });
        if (downloadUrl.value) {
            URL.revokeObjectURL(downloadUrl.value);
        }
        downloadUrl.value = URL.createObjectURL(blob);
    } catch (error) {
        console.error(error);
        errors.value = ['No pudimos procesar el CSS. Revisa que esté completo.'];
    }
}

async function copyOutput() {
    if (!formattedCss.value) return;
    try {
        await navigator.clipboard.writeText(formattedCss.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1500);
    } catch (error) {
        console.error(error);
    }
}

function downloadFile() {
    if (!downloadUrl.value) return;
    const a = document.createElement('a');
    a.href = downloadUrl.value;
    a.download = 'css-formateado.css';
    document.body.appendChild(a);
    a.click();
    a.remove();
}

const previewText = computed(
    () => formattedCss.value || 'El CSS formateado aparecerá aquí después de procesarlo.'
);

onMounted(() => {
    formatCss();

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
                        <p class="text-uppercase small mb-2 text-info fw-semibold">CSS limpio</p>
                        <h1 class="display-5 fw-bold mb-3">{{ seoData.h1 }}</h1>
                        <p class="lead text-white-50 mb-3">
                            {{ pageDescription }}
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-info text-dark">Detecta errores</span>
                            <span class="badge bg-info text-dark">Formateo legible</span>
                            <span class="badge bg-info text-dark">Copia o descarga</span>
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
                                        <h2 class="h5 fw-semibold mb-1">CSS de entrada</h2>
                                        <p class="text-muted small mb-0">Pega tu código o escríbelo directamente.</p>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" @click="cssInput = ''">
                                            Limpiar
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm" @click="formatCss">
                                            Formatear y validar
                                        </button>
                                    </div>
                                </div>

                                <textarea
                                    v-model="cssInput"
                                    spellcheck="false"
                                    rows="16"
                                    class="form-control font-monospace"
                                    placeholder="Pega tu CSS aquí..."
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
                                        <h2 class="h5 fw-semibold mb-1">CSS formateado</h2>
                                        <p class="text-muted small mb-0">Copia o descarga tu código limpio.</p>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-sm"
                                            @click="copyOutput"
                                            :disabled="!formattedCss"
                                        >
                                            {{ copied ? 'Copiado' : 'Copiar' }}
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-outline-success btn-sm"
                                            @click="downloadFile"
                                            :disabled="!downloadUrl"
                                        >
                                            Descargar
                                        </button>
                                        <span class="badge bg-dark text-white">
                                            {{ stats.formattedLength }} chars
                                        </span>
                                    </div>
                                </div>

                                <div class="flex-grow-1 mb-3">
                                    <pre class="form-control h-100 font-monospace bg-light" style="min-height: 320px; white-space: pre-wrap;">{{ previewText }}</pre>
                                </div>

                                <p class="text-muted small mb-0">
                                    Todo se procesa en tu navegador: no subimos ni guardamos tu CSS.
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
                                        1) Pega tu CSS y haz clic en “Formatear y validar”.
                                    </li>
                                    <li class="list-group-item small">
                                        2) Si el navegador encuentra reglas inválidas, verás el mensaje de error devuelto por el parser.
                                    </li>
                                    <li class="list-group-item small">
                                        3) Copia o descarga el CSS formateado para usarlo en tu proyecto.
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
                                <div class="accordion" id="accordionCssFormatter">
                                    <div class="accordion-item" v-for="(item, index) in seoData.faq" :key="index">
                                        <h2 class="accordion-header" :id="`heading-css-${index}`">
                                            <button
                                                class="accordion-button collapsed"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                :data-bs-target="`#collapse-css-${index}`"
                                                aria-expanded="false"
                                                :aria-controls="`collapse-css-${index}`"
                                            >
                                                <span class="small fw-semibold">
                                                    {{ item.question }}
                                                </span>
                                            </button>
                                        </h2>
                                        <div
                                            :id="`collapse-css-${index}`"
                                            class="accordion-collapse collapse"
                                            :aria-labelledby="`heading-css-${index}`"
                                            data-bs-parent="#accordionCssFormatter"
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
