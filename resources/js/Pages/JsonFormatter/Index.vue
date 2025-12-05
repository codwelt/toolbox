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
const pageTitle = computed(() => seoData.value.title || 'Formatear y validar JSON online');
const pageDescription = computed(
    () =>
        seoData.value.description ||
        'Pega tu JSON, detecta errores de sintaxis y obtén una versión formateada lista para copiar o descargar.'
);
const { ogImageUrl } = useOgImage(seoData.value);

const jsonInput = ref(`{"name":"Ada Lovelace","skills":["math","programming"],"active":true}`);
const formattedJson = ref('');
const errors = ref([]);
const copied = ref(false);
const downloadUrl = ref('');
const errorLocation = ref(null);
const textareaRef = ref(null);
const lineNumbersRef = ref(null);
const scrollTop = ref(0);
const history = ref([]);

const editorLineHeight = 24; // px
const editorPadding = 12; // px
const HISTORY_KEY = 'jsonFormatterHistory';
const HISTORY_LIMIT = 10;

const lineCount = computed(() => Math.max(1, jsonInput.value.split(/\r?\n/).length));
const lineNumbersText = computed(() => Array.from({ length: lineCount.value }, (_, idx) => idx + 1).join('\n'));
const errorHighlightStyle = computed(() => {
    if (!errorLocation.value?.line) return null;
    const top = editorPadding + (errorLocation.value.line - 1) * editorLineHeight - scrollTop.value;
    return {
        top: `${top}px`,
        height: `${editorLineHeight}px`,
    };
});

function getErrorLocation(message, text) {
    const match = message?.match(/position\s+(\d+)/i);
    if (!match) return null;

    const index = Math.min(Math.max(Number(match[1]) || 0, 0), text.length);
    const slice = text.slice(0, index);
    const lines = slice.split(/\r?\n/);
    const line = lines.length;
    const column = (lines[lines.length - 1] || '').length + 1;

    return { index, line, column };
}

function translateJsonError(message, location) {
    if (!message) return 'JSON inválido.';

    const rules = [
        {
            pattern: /Unexpected end of JSON input/i,
            translate: 'El JSON está incompleto o tiene llaves/corchetes sin cerrar.',
        },
        {
            pattern: /Unexpected token (.+) in JSON at position (\d+)/i,
            translate: (_match, token, position) =>
                `Token inesperado "${token}" en la posición ${position}. Revisa comas, llaves o corchetes.`,
        },
        {
            pattern: /Unexpected number in JSON at position (\d+)/i,
            translate: (_match, position) => `Número inesperado en la posición ${position}.`,
        },
        {
            pattern: /Unexpected string in JSON at position (\d+)/i,
            translate: (_match, position) => `Cadena inesperada en la posición ${position}.`,
        },
        {
            pattern: /Unexpected boolean in JSON at position (\d+)/i,
            translate: (_match, position) => `Booleano inesperado en la posición ${position}.`,
        },
        {
            pattern: /Unexpected null in JSON at position (\d+)/i,
            translate: (_match, position) => `Valor null inesperado en la posición ${position}.`,
        },
        {
            pattern: /Unexpected control character in string literal in JSON at position (\d+)/i,
            translate: (_match, position) =>
                `Carácter de control inesperado en una cadena en la posición ${position}.`,
        },
        {
            pattern: /Unexpected non-whitespace character after JSON at position (\d+)/i,
            translate: (_match, position) =>
                `Hay contenido extra después del JSON válido en la posición ${position}.`,
        },
    ];

    for (const rule of rules) {
        const match = message.match(rule.pattern);
        if (match) {
            const base = typeof rule.translate === 'function' ? rule.translate(...match) : rule.translate;
            if (location?.line && location?.column) {
                return `${base} (línea ${location.line}, columna ${location.column}).`;
            }
            return base;
        }
    }

    if (location?.line && location?.column) {
        return `Error al parsear JSON en línea ${location.line}, columna ${location.column}: ${message}`;
    }

    return `Error al parsear JSON: ${message}`;
}

const stats = computed(() => ({
    originalLength: jsonInput.value.length,
    formattedLength: formattedJson.value.length,
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

function validateJson(text) {
    try {
        return JSON.parse(text);
    } catch (err) {
        const location = getErrorLocation(err.message, text);
        const message = translateJsonError(err.message, location);
        return { __error: message, __location: location };
    }
}

function formatPartialJson(text, stopIndex) {
    const target = text.slice(0, Math.max(0, stopIndex ?? 0));
    let result = '';
    let indent = 0;
    let inString = false;
    let escape = false;
    const pad = () => ' '.repeat(indent * 4);

    for (let i = 0; i < target.length; i++) {
        const ch = target[i];

        if (inString) {
            result += ch;
            if (escape) {
                escape = false;
            } else if (ch === '\\') {
                escape = true;
            } else if (ch === '"') {
                inString = false;
            }
            continue;
        }

        if (ch === '"') {
            inString = true;
            result += ch;
        } else if (ch === '{' || ch === '[') {
            result += ch;
            indent++;
            result += '\n' + pad();
        } else if (ch === '}' || ch === ']') {
            indent = Math.max(0, indent - 1);
            result = result.trimEnd();
            result += '\n' + pad() + ch;
        } else if (ch === ',') {
            result += ch + '\n' + pad();
        } else if (ch === ':') {
            result += ': ';
        } else if (ch === '\n' || ch === '\r') {
            // normalizamos saltos de línea al formatear
        } else {
            result += ch;
        }
    }

    return result.trimEnd();
}

function formatJson() {
    copied.value = false;
    errors.value = [];
    formattedJson.value = '';
    downloadUrl.value = '';
    errorLocation.value = null;

    const input = jsonInput.value || '';
    if (!input.trim()) {
        errors.value = ['Pega algún JSON para formatear.'];
        return;
    }

    const parsed = validateJson(input);
    if (parsed && parsed.__error) {
        errors.value = [parsed.__error];
        errorLocation.value = parsed.__location || null;
        if (parsed.__location?.index != null) {
            formattedJson.value = formatPartialJson(input, parsed.__location.index);
        }
        downloadUrl.value = '';
        return;
    }

    try {
        const pretty = JSON.stringify(parsed, null, 4);
        formattedJson.value = pretty;
        setDownloadUrl(pretty);
        addToHistory({ input, output: pretty, timestamp: Date.now() });
    } catch (error) {
        console.error(error);
        errors.value = ['No pudimos procesar el JSON.'];
    }
}

async function copyOutput() {
    if (!formattedJson.value) return;
    try {
        await navigator.clipboard.writeText(formattedJson.value);
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
    a.download = 'json-formateado.json';
    document.body.appendChild(a);
    a.click();
    a.remove();
}

const previewText = computed(
    () => formattedJson.value || 'El JSON formateado aparecerá aquí después de procesarlo.'
);

function handleScroll(event) {
    scrollTop.value = event.target.scrollTop;
    if (lineNumbersRef.value) {
        lineNumbersRef.value.scrollTop = event.target.scrollTop;
    }
}

function formatTimestamp(ts) {
    try {
        return new Date(ts).toLocaleString();
    } catch (error) {
        return '';
    }
}

function setDownloadUrl(content) {
    const blob = new Blob([content], { type: 'application/json' });
    if (downloadUrl.value) {
        URL.revokeObjectURL(downloadUrl.value);
    }
    downloadUrl.value = URL.createObjectURL(blob);
}

function loadHistory() {
    try {
        const raw = sessionStorage.getItem(HISTORY_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        if (Array.isArray(parsed)) {
            history.value = parsed.slice(0, HISTORY_LIMIT);
        }
    } catch (error) {
        console.error('No se pudo cargar el historial:', error);
    }
}

function persistHistory() {
    try {
        sessionStorage.setItem(HISTORY_KEY, JSON.stringify(history.value.slice(0, HISTORY_LIMIT)));
    } catch (error) {
        console.error('No se pudo guardar el historial:', error);
    }
}

function addToHistory(entry) {
    const item = {
        input: entry.input,
        output: entry.output,
        timestamp: entry.timestamp || Date.now(),
    };
    history.value = [item, ...history.value.filter((h) => h.output !== item.output)].slice(0, HISTORY_LIMIT);
    persistHistory();
}

function restoreHistory(item) {
    jsonInput.value = item.input || '';
    formattedJson.value = item.output || '';
    errors.value = [];
    errorLocation.value = null;
    copied.value = false;
    if (formattedJson.value) {
        setDownloadUrl(formattedJson.value);
    } else {
        downloadUrl.value = '';
    }
}

onMounted(() => {
    loadHistory();
    formatJson();

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
                        <p class="text-uppercase small mb-2 text-info fw-semibold">JSON limpio</p>
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
                                        <h2 class="h5 fw-semibold mb-1">JSON de entrada</h2>
                                        <p class="text-muted small mb-0">Pega tu JSON o escríbelo directamente.</p>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" @click="jsonInput = ''">
                                            Limpiar
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm" @click="formatJson">
                                            Formatear y validar
                                        </button>
                                    </div>
                                </div>

                                <div class="editor-wrapper border rounded d-flex bg-light-subtle position-relative">
                                    <div v-if="errorHighlightStyle" class="error-highlight" :style="errorHighlightStyle"></div>
                                    <pre
                                        ref="lineNumbersRef"
                                        class="line-numbers small text-muted mb-0 user-select-none"
                                        aria-hidden="true"
                                        v-text="lineNumbersText"
                                    ></pre>
                                    <textarea
                                        ref="textareaRef"
                                        v-model="jsonInput"
                                        spellcheck="false"
                                        rows="16"
                                        class="editor-textarea form-control border-0 rounded-0 flex-grow-1 font-monospace"
                                        placeholder='Pega tu JSON aquí...'
                                        @scroll="handleScroll"
                                    ></textarea>
                                </div>

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
                                        <h2 class="h5 fw-semibold mb-1">JSON formateado</h2>
                                        <p class="text-muted small mb-0">Copia o descarga tu código limpio.</p>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-sm"
                                            @click="copyOutput"
                                            :disabled="!formattedJson"
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
                                        1) Pega tu JSON y haz clic en “Formatear y validar”.
                                    </li>
                                    <li class="list-group-item small">
                                        2) Si hay comas extra, llaves faltantes o sintaxis inválida, verás el mensaje del parser.
                                    </li>
                                    <li class="list-group-item small">
                                        3) Copia o descarga el JSON formateado para usarlo en tu proyecto.
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
                                <div class="accordion" id="accordionJsonFormatter">
                                    <div class="accordion-item" v-for="(item, index) in seoData.faq" :key="index">
                                        <h2 class="accordion-header" :id="`heading-json-${index}`">
                                            <button
                                                class="accordion-button collapsed"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                :data-bs-target="`#collapse-json-${index}`"
                                                aria-expanded="false"
                                                :aria-controls="`collapse-json-${index}`"
                                            >
                                                <span class="small fw-semibold">
                                                    {{ item.question }}
                                                </span>
                                            </button>
                                        </h2>
                                        <div
                                            :id="`collapse-json-${index}`"
                                            class="accordion-collapse collapse"
                                            :aria-labelledby="`heading-json-${index}`"
                                            data-bs-parent="#accordionJsonFormatter"
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

                <div class="row g-4 mt-4">
                    <div class="col-12">
                        <div class="card shadow-sm border-0">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h2 class="h5 fw-semibold mb-0">Historial de esta sesión</h2>
                                    <span class="badge bg-secondary-subtle text-dark" v-if="history.length">
                                        {{ history.length }} entr{{ history.length === 1 ? 'ada' : 'adas' }}
                                    </span>
                                </div>

                                <div v-if="!history.length" class="text-muted small">
                                    Aún no tienes entradas en esta sesión. Formatea un JSON para guardarlo aquí.
                                </div>

                                <ul v-else class="list-group list-group-flush">
                                    <li v-for="(item, idx) in history" :key="idx" class="list-group-item small d-flex">
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold mb-1">
                                                {{ formatTimestamp(item.timestamp) || 'Hace un momento' }}
                                            </div>
                                            <div class="text-muted text-truncate">
                                                {{ (item.output || '').replace(/\\s+/g, ' ').slice(0, 180) || 'JSON vacío' }}
                                            </div>
                                        </div>
                                        <div class="ms-3 d-flex align-items-center">
                                            <button class="btn btn-outline-primary btn-sm" @click="restoreHistory(item)">
                                                Restaurar
                                            </button>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
.editor-wrapper {
    min-height: 360px;
    overflow: hidden;
}

.editor-textarea {
    resize: vertical;
    font-family: 'SFMono-Regular', Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
    line-height: 24px;
    padding: 12px;
    background-color: transparent;
    box-shadow: none;
}

.editor-textarea:focus {
    box-shadow: none;
}

.line-numbers {
    width: 52px;
    padding: 12px 8px 12px 12px;
    line-height: 24px;
    text-align: right;
    overflow: hidden;
    flex-shrink: 0;
    margin: 0;
    white-space: pre;
    background-color: #f8f9fa;
    border-right: 1px solid #e9ecef;
}

.error-highlight {
    position: absolute;
    left: 0;
    right: 0;
    background: rgba(255, 193, 7, 0.25);
    pointer-events: none;
    border-top: 1px solid rgba(255, 193, 7, 0.5);
    border-bottom: 1px solid rgba(255, 193, 7, 0.5);
}
</style>
