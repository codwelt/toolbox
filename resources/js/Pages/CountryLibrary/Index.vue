<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { countries } from '@/countries/countries.generated';
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
const pageTitle = computed(() => seoData.value.title || 'Biblioteca de países del mundo');
const pageDescription = computed(
    () =>
        seoData.value.description ||
        'Consulta países con código ISO, indicativo telefónico, bandera y moneda en un solo lugar.'
);
const { ogImageUrl } = useOgImage(seoData.value);

const search = ref('');
const copyFeedback = ref('');

// normalizar texto para búsqueda (sin acentos, minúsculas)
function normalize(str) {
    if (!str) return '';
    return str
        .toString()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

// agrupamos por continente y aplicamos búsqueda
const groupedCountries = computed(() => {
    const term = normalize(search.value);
    const groups = {};

    countries.forEach((c) => {
        const nameEs = normalize(c.nameEs);
        const nameEn = normalize(c.nameEn);
        const iso2 = normalize(c.iso2);
        const iso3 = normalize(c.iso3);
        const dial = normalize(c.dialCode);
        const currency = normalize(c.currencyCode);
        const currencyName = normalize(c.currencyName);
        const continent = c.continent || 'Other';

        const haystack = `${nameEs} ${nameEn} ${iso2} ${iso3} ${dial} ${currency} ${currencyName}`;

        if (!term || haystack.includes(term)) {
            if (!groups[continent]) {
                groups[continent] = [];
            }
            groups[continent].push(c);
        }
    });

    // ordenar países alfabéticamente dentro de cada continente
    Object.keys(groups).forEach((key) => {
        groups[key].sort((a, b) =>
            (a.nameEs || a.nameEn).localeCompare(b.nameEs || b.nameEn, 'es')
        );
    });

    return groups;
});

async function copyDialCode(country) {
    copyFeedback.value = '';

    try {
        if (navigator.clipboard && country.dialCode) {
            await navigator.clipboard.writeText(country.dialCode);
            copyFeedback.value = `Indicativo ${country.dialCode} copiado al portapapeles.`;
        }
    } catch (e) {
        console.error(e);
        copyFeedback.value =
            'No se pudo copiar automáticamente. Intenta copiar el indicativo manualmente.';
    }

    if (copyFeedback.value) {
        setTimeout(() => {
            copyFeedback.value = '';
        }, 2500);
    }
}

// JSON-LD SEO
const jsonLdScriptEl = ref(null);

onMounted(() => {
    const faqStructured = (seoData.value.faq || []).map((item) => ({
        '@type': 'Question',
        name: item.question,
        acceptedAnswer: {
            '@type': 'Answer',
            text: item.answer,
        },
    }));

    const jsonLdObj = {
        '@context': 'https://schema.org',
        '@type': 'WebApplication',
        name: pageTitle.value,
        url: seoData.value.canonical || seoData.value.path || '',
        applicationCategory: 'Utility',
        description: pageDescription.value,
        mainEntity: {
            '@type': 'FAQPage',
            mainEntity: faqStructured,
        },
    };

    const el = document.createElement('script');
    el.type = 'application/ld+json';
    el.text = JSON.stringify(jsonLdObj);
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
    <div class="bg-light min-vh-100">
        <!-- HEAD SEO -->

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
                        <p class="text-uppercase small mb-2 text-info fw-semibold">Datos de países</p>
                        <h1 class="display-5 fw-bold mb-3">{{ seoData.h1 }}</h1>
                        <p class="lead text-white-50 mb-3">
                            {{ pageDescription }}
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-info text-dark">Indicativos</span>
                            <span class="badge bg-info text-dark">ISO y monedas</span>
                            <span class="badge bg-info text-dark">Optimizado SEO</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="container py-5">

            <!-- CONTENIDO PRINCIPAL -->
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-body p-4 p-md-5">
                            <!-- Buscador -->
                            <div class="row mb-3">
                                <div class="col-12 col-md-8">
                                    <label class="form-label mb-1 small text-muted">
                                        Buscar país, indicativo, código corto o moneda
                                    </label>
                                    <input v-model="search" type="text" class="form-control form-control-sm"
                                        placeholder="Ej: Colombia, +57, co, euro, dólar..." />
                                </div>
                            </div>

                            <!-- Listado por continentes -->
                            <div class="country-library-list">
                                <section v-for="(list, continentName) in groupedCountries" :key="continentName"
                                    class="mb-4">
                                    <h2 class="h5 fw-bold mb-3">
                                        {{ continentName }}
                                    </h2>

                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 60px;">Bandera</th>
                                                    <th>País</th>
                                                    <th style="width: 120px;">Código</th>
                                                    <th style="width: 120px;">Indicativo</th>
                                                    <th>Moneda</th>
                                                    <th style="width: 70px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="country in list" :key="country.iso2">
                                                    <td class="fs-4">
                                                        {{ country.flag }}
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold">
                                                            {{ country.nameEs || country.nameEn }}
                                                        </div>
                                                        <div class="small text-muted">
                                                            {{ country.nameEn }}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary-subtle text-dark border">
                                                            {{ country.iso2?.toLowerCase() }}
                                                        </span>
                                                        <span v-if="country.iso3"
                                                            class="badge bg-light text-muted border ms-1">
                                                            {{ country.iso3.toLowerCase() }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary-subtle text-primary border">
                                                            {{ country.dialCode }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="small fw-semibold">
                                                            {{ country.currencyName }}
                                                        </div>
                                                        <div class="small text-muted">
                                                            {{ country.currencyCode }}
                                                        </div>
                                                    </td>
                                                    <td class="text-end">
                                                        <button type="button" class="btn btn-outline-primary btn-sm"
                                                            @click="copyDialCode(country)" title="Copiar indicativo">
                                                            Copiar
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>

                                <p v-if="!Object.keys(groupedCountries).length"
                                    class="text-center small text-muted mb-0">
                                    No se encontraron países para el término de búsqueda.
                                </p>
                            </div>

                            <p class="small text-muted mt-3 mb-0">
                                Esta biblioteca está pensada para integrarse fácilmente en
                                formularios de registro, configuración de enlaces de WhatsApp y
                                cualquier herramienta que necesite datos de países, indicativos y
                                monedas de forma centralizada.
                            </p>
                        </div>
                    </div>

                    <!-- FAQ SEO -->
                    <div class="row gy-4 mb-5">
                        <div class="col-12">
                            <section aria-labelledby="faq">
                                <h2 id="faq" class="h4 fw-bold mb-3">
                                    Preguntas frecuentes sobre la biblioteca de países
                                </h2>

                                <div class="accordion" id="accordionFaqCountry">
                                    <div class="accordion-item" v-for="(item, index) in seoData.faq" :key="index">
                                        <h2 class="accordion-header" :id="`heading-country-${index}`">
                                            <button class="accordion-button collapsed" type="button"
                                                data-bs-toggle="collapse" :data-bs-target="`#collapse-country-${index}`"
                                                aria-expanded="false" :aria-controls="`collapse-country-${index}`">
                                                <span class="small fw-semibold">
                                                    {{ item.question }}
                                                </span>
                                            </button>
                                        </h2>
                                        <div :id="`collapse-country-${index}`" class="accordion-collapse collapse"
                                            :aria-labelledby="`heading-country-${index}`"
                                            data-bs-parent="#accordionFaqCountry">
                                            <div class="accordion-body small text-muted">
                                                {{ item.answer }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-12">
                            <section aria-label="otras-herramientas">
                                <p class="small text-muted">
                                    En Toolbox Codwelt también encuentras herramientas gratuitas
                                    para generar enlaces de WhatsApp, comprimir imágenes y gestionar
                                    emojis, para centralizar toda la operación digital de tu marca.
                                </p>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TOAST copiado -->
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
            <div v-if="copyFeedback" class="toast show align-items-center text-bg-success border-0 shadow-sm"
                role="status">
                <div class="d-flex">
                    <div class="toast-body small">
                        {{ copyFeedback }}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" aria-label="Close"
                        @click="copyFeedback = ''"></button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.country-library-list {
    max-width: 100%;
}
</style>
