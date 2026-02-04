<script setup>
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppLayout from './../../Layouts/AppLayout.vue';
import { useOgImage } from '@/composables/useOgImage';

defineOptions({ layout: AppLayout });

const props = defineProps({ seo: { type: Object, required: true } });

const seoData = computed(() => props.seo || {});
const pageTitle = computed(() => seoData.value.title || 'Corrector ortográfico online');
const pageDescription = computed(
    () =>
        seoData.value.description ||
        'Corrige faltas de ortografía de páginas y textos en segundos con un motor que sugiere alternativas y explica el fallo.'
);
const { ogImageUrl } = useOgImage(seoData.value);

const initialText = `La analisis ortográfico detecta errores y los muestra con sugerencias claras. Pega tu texto o una URL y descubre cómo mejorar tu redacción.`;
const textInput = ref(initialText);
const urlInput = ref('');
const analysis = ref(null);
const loading = ref(false);
const error = ref('');
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

const highlightedTextHtml = computed(() => {
    if (!analysis.value) return '';

    const text = analysis.value.text || '';
    if (!analysis.value.matches?.length) {
        return escapeHtml(text);
    }

    const matches = [...analysis.value.matches].sort((a, b) => (a.offset || 0) - (b.offset || 0));
    let cursor = 0;
    const segments = [];

    matches.forEach((match) => {
        const matchOffset = Math.max(0, match.offset || 0);
        const matchLength = Math.max(0, match.length || 0);
        const start = Math.max(cursor, matchOffset);
        const end = Math.min(text.length, matchOffset + matchLength);

        if (start > cursor) {
            segments.push({ text: text.slice(cursor, start), highlight: false });
        }

        if (end > start) {
            segments.push({ text: text.slice(start, end), highlight: true });
            cursor = end;
        }
    });

    if (cursor < text.length) {
        segments.push({ text: text.slice(cursor), highlight: false });
    }

    return segments
        .map((segment) => {
            if (!segment.text) return '';
            const escaped = escapeHtml(segment.text);
            return segment.highlight
                ? `<span class="bg-danger-subtle text-danger px-1 rounded">${escaped}</span>`
                : escaped;
        })
        .join('');
});

const effectiveMaxLength = computed(() => analysis.value?.max_length || 8000);

const issueBreakdown = computed(() => {
    if (!analysis.value) return [];
    const counts = {};
    (analysis.value.matches || []).forEach((match) => {
        const label = match.issue_type_label || 'Otros';
        counts[label] = (counts[label] || 0) + 1;
    });
    return Object.entries(counts).map(([label, count]) => ({ label, count }));
});

function escapeHtml(value) {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

async function runAnalysis(payload, hydrateText = false) {
    error.value = '';
    analysis.value = null;
    loading.value = true;

    try {
        const { data } = await axios.post('/api/tools/orthography-checker', payload);
        analysis.value = data;
        if (hydrateText && data.text) {
            textInput.value = data.text;
        }
        if (data.url) {
            urlInput.value = data.url;
        }
    } catch (err) {
        console.error(err);
        if (err.response?.data?.message) {
            error.value = err.response.data.message;
        } else {
            error.value = 'No pudimos analizar el texto en este momento. Intenta con otro contenido.';
        }
    } finally {
        loading.value = false;
    }
}

function analyzeText() {
    const text = (textInput.value || '').trim();
    if (!text) {
        error.value = 'Escribe o pega un texto antes de analizar.';
        return;
    }
    runAnalysis({ text });
}

function analyzeUrl() {
    const url = (urlInput.value || '').trim();
    if (!url) {
        error.value = 'Escribe una URL pública para cargar su contenido.';
        return;
    }
    runAnalysis({ url }, true);
}

onMounted(() => {
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
                        <p class="text-uppercase small mb-2 text-info fw-semibold">Texto preciso</p>
                        <h1 class="display-5 fw-bold mb-3">{{ seoData.h1 || pageTitle }}</h1>
                        <p class="lead text-white-50 mb-3">
                            {{ pageDescription }}
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-info text-dark">Ortografía en español</span>
                            <span class="badge bg-info text-dark">Detecta palabras y frases repetidas</span>
                            <span class="badge bg-info text-dark">Correcciones claras</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="container py-5">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h2 class="h5 fw-semibold mb-0">Analiza tu texto o una página</h2>
                                    <p class="small text-muted mb-0">LanguageTool identifica faltas, mayúsculas y duplicados.</p>
                                </div>
                                <span class="badge bg-primary-subtle text-primary border">IA asistida</span>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">Texto para revisar</label>
                                <textarea
                                    v-model="textInput"
                                    rows="6"
                                    class="form-control form-control-lg rounded-3"
                                    placeholder="Pega tu texto aquí"
                                ></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">O pega una URL pública</label>
                                <div class="input-group">
                                    <input
                                        v-model="urlInput"
                                        type="url"
                                        class="form-control"
                                        placeholder="https://example.com"
                                        :disabled="loading"
                                    />
                                    <button class="btn btn-outline-primary" type="button" :disabled="loading" @click="analyzeUrl">
                                        {{ loading ? 'Cargando...' : 'Cargar desde URL' }}
                                    </button>
                                </div>
                                <p class="small text-muted mt-1 mb-0">
                                    Límite de {{ effectiveMaxLength }} caracteres. Usamos LanguageTool en tu navegador.
                                </p>
                            </div>

                            <div class="d-flex gap-2">
                                <button class="btn btn-primary" type="button" :disabled="loading" @click="analyzeText">
                                    <span v-if="loading" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                    {{ loading ? 'Analizando...' : 'Analizar ortografía' }}
                                </button>
                                <button class="btn btn-outline-secondary" type="button" :disabled="loading" @click="textInput = ''">
                                    Limpiar texto
                                </button>
                            </div>

                            <div v-if="error" class="alert alert-danger small mt-3 mb-0" role="alert">
                                {{ error }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <h3 class="h6 fw-semibold mb-3">¿Qué revisa?</h3>
                            <ul class="list-unstyled small text-muted mb-0">
                                <li class="mb-2">• Faltas de ortografía y palabras mal escritas.</li>
                                <li class="mb-2">• Mayúsculas incorrectas y uso redundante de palabras.</li>
                                <li class="mb-2">• Recomendaciones de corrección y explicaciones.</li>
                                <li class="mb-2">• Resumen del texto analizado y longitud.</li>
                                <li>• Lectura desde una URL pública o texto copiado.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="analysis" class="row g-4 mt-4">
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h3 class="h6 fw-semibold mb-1">Texto analizado</h3>
                                    <p class="small text-muted mb-0">
                                        Fuente:
                                        <span class="fw-semibold">{{ analysis.source === 'url' ? 'URL cargada' : 'Texto manual' }}</span>
                                        • {{ analysis.input_length }} caracteres leídos (analizados {{ analysis.analyzed_length }})
                                        <span v-if="analysis.truncated">• Texto recortado a {{ analysis.max_length }} caracteres.</span>
                                    </p>
                                </div>
                                <span class="badge bg-success-subtle text-success" v-if="analysis.matches_count === 0">Sin errores detectados</span>
                                <span class="badge bg-warning-subtle text-warning" v-else>Errores detectados: {{ analysis.matches_count }}</span>
                            </div>

                            <div v-if="analysis.used_fallback" class="alert alert-info small mb-3">
                                No pudimos acceder directamente a la URL, se usó un servicio público de lectura.
                            </div>

                            <div class="small text-muted mb-3" v-if="analysis.max_length">
                                <span class="me-3">Máximo {{ analysis.max_length }} caracteres por análisis.</span>
                            </div>

                            <div class="border rounded-3 p-3 bg-white text-dark" style="min-height: 220px;">
                                <div v-html="highlightedTextHtml"></div>
                            </div>

                            <div v-if="issueBreakdown.length" class="d-flex flex-wrap gap-2 mt-3">
                                <span
                                    v-for="item in issueBreakdown"
                                    :key="item.label"
                                    class="badge bg-secondary-subtle text-secondary border fw-semibold"
                                >
                                    {{ item.label }} • {{ item.count }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h3 class="h6 fw-semibold mb-3">Resumen rápido</h3>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Errores detectados</span>
                                <span class="fw-semibold">{{ analysis.matches_count }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Texto analizado</span>
                                <span class="fw-semibold">{{ Math.min(analysis.analyzed_length, analysis.max_length) }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Fuente</span>
                                <span class="fw-semibold">{{ analysis.source === 'url' ? 'URL' : 'Texto' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="analysis && analysis.matches.length" class="row g-4 mt-3">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h3 class="h6 fw-semibold mb-3">Detalles de los errores</h3>

                            <div class="list-group">
                                <article
                                    v-for="match in analysis.matches"
                                    :key="match.id"
                                    class="list-group-item list-group-item-action border-0 rounded-3 mb-3 p-3 shadow-sm"
                                >
                                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                                        <div>
                                            <p class="mb-1 fw-semibold text-dark">{{ match.word || 'Fragmento detectado' }}</p>
                                            <p class="small mb-0 text-muted">
                                                {{ match.sentence || 'Sin oración de referencia' }}
                                            </p>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-danger-subtle text-danger border fw-semibold me-1">
                                                {{ match.issue_type_label || 'Otros' }}
                                            </span>
                                            <span class="badge bg-light text-secondary border fw-semibold">
                                                {{ match.rule || 'Regla personalizada' }}
                                            </span>
                                        </div>
                                    </div>

                                    <p class="small text-body-secondary mb-1">{{ match.message || match.short_message }}</p>

                                    <p class="small mb-1 text-muted">
                                        <span class="fw-semibold text-dark">Contexto:</span>
                                        {{ match.context_text || 'Sin contexto adicional.' }}
                                    </p>

                                    <div v-if="match.replacements && match.replacements.length" class="small text-muted">
                                        <span class="fw-semibold">Correcciones sugeridas:</span>
                                        <span class="text-success">{{ match.replacements.join(', ') }}</span>
                                    </div>

                                    <div v-if="match.rule_url" class="mt-2">
                                        <a class="small text-decoration-none" :href="match.rule_url" target="_blank">
                                            Más detalles sobre la regla
                                        </a>
                                    </div>
                                </article>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
