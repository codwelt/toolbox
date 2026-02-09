<script setup>
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppLayout from './../../Layouts/AppLayout.vue';
import { useOgImage } from '@/composables/useOgImage';

defineOptions({ layout: AppLayout });

const props = defineProps({ seo: { type: Object, required: true } });

const seoData = computed(() => props.seo || {});
const pageTitle = computed(() => seoData.value.title || 'Detector de páginas web hechas con IA');
const pageDescription = computed(
    () =>
        seoData.value.description ||
        'Analiza una URL y detecta señales de que la página fue creada o redactada con inteligencia artificial.'
);
const { ogImageUrl } = useOgImage(seoData.value);

const urlInput = ref('');
const loading = ref(false);
const error = ref('');
const analysis = ref(null);
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

const scoreVariant = computed(() => {
    if (!analysis.value) return 'bg-secondary';
    if (analysis.value.aiScore >= 70) return 'bg-danger';
    if (analysis.value.aiScore >= 45) return 'bg-warning text-dark';
    return 'bg-success';
});

const verdictText = computed(() => {
    if (!analysis.value) return '';
    if (analysis.value.aiScore >= 70) return 'Se detectan múltiples señales fuertes asociadas a constructores o contenido IA.';
    if (analysis.value.aiScore >= 45) return 'Se detectan algunas señales de IA combinadas con patrones mixtos.';
    return 'Baja cantidad de señales de IA. Revisa manualmente para confirmar.';
});

async function analyzeUrl() {
    error.value = '';
    analysis.value = null;
    const url = (urlInput.value || '').trim();
    if (!url) {
        error.value = 'Ingresa una URL válida (con dominio y sin caracteres extraños).';
        return;
    }

    loading.value = true;
    try {
        const { data } = await axios.post('/api/tools/ai-website-detector', { url });
        analysis.value = {
            url: data.url,
            domain: data.domain,
            aiScore: data.ai_score ?? 0,
            builderMatches: data.builder_matches || [],
            metaSignals: data.meta_signals || [],
            metrics: data.metrics || {},
            textPreview: data.text_preview || '',
            usedFallback: data.used_fallback || false,
        };
    } catch (err) {
        console.error(err);
        if (err.response?.data?.message) {
            error.value = err.response.data.message;
        } else {
            error.value = 'No pudimos leer la página. Puede estar bloqueando solicitudes externas o no responder.';
        }
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    const el = document.createElement('script');
    el.type = 'application/ld+json';
    el.text = jsonLd.value;
    document.head.appendChild(el);
    jsonLdScriptEl.value = el;
});

onBeforeUnmount(() => {
    if (jsonLdScriptEl.value && jsonLdScriptEl.value.parentNode) jsonLdScriptEl.value.parentNode.removeChild(jsonLdScriptEl.value);
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
                    <div class="col-lg-9">
                        <p class="text-uppercase small mb-2 text-info fw-semibold">Auditoría rápida</p>
                        <h1 class="display-5 fw-bold mb-3">{{ seoData.h1 || pageTitle }}</h1>
                        <p class="lead text-white-50 mb-4">
                            {{ pageDescription }}
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-info text-dark">Metaetiquetas</span>
                            <span class="badge bg-info text-dark">Constructores IA</span>
                            <span class="badge bg-info text-dark">Patrones de texto</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="container py-5">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <p class="small text-muted mb-1">URL a analizar</p>
                                    <h2 class="h5 fw-bold mb-0">Detecta señales IA en segundos</h2>
                                </div>
                                <span class="badge bg-primary-subtle text-primary border">Beta</span>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">Pega la URL pública del sitio</label>
                                <div class="input-group">
                                    <input
                                        v-model="urlInput"
                                        type="url"
                                        class="form-control form-control-lg"
                                        placeholder="https://tusitio.com"
                                        :disabled="loading"
                                    />
                                    <button class="btn btn-primary btn-lg" type="button" :disabled="loading" data-share-auto-run="true" @click="analyzeUrl">
                                        <span v-if="loading" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                        {{ loading ? 'Analizando...' : 'Analizar URL' }}
                                    </button>
                                </div>
                                <p class="small text-muted mt-2 mb-0">
                                    Revisamos el HTML de la página en tu navegador. Si la web bloquea peticiones externas, probaremos con un lector público (fallback).
                                </p>
                            </div>

                            <div v-if="error" class="alert alert-danger small mb-0" role="alert">
                                {{ error }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h2 class="h5 fw-bold mb-0">¿Qué detectamos?</h2>
                                <span class="badge bg-light text-muted border">Heurístico</span>
                            </div>
                            <ul class="list-unstyled small text-muted mb-0">
                                <li class="mb-2">• Metaetiquetas y scripts de constructores con IA.</li>
                                <li class="mb-2">• Patrones de texto (repetición, variedad léxica, longitud).</li>
                                <li class="mb-2">• Marcas explícitas como data-ai o ai-generated.</li>
                                <li>• Señales no determinan autoría, solo orientan una revisión manual.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="analysis" class="row g-4 mt-1">
                <div class="col-lg-7">
                    <div class="card shadow-sm border-0 mb-3">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <p class="small text-muted mb-1">Resultado estimado</p>
                                    <h2 class="h5 fw-bold mb-0">{{ analysis.domain || analysis.url }}</h2>
                                </div>
                                <span class="badge bg-light text-muted border">{{ analysis.usedFallback ? 'Con fallback' : 'Lectura directa' }}</span>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-semibold">Probabilidad IA estimada</span>
                                    <span class="fw-bold">{{ analysis.aiScore }}%</span>
                                </div>
                                <div class="progress" style="height: 12px">
                                    <div class="progress-bar" :class="scoreVariant" role="progressbar" :style="{ width: `${analysis.aiScore}%` }" />
                                </div>
                                <p class="small text-muted mt-2 mb-0">{{ verdictText }}</p>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100">
                                        <p class="small text-muted mb-2">Señales técnicas</p>
                                        <ul class="list-unstyled small mb-0">
                                            <li v-if="analysis.builderMatches.length" class="mb-2">
                                                <span class="fw-semibold">Constructores IA:</span>
                                                <ul class="mb-0 mt-1 ps-3">
                                                    <li v-for="builder in analysis.builderMatches" :key="builder.name">
                                                        {{ builder.name }} ({{ builder.markers.join(', ') }})
                                                    </li>
                                                </ul>
                                            </li>
                                            <li v-else class="text-muted">No se detectaron constructores IA conocidos.</li>
                                            <li v-for="(meta, idx) in analysis.metaSignals" :key="idx" class="mt-2">
                                                <span class="fw-semibold">{{ meta.label }}:</span> {{ meta.value }}
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100">
                                        <p class="small text-muted mb-2">Patrones de texto</p>
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span>Palabras</span>
                                            <span class="fw-semibold">{{ analysis.metrics.wordCount }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span>Oraciones</span>
                                            <span class="fw-semibold">{{ analysis.metrics.sentenceCount }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span>Promedio palabras/oración</span>
                                            <span class="fw-semibold">{{ analysis.metrics.avgSentenceLength }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span>Variedad léxica (TTR)</span>
                                            <span class="fw-semibold">{{ analysis.metrics.typeTokenRatio }}%</span>
                                        </div>
                                        <div class="d-flex justify-content-between small">
                                            <span>Repetición (top palabras)</span>
                                            <span class="fw-semibold">{{ analysis.metrics.repetitionRatio }}%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h3 class="h6 fw-bold mb-0">Texto extraído (vista previa)</h3>
                                <span class="badge bg-light text-muted border">No se envía a servidores</span>
                            </div>
                            <div class="small text-muted mb-3">
                                Usa este texto para revisar si hay frases genéricas o repetitivas típicas de IA.
                            </div>
                            <div class="bg-light border rounded p-3 small" style="min-height: 160px; white-space: pre-wrap">
                                {{ analysis.textPreview || 'No se pudo extraer texto visible de la página.' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card shadow-sm border-0 mb-3">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-3">Cómo interpretar el resultado</h3>
                            <ul class="small text-muted mb-0">
                                <li class="mb-2">Un puntaje alto indica que varias señales coinciden con constructores o contenido IA.</li>
                                <li class="mb-2">Si solo hay señales de texto, revisa manualmente si el copy parece genérico o repetitivo.</li>
                                <li class="mb-2">La ausencia de señales no garantiza autoría humana; sirve como orientación inicial.</li>
                                <li>Si el sitio bloquea peticiones, usa una versión cacheada o captura HTML para un análisis más fiable.</li>
                            </ul>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-3">Preguntas frecuentes</h3>
                            <div class="accordion" id="accordionFaq">
                                <div v-for="(faq, idx) in seoData.faq" :key="idx" class="accordion-item">
                                    <h2 :id="`heading-${idx}`" class="accordion-header">
                                        <button
                                            class="accordion-button collapsed"
                                            type="button"
                                            data-bs-toggle="collapse"
                                            :data-bs-target="`#collapse-${idx}`"
                                            aria-expanded="false"
                                            :aria-controls="`collapse-${idx}`"
                                        >
                                            <span class="small fw-semibold">{{ faq.question }}</span>
                                        </button>
                                    </h2>
                                    <div
                                        :id="`collapse-${idx}`"
                                        class="accordion-collapse collapse"
                                        :aria-labelledby="`heading-${idx}`"
                                        data-bs-parent="#accordionFaq"
                                    >
                                        <div class="accordion-body small text-muted">
                                            {{ faq.answer }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
