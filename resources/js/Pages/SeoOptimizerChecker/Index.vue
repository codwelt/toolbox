<script setup>
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppLayout from './../../Layouts/AppLayout.vue';
import { useOgImage } from '@/composables/useOgImage';

defineOptions({ layout: AppLayout });

const props = defineProps({ seo: { type: Object, required: true } });

const seoData = computed(() => props.seo || {});
const pageTitle = computed(() => seoData.value.title || 'Analizador SEO rápido por URL');
const pageDescription = computed(
    () =>
        seoData.value.description ||
        'Pega una URL y obtén un porcentaje de optimización SEO: títulos, meta descripciones, canónico, H1/H2 y texto alternativo.'
);
const { ogImageUrl } = useOgImage(seoData.value);

const urlInput = ref('');
const loading = ref(false);
const error = ref('');
const result = ref(null);
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
    if (!result.value) return 'bg-secondary';
    if (result.value.score >= 85) return 'bg-success';
    if (result.value.score >= 65) return 'bg-warning text-dark';
    return 'bg-danger';
});

async function analyzeUrl() {
    error.value = '';
    result.value = null;
    const url = (urlInput.value || '').trim();
    if (!url) {
        error.value = 'Ingresa una URL válida (ej. https://tusitio.com).';
        return;
    }

    loading.value = true;
    try {
        const { data } = await axios.post('/api/tools/seo-analyzer', { url });
        result.value = {
            ...data,
            usedFallback: data.used_fallback || false,
            imagesCount: data.images_count || 0,
            wordCount: data.word_count || 0,
            structuredDataTypes: data.structured_data_types || [],
            sitemap: data.sitemap || null,
            robotsTxt: data.robots_txt || null,
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
                        <p class="text-uppercase small mb-2 text-info fw-semibold">Checklist SEO</p>
                        <h1 class="display-5 fw-bold mb-3">{{ seoData.h1 || pageTitle }}</h1>
                        <p class="lead text-white-50 mb-4">
                            {{ pageDescription }}
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-info text-dark">Títulos y descripciones</span>
                            <span class="badge bg-info text-dark">Canónico y robots</span>
                            <span class="badge bg-info text-dark">Headings e imágenes</span>
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
                                    <p class="small text-muted mb-1">URL a auditar</p>
                                    <h2 class="h5 fw-bold mb-0">Analiza SEO on-page en segundos</h2>
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
                                    <button
                                        class="btn btn-primary btn-lg"
                                        type="button"
                                        :disabled="loading"
                                        data-share-auto-run="true"
                                        @click="analyzeUrl"
                                    >
                                        <span v-if="loading" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                        {{ loading ? 'Analizando...' : 'Analizar SEO' }}
                                    </button>
                                </div>
                                <p class="small text-muted mt-2 mb-0">
                                    Leemos el HTML en tu navegador; si el sitio bloquea peticiones, probaremos con un lector público (fallback).
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
                                <h2 class="h5 fw-bold mb-0">¿Qué revisamos?</h2>
                                <span class="badge bg-light text-muted border">Heurístico</span>
                            </div>
                            <ul class="list-unstyled small text-muted mb-0">
                                <li class="mb-2">• Longitud y presencia de título y meta descripción.</li>
                                <li class="mb-2">• Etiqueta canonical y robots.</li>
                                <li class="mb-2">• H1 único y H2 de apoyo.</li>
                                <li class="mb-2">• Texto alternativo en imágenes.</li>
                                <li>• Enlaces internos y meta OG básicos.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="result" class="row g-4 mt-1">
                <div class="col-lg-7">
                    <div class="card shadow-sm border-0 mb-3">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <p class="small text-muted mb-1">Resultado SEO estimado</p>
                                    <h2 class="h5 fw-bold mb-0">{{ result.domain || result.url }}</h2>
                                </div>
                                <span class="badge bg-light text-muted border">
                                    {{ result.usedFallback ? 'Con fallback (metadata limitada)' : 'Lectura directa' }}
                                </span>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-semibold">Puntaje SEO</span>
                                    <span class="fw-bold">{{ result.score }}%</span>
                                </div>
                                <div class="progress" style="height: 12px">
                                    <div class="progress-bar" :class="scoreVariant" role="progressbar" :style="{ width: `${result.score}%` }" />
                                </div>
                                <p class="small text-muted mt-2 mb-0">
                                    Puntaje basado en checks on-page. Úsalo como checklist rápido, no reemplaza una auditoría completa.
                                </p>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100">
                                        <p class="small text-muted mb-2">Metadatos</p>
                                        <ul class="small list-unstyled mb-0">
                                            <li class="mb-1"><span class="fw-semibold">Título:</span> {{ result.title || '—' }}</li>
                                            <li class="mb-1"><span class="fw-semibold">Descripción:</span> {{ result.description || '—' }}</li>
                                            <li class="mb-1"><span class="fw-semibold">Canonical:</span> {{ result.canonical || '—' }}</li>
                                            <li class="mb-1"><span class="fw-semibold">Robots:</span> {{ result.robots || '—' }}</li>
                                            <li class="mb-1"><span class="fw-semibold">Lang:</span> {{ result.lang || '—' }}</li>
                                            <li class="mb-1"><span class="fw-semibold">Hreflang:</span> {{ result.hreflangs?.join(', ') || '—' }}</li>
                                            <li class="mb-1"><span class="fw-semibold">Viewport:</span> {{ result.viewport || '—' }}</li>
                                            <li class="mb-1"><span class="fw-semibold">Charset:</span> {{ result.charset || '—' }}</li>
                                            <li class="mb-1">
                                                <span class="fw-semibold">Robots.txt:</span>
                                                <span>{{ result.robotsTxt?.status ? result.robotsTxt.status : '—' }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100">
                                        <p class="small text-muted mb-2">Estructura</p>
                                        <ul class="small list-unstyled mb-0">
                                            <li class="mb-1"><span class="fw-semibold">H1:</span> {{ result.h1s.join(' | ') || '—' }}</li>
                                            <li class="mb-1"><span class="fw-semibold">H2:</span> {{ result.h2s.join(' | ') || '—' }}</li>
                                            <li class="mb-1"><span class="fw-semibold">Imágenes:</span> {{ result.imagesCount }}</li>
                                            <li class="mb-1"><span class="fw-semibold">Palabras visibles:</span> {{ result.wordCount }}</li>
                                            <li class="mb-1"><span class="fw-semibold">Structured data:</span> {{ result.structuredDataTypes?.join(', ') || '—' }}</li>
                                            <li class="mb-1">
                                                <span class="fw-semibold">Sitemap:</span>
                                                <span>{{ result.sitemap?.url || '—' }}</span>
                                            </li>
                                            <li v-if="result.sitemap?.urls_count !== undefined" class="mb-1">
                                                <span class="fw-semibold">URLs en sitemap:</span> {{ result.sitemap.urls_count }}
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h3 class="h6 fw-bold mb-0">Checklist detallado</h3>
                                <span class="badge bg-light text-muted border">On-page</span>
                            </div>
                            <div class="list-group list-group-flush">
                                <div
                                    v-for="(check, idx) in result.checks"
                                    :key="idx"
                                    class="list-group-item d-flex justify-content-between align-items-start"
                                >
                                    <div>
                                        <div class="fw-semibold small">{{ check.label }}</div>
                                        <div class="small text-muted">{{ check.detail }}</div>
                                    </div>
                                    <span class="badge" :class="check.ok ? 'bg-success' : 'bg-danger'">{{ check.ok ? 'OK' : 'Falta' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card shadow-sm border-0 mb-3">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-3">Recomendaciones rápidas</h3>
                            <ul class="small text-muted mb-0">
                                <li class="mb-2">Ajusta título (50-60 chars) y descripción (110-160) si faltan.</li>
                                <li class="mb-2">Agrega canonical y verifica que robots no bloquee indexación.</li>
                                <li class="mb-2">Usa un H1 único y varios H2 descriptivos.</li>
                                <li class="mb-2">Añade texto alternativo a imágenes clave.</li>
                                <li>Enlaza a páginas internas relevantes para reforzar estructura.</li>
                            </ul>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-3">Preguntas frecuentes</h3>
                            <div class="accordion" id="accordionFaqSeo">
                                <div v-for="(faq, idx) in seoData.faq" :key="idx" class="accordion-item">
                                    <h2 :id="`heading-seo-${idx}`" class="accordion-header">
                                        <button
                                            class="accordion-button collapsed"
                                            type="button"
                                            data-bs-toggle="collapse"
                                            :data-bs-target="`#collapse-seo-${idx}`"
                                            aria-expanded="false"
                                            :aria-controls="`collapse-seo-${idx}`"
                                        >
                                            <span class="small fw-semibold">{{ faq.question }}</span>
                                        </button>
                                    </h2>
                                    <div
                                        :id="`collapse-seo-${idx}`"
                                        class="accordion-collapse collapse"
                                        :aria-labelledby="`heading-seo-${idx}`"
                                        data-bs-parent="#accordionFaqSeo"
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
