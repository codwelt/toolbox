<script setup>
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppLayout from './../../Layouts/AppLayout.vue';
import { useOgImage } from '@/composables/useOgImage';

defineOptions({ layout: AppLayout });

const props = defineProps({ seo: { type: Object, required: true } });

const seoData = computed(() => props.seo || {});
const pageTitle = computed(() => seoData.value.title || 'Mi IP pública');
const pageDescription = computed(
    () =>
        seoData.value.description ||
        'Consulta tu IP pública, verifica si es IPv4 o IPv6, si es privada/reservada y revisa cabeceras básicas.'
);
const { ogImageUrl } = useOgImage(seoData.value);

const info = ref(null);
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

const ipVersionBadge = computed(() => {
    if (!info.value) return 'bg-secondary';
    return info.value.is_ipv6 ? 'bg-info text-dark' : 'bg-primary';
});

const ipPrivateBadge = computed(() => {
    if (!info.value) return 'bg-secondary';
    if (info.value.is_private) return 'bg-warning text-dark';
    if (info.value.is_reserved) return 'bg-secondary';
    return 'bg-success';
});

async function loadIpInfo() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get('/api/tools/ip-info');
        info.value = data;
    } catch (err) {
        console.error(err);
        error.value = 'No pudimos obtener tu IP. Intenta de nuevo.';
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadIpInfo();
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
                        <p class="text-uppercase small mb-2 text-info fw-semibold">Red</p>
                        <h1 class="display-5 fw-bold mb-3">{{ seoData.h1 || pageTitle }}</h1>
                        <p class="lead text-white-50 mb-4">
                            {{ pageDescription }}
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-info text-dark">IPv4 / IPv6</span>
                            <span class="badge bg-info text-dark">Privada o pública</span>
                            <span class="badge bg-info text-dark">Cabeceras básicas</span>
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
                                    <p class="small text-muted mb-1">Tu conexión actual</p>
                                    <h2 class="h5 fw-bold mb-0">IP y detalles</h2>
                                </div>
                                <button class="btn btn-outline-primary btn-sm" type="button" :disabled="loading" @click="loadIpInfo">
                                    <span v-if="loading" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                    Refrescar
                                </button>
                            </div>

                            <div v-if="error" class="alert alert-danger small" role="alert">
                                {{ error }}
                            </div>

                            <template v-else-if="info">
                                <div class="mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="display-6 fw-bold">{{ info.ip }}</span>
                                        <span class="badge" :class="ipVersionBadge">{{ info.is_ipv6 ? 'IPv6' : 'IPv4' }}</span>
                                        <span class="badge" :class="ipPrivateBadge">
                                            {{ info.is_private ? 'Privada/Proxy' : info.is_reserved ? 'Reservada' : 'Pública' }}
                                        </span>
                                    </div>
                                    <div class="small text-muted">Host: {{ info.host || '—' }}</div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="border rounded p-3 h-100">
                                            <p class="small text-muted mb-2">Datos de la petición</p>
                                            <ul class="list-unstyled small mb-0">
                                                <li class="mb-1">
                                                    <span class="fw-semibold">X-Forwarded-For:</span>
                                                    <span>{{ info.headers?.x_forwarded_for || '—' }}</span>
                                                </li>
                                                <li class="mb-1">
                                                    <span class="fw-semibold">X-Real-IP:</span>
                                                    <span>{{ info.headers?.x_real_ip || '—' }}</span>
                                                </li>
                                                <li class="mb-1">
                                                    <span class="fw-semibold">User-Agent:</span>
                                                    <span>{{ info.user_agent || '—' }}</span>
                                                </li>
                                                <li>
                                                    <span class="fw-semibold">Idioma:</span>
                                                    <span>{{ info.headers?.accept_language || '—' }}</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="border rounded p-3 h-100">
                                            <p class="small text-muted mb-2">Notas rápidas</p>
                                            <ul class="small text-muted mb-0">
                                                <li class="mb-2">Si usas VPN o proxy, verás la IP pública de ese servicio.</li>
                                                <li class="mb-2">Direcciones privadas (192.168.x.x, 10.x.x.x, etc.) indican red interna.</li>
                                                <li>Las IP reservadas no se enrutan en internet público.</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div v-else class="text-muted small">
                                Presiona “Refrescar” para obtener tu IP.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card shadow-sm border-0 mb-3">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-3">Preguntas frecuentes</h3>
                            <div class="accordion" id="accordionFaqIp">
                                <div v-for="(faq, idx) in seoData.faq" :key="idx" class="accordion-item">
                                    <h2 :id="`heading-ip-${idx}`" class="accordion-header">
                                        <button
                                            class="accordion-button collapsed"
                                            type="button"
                                            data-bs-toggle="collapse"
                                            :data-bs-target="`#collapse-ip-${idx}`"
                                            aria-expanded="false"
                                            :aria-controls="`collapse-ip-${idx}`"
                                        >
                                            <span class="small fw-semibold">{{ faq.question }}</span>
                                        </button>
                                    </h2>
                                    <div
                                        :id="`collapse-ip-${idx}`"
                                        class="accordion-collapse collapse"
                                        :aria-labelledby="`heading-ip-${idx}`"
                                        data-bs-parent="#accordionFaqIp"
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
