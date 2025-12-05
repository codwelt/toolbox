<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppLayout from './../../Layouts/AppLayout.vue';
import { useOgImage } from '@/composables/useOgImage';

defineOptions({ layout: AppLayout });

const props = defineProps({ seo: { type: Object, required: true } });

const seoData = computed(() => props.seo || {});
const pageTitle = computed(() => seoData.value.title || 'Analizador de texto con detección IA');
const pageDescription = computed(
    () =>
        seoData.value.description ||
        'Analiza tu texto, estima similitud con escritura de IA y recibe sugerencias para mejorar la redacción.'
);
const { ogImageUrl } = useOgImage(seoData.value);

const textInput = ref(`La escritura clara transmite ideas de forma concisa y directa. Usar párrafos equilibrados y vocabulario variado ayuda a mantener la atención y a evitar repeticiones. Este analizador ofrece métricas y sugerencias para mejorar tu estilo.`);
const analysis = ref({
    wordCount: 0,
    sentenceCount: 0,
    avgSentenceLength: 0,
    typeTokenRatio: 0,
    aiScore: 0,
    suggestions: [],
});
const copied = ref(false);
const downloadUrl = ref('');
const jsonLdScriptEl = ref(null);
const aiColor = computed(() => {
    if (analysis.value.aiScore >= 75) return '#dc3545';
    if (analysis.value.aiScore >= 50) return '#fd7e14';
    if (analysis.value.aiScore >= 25) return '#ffc107';
    return '#198754';
});

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

function splitSentences(text) {
    return (text || '')
        .split(/[.!?]+/)
        .map((s) => s.trim())
        .filter(Boolean);
}

function computeTypeTokenRatio(words) {
    const set = new Set(words);
    return words.length ? (set.size / words.length) * 100 : 0;
}

function estimateAiScore({ avgSentenceLength, typeTokenRatio, repetitionRatio }) {
    // Heurística simple: textos con alta repetición y poca variedad léxica se acercan a IA genérica
    let score = 50;
    if (avgSentenceLength < 10) score += 10; // oraciones cortas y uniformes
    if (avgSentenceLength > 22) score -= 5; // oraciones muy largas suelen ser humanas
    if (typeTokenRatio < 35) score += 15; // poca variedad
    if (typeTokenRatio > 55) score -= 10; // buena variedad
    if (repetitionRatio > 20) score += 10;
    return Math.min(100, Math.max(0, Math.round(score)));
}

function analyzeText() {
    const text = (textInput.value || '').trim();
    if (!text) {
        analysis.value = {
            wordCount: 0,
            sentenceCount: 0,
            avgSentenceLength: 0,
            typeTokenRatio: 0,
            aiScore: 0,
            suggestions: ['Pega un texto para analizar.'],
        };
        return;
    }

    const words = text.split(/\s+/).filter(Boolean);
    const sentences = splitSentences(text);
    const wordCount = words.length;
    const sentenceCount = sentences.length || 1;
    const avgSentenceLength = wordCount / sentenceCount;
    const typeTokenRatio = computeTypeTokenRatio(words);

    // Repetición básica: porcentaje de palabras más frecuentes sobre total
    const freq = words.reduce((acc, w) => {
        const key = w.toLowerCase();
        acc[key] = (acc[key] || 0) + 1;
        return acc;
    }, {});
    const sorted = Object.values(freq).sort((a, b) => b - a);
    const topFive = sorted.slice(0, 5).reduce((a, b) => a + b, 0);
    const repetitionRatio = wordCount ? (topFive / wordCount) * 100 : 0;

    const aiScore = estimateAiScore({ avgSentenceLength, typeTokenRatio, repetitionRatio });

    const suggestions = buildSuggestions({ avgSentenceLength, typeTokenRatio, repetitionRatio });

    analysis.value = {
        wordCount,
        sentenceCount,
        avgSentenceLength: Math.round(avgSentenceLength * 10) / 10,
        typeTokenRatio: Math.round(typeTokenRatio),
        aiScore,
        suggestions,
    };

    buildDownloadUrl();
}

function buildSuggestions({ avgSentenceLength, typeTokenRatio, repetitionRatio }) {
    const tips = [];
    if (avgSentenceLength < 12) tips.push('Varía la longitud de las oraciones para evitar ritmo monótono.');
    if (avgSentenceLength > 24) tips.push('Divide oraciones largas para mayor claridad.');
    if (typeTokenRatio < 40) tips.push('Añade sinónimos o vocabulario más variado.');
    if (typeTokenRatio > 70) tips.push('Equilibra variedad con claridad para no perder coherencia.');
    if (repetitionRatio > 20) tips.push('Reduce repeticiones de palabras clave o reemplázalas por equivalentes.');
    if (!tips.length) tips.push('El texto luce equilibrado; refina detalles de estilo según el contexto.');
    return tips;
}

function copyResult() {
    if (!textInput.value) return;
    navigator.clipboard
        .writeText(textInput.value)
        .then(() => {
            copied.value = true;
            setTimeout(() => (copied.value = false), 1500);
        })
        .catch((error) => console.error(error));
}

function buildDownloadUrl() {
    const blob = new Blob([textInput.value], { type: 'text/plain' });
    if (downloadUrl.value) URL.revokeObjectURL(downloadUrl.value);
    downloadUrl.value = URL.createObjectURL(blob);
}

function downloadText() {
    if (!downloadUrl.value) return;
    const a = document.createElement('a');
    a.href = downloadUrl.value;
    a.download = 'texto-analizado.txt';
    document.body.appendChild(a);
    a.click();
    a.remove();
}

onMounted(() => {
    analyzeText();
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
                        <p class="text-uppercase small mb-2 text-info fw-semibold">Redacción y IA</p>
                        <h1 class="display-5 fw-bold mb-3">{{ seoData.h1 }}</h1>
                        <p class="lead text-white-50 mb-3">
                            {{ pageDescription }}
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-info text-dark">Estimación IA</span>
                            <span class="badge bg-info text-dark">Métricas clave</span>
                            <span class="badge bg-info text-dark">Sugerencias</span>
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
                                        <p class="text-muted small mb-0">Pega tu texto para analizar estilo y similitud IA.</p>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" @click="textInput = ''; analyzeText()">Limpiar</button>
                                        <button type="button" class="btn btn-primary btn-sm" @click="analyzeText">Analizar</button>
                                    </div>
                                </div>

                                <textarea
                                    v-model="textInput"
                                    spellcheck="false"
                                    rows="12"
                                    class="form-control font-monospace"
                                    placeholder="Pega tu texto aquí..."
                                    @input="analyzeText"
                                ></textarea>

                                <div class="alert alert-info mt-3 mb-0 small" role="alert">
                                    Todo se analiza en tu navegador; no enviamos el contenido a servidores externos.
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
                                        <p class="text-muted small mb-0">Estimación y métricas.</p>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <button type="button" class="btn btn-outline-primary btn-sm" @click="copyResult" :disabled="!textInput">
                                            {{ copied ? 'Copiado' : 'Copiar' }}
                                        </button>
                                        <button type="button" class="btn btn-outline-success btn-sm" @click="downloadText" :disabled="!textInput">
                                            Descargar
                                        </button>
                                    </div>
                                </div>

                                <ul class="list-group list-group-flush small">
                                    <li class="list-group-item d-flex justify-content-between"><span>Palabras</span><span class="fw-semibold">{{ analysis.wordCount }}</span></li>
                                    <li class="list-group-item d-flex justify-content-between"><span>Oraciones</span><span class="fw-semibold">{{ analysis.sentenceCount }}</span></li>
                                    <li class="list-group-item d-flex justify-content-between"><span>Longitud media (palabras/oración)</span><span class="fw-semibold">{{ analysis.avgSentenceLength }}</span></li>
                                    <li class="list-group-item d-flex justify-content-between"><span>Variedad léxica (TTR %)</span><span class="fw-semibold">{{ analysis.typeTokenRatio }}%</span></li>
                                    <li class="list-group-item d-flex justify-content-between"><span>Prob. estilo IA (estimada)</span><span class="fw-semibold">{{ analysis.aiScore }}%</span></li>
                                </ul>

                                <div class="mt-3">
                                    <h3 class="h6 fw-semibold mb-2">Visualización</h3>
                                    <div class="progress" style="height: 18px;">
                                        <div
                                            class="progress-bar"
                                            role="progressbar"
                                            :style="{ width: analysis.aiScore + '%', backgroundColor: aiColor }"
                                            :aria-valuenow="analysis.aiScore"
                                            aria-valuemin="0"
                                            aria-valuemax="100"
                                        >
                                            {{ analysis.aiScore }}% IA
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <h3 class="h6 fw-semibold mb-2">Sugerencias</h3>
                                    <ul class="small text-muted ps-3 mb-0">
                                        <li v-for="(tip, idx) in analysis.suggestions" :key="idx">{{ tip }}</li>
                                    </ul>
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
                                    <li class="list-group-item small">1) Pega tu texto y obtén métricas de claridad y variedad.</li>
                                    <li class="list-group-item small">2) Revisa la estimación de similitud con IA y las sugerencias.</li>
                                    <li class="list-group-item small">3) Mejora vocabulario, longitud de oraciones y evita repeticiones.</li>
                                    <li class="list-group-item small">4) Copia o descarga el texto tras editarlo.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <h2 class="h5 fw-semibold mb-3">Preguntas frecuentes</h2>
                                <div class="accordion" id="accordionAnalyzer">
                                    <div class="accordion-item" v-for="(item, index) in seoData.faq" :key="index">
                                        <h2 class="accordion-header" :id="`heading-analyzer-${index}`">
                                            <button
                                                class="accordion-button collapsed"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                :data-bs-target="`#collapse-analyzer-${index}`"
                                                aria-expanded="false"
                                                :aria-controls="`collapse-analyzer-${index}`"
                                            >
                                                <span class="small fw-semibold">
                                                    {{ item.question }}
                                                </span>
                                            </button>
                                        </h2>
                                        <div
                                            :id="`collapse-analyzer-${index}`"
                                            class="accordion-collapse collapse"
                                            :aria-labelledby="`heading-analyzer-${index}`"
                                            data-bs-parent="#accordionAnalyzer"
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
