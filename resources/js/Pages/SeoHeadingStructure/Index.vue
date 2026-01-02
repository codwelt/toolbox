<script setup>
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppLayout from './../../Layouts/AppLayout.vue';
import { useOgImage } from '@/composables/useOgImage';

defineOptions({ layout: AppLayout });

const props = defineProps({ seo: { type: Object, required: true } });

const seoData = computed(() => props.seo || {});
const pageTitle = computed(() => seoData.value.title || 'Analizador de estructura de títulos SEO');
const pageDescription = computed(
    () =>
        seoData.value.description ||
        'Analiza los encabezados H1-H6 de una URL, valida la jerarquía y recibe consejos claros para mejorar tu estructura de títulos.'
);
const { ogImageUrl } = useOgImage(seoData.value);

const urlInput = ref('');
const loading = ref(false);
const error = ref('');
const result = ref(null);
const jsonLdScriptEl = ref(null);
const copyStatus = ref('');

const scoreVariant = computed(() => {
    if (!result.value) return 'bg-secondary';
    if (result.value.score >= 85) return 'bg-success';
    if (result.value.score >= 65) return 'bg-warning text-dark';
    return 'bg-danger';
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
        const { data } = await axios.post('/api/tools/seo-heading-structure', { url });
        result.value = {
            ...data,
            usedFallback: data.used_fallback || false,
            counts: data.counts || {},
            checks: data.checks || [],
            recommendations: data.recommendations || [],
            outline: data.outline || [],
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

function buildHeadingsText(outline) {
    if (!outline?.length) return '';
    return outline
        .map((item) => {
            const indent = '  '.repeat(Math.max(item.level - 1, 0));
            const text = item.text || 'Sin texto';
            return `${indent}H${item.level} ${text}`;
        })
        .join('\n');
}

async function copyHeadings() {
    copyStatus.value = '';
    if (!result.value?.outline?.length) return;
    const text = buildHeadingsText(result.value.outline);

    try {
        await navigator.clipboard.writeText(text);
        copyStatus.value = 'Copiado';
    } catch (err) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'absolute';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        copyStatus.value = 'Copiado';
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
            <meta v-if="seoData.keywords && seoData.keywords.length" name="keywords"
                :content="seoData.keywords.join(', ')" />
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
                        <p class="text-uppercase small mb-2 text-warning fw-semibold">Estructura de títulos SEO</p>
                        <h1 class="display-5 fw-bold mb-3">{{ seoData.h1 || pageTitle }}</h1>
                        <p class="lead text-white-50 mb-4">
                            {{ pageDescription }}
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-warning text-dark">H1-H6</span>
                            <span class="badge bg-warning text-dark">Jerarquía y saltos</span>
                            <span class="badge bg-warning text-dark">Consejos claros</span>
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
                                    <p class="small text-muted mb-1">URL a revisar</p>
                                    <h2 class="h5 fw-bold mb-0">Evalúa la jerarquía de títulos</h2>
                                </div>
                                <span class="badge bg-primary-subtle text-primary border">Nuevo</span>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">Pega la URL pública del sitio</label>
                                <div class="input-group">
                                    <input v-model="urlInput" type="url" class="form-control form-control-lg"
                                        placeholder="https://tusitio.com" :disabled="loading" />
                                    <button class="btn btn-primary btn-lg" type="button" :disabled="loading"
                                        @click="analyzeUrl">
                                        <span v-if="loading" class="spinner-border spinner-border-sm me-2" role="status"
                                            aria-hidden="true"></span>
                                        {{ loading ? 'Analizando...' : 'Analizar títulos' }}
                                    </button>
                                </div>
                                <p class="small text-muted mt-2 mb-0">
                                    Leemos el HTML en tu navegador; si el sitio bloquea peticiones, usamos un lector
                                    público como fallback.
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
                                <h2 class="h5 fw-bold mb-0">Buenas prácticas</h2>
                                <span class="badge bg-light text-muted border">SEO</span>
                            </div>
                            <ul class="list-unstyled small text-muted mb-0">
                                <li class="mb-2">• Un único H1 que resume el tema principal.</li>
                                <li class="mb-2">• H2 para secciones y H3 para subsecciones.</li>
                                <li class="mb-2">• Evitar saltos de H2 a H4.</li>
                                <li>• Encabezados descriptivos, no vacíos.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="result" class="row g-4 mt-1">
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h3 class="h6 fw-bold mb-0">Contenido</h3>
                                <button class="btn btn-outline-secondary btn-sm" type="button"
                                    :disabled="!result.outline?.length" @click="copyHeadings">
                                    <span class="me-1">📋</span>
                                    {{ copyStatus === 'Copiado' ? 'Copiado' : 'Copiar headings' }}
                                </button>
                            </div>
                            <div v-if="result.outline && result.outline.length" class="heading-list">
                                <div class="heading-list-row">
                                    <div class="heading-list-label">Headings</div>
                                    <div class="heading-list-items">
                                        <div v-for="item in result.outline" :key="item.position" class="heading-item"
                                            :style="{ paddingLeft: `${(item.level - 1) * 20}px` }">
                                            <span class="heading-level">H{{ item.level }}</span>
                                            <span class="heading-text">{{ item.text || 'Sin texto' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="small text-muted mb-0">No se detectaron encabezados en la página.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="d-flex flex-column gap-4">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h3 class="h6 fw-bold mb-0">Resultado general</h3>
                                    <span class="badge" :class="scoreVariant">{{ result.score }}%</span>
                                </div>
                                <p class="small text-muted mb-3">
                                    {{ result.url }}
                                </p>
                                <div class="row g-2 text-center small">
                                    <div class="col-4" v-for="level in [1, 2, 3, 4, 5, 6]" :key="level">
                                        <div class="border rounded py-2 bg-white">
                                            <div class="fw-semibold">H{{ level }}</div>
                                            <div class="text-muted">{{ result.counts?.['h' + level] ?? 0 }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 small text-muted">
                                    <span v-if="result.usedFallback">Lectura con fallback activada.</span>
                                    <span v-else>Lectura directa del HTML.</span>
                                </div>
                            </div>
                        </div>
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-4">
                                <h3 class="h6 fw-bold mb-3">Checklist de estructura</h3>
                                <div v-for="check in result.checks" :key="check.label"
                                    class="d-flex justify-content-between align-items-start border-bottom py-2">
                                    <div>
                                        <div class="fw-semibold">{{ check.label }}</div>
                                        <div class="small text-muted">{{ check.detail }}</div>
                                    </div>
                                    <span class="badge" :class="check.ok ? 'bg-success' : 'bg-warning text-dark'">
                                        {{ check.ok ? 'OK' : 'Mejorar' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body p-4">
                                <h3 class="h6 fw-bold mb-3">Consejos recomendados</h3>
                                <ul v-if="result.recommendations && result.recommendations.length"
                                    class="small text-muted mb-0">
                                    <li v-for="item in result.recommendations" :key="item" class="mb-2">• {{ item }}
                                    </li>
                                </ul>
                                <p v-else class="small text-muted mb-0">Sin recomendaciones adicionales.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="seoData.faq && seoData.faq.length" class="row mt-5">
                <div class="col-lg-10 mx-auto">
                    <h2 class="h4 fw-bold mb-4 text-center">Preguntas frecuentes</h2>
                    <div class="accordion" id="accordionFaqHeadingStructure">
                        <div v-for="(item, index) in seoData.faq" :key="item.question" class="accordion-item">
                            <h3 class="accordion-header" :id="`headingFaq-${index}`">
                                <button class="accordion-button" :class="{ collapsed: index !== 0 }" type="button"
                                    data-bs-toggle="collapse" :data-bs-target="`#collapseFaq-${index}`"
                                    :aria-expanded="index === 0 ? 'true' : 'false'"
                                    :aria-controls="`collapseFaq-${index}`">
                                    {{ item.question }}
                                </button>
                            </h3>
                            <div :id="`collapseFaq-${index}`" class="accordion-collapse collapse"
                                :class="{ show: index === 0 }" :aria-labelledby="`headingFaq-${index}`"
                                data-bs-parent="#accordionFaqHeadingStructure">
                                <div class="accordion-body">{{ item.answer }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.heading-list {
    border-top: 1px solid #eee;
    padding-top: 12px;
}

.heading-list-row {
    display: grid;
    grid-template-columns: 110px 1fr;
    gap: 16px;
    align-items: start;
}

.heading-list-label {
    font-weight: 600;
    color: #4b5563;
    font-size: 0.95rem;
}

.heading-list-items {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.heading-item {
    display: flex;
    gap: 10px;
    align-items: center;
    color: #1f2937;
}

.heading-level {
    color: #6b7280;
    font-weight: 600;
    min-width: 28px;
}

.heading-text {
    font-weight: 500;
}

@media (max-width: 768px) {
    .heading-list-row {
        grid-template-columns: 1fr;
        gap: 8px;
    }
}
</style>
