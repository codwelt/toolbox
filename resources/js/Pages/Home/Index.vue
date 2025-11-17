<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    seo: {
        type: Object,
        required: true,
    },
    tools: {
        type: Array,
        required: true,
    },
});

// JSON-LD ItemList de herramientas
const jsonLd = computed(() => {
    const items = (props.tools || []).map((tool, index) => ({
        '@type': 'ListItem',
        position: index + 1,
        url: tool.canonical,
        name: tool.title,
        description: tool.description,
    }));

    return JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'ItemList',
        name: 'Toolbox Codwelt - Herramientas online',
        itemListElement: items,
    });
});

const jsonLdScriptEl = ref(null);

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
    <div class="bg-light min-vh-100">

        <Head :title="seo.title">
            <meta name="description" :content="seo.description" />
            <meta property="og:type" content="website" />
            <meta property="og:title" :content="seo.title" />
            <meta property="og:description" :content="seo.description" />
            <meta property="og:url" :content="seo.canonical" />
            <meta name="twitter:card" content="summary_large_image" />
            <meta name="twitter:title" :content="seo.title" />
            <meta name="twitter:description" :content="seo.description" />
            <link rel="canonical" :href="seo.canonical" />
        </Head>

        <div class="container py-5">
            <!-- HERO -->
            <div class="row mb-4">
                <div class="col-lg-10 mx-auto text-center">
                    <h1 class="display-5 fw-bold mb-3">
                        Toolbox Codwelt
                    </h1>
                    <p class="lead text-muted mb-2">
                        Suite de herramientas online para optimizar imágenes y recursos digitales,
                        desarrolladas por Codwelt.
                    </p>
                    <p class="text-secondary">
                        Centraliza en un solo lugar todo lo que necesitas para preparar imágenes para web,
                        tiendas virtuales y redes sociales.
                    </p>
                </div>
            </div>

            <!-- LISTA DE HERRAMIENTAS -->
            <div class="row gy-4">
                <div class="col-lg-10 mx-auto">
                    <div class="row g-3">
                        <div v-for="tool in tools" :key="tool.key" class="col-md-6 col-xl-4">
                            <a class="text-decoration-none text-reset" :href="tool.path">
                                <div class="card shadow-sm border-0 h-100 tool-card">
                                    <div class="card-body">
                                        <h2 class="h6 fw-bold mb-2">
                                            {{ tool.title }}
                                        </h2>
                                        <p class="small text-muted mb-0">
                                            {{ tool.description }}
                                        </p>
                                    </div>
                                    <div class="card-footer bg-white border-0 pt-0 pb-3 px-3">
                                        <span class="small text-primary">
                                            Usar herramienta →
                                        </span>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <p class="small text-muted mt-4">
                        Seguiremos sumando nuevas herramientas a Toolbox Codwelt para facilitar el día a día
                        de desarrolladores, diseñadores y equipos de marketing.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.tool-card {
    transition: transform 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.tool-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.35rem 1rem rgba(0, 0, 0, 0.08);
}
</style>
