<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { emojiCategories } from '@/emoji/emojiLibrary';
import AppLayout from './../../Layouts/AppLayout.vue';

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    seo: {
        type: Object,
        required: true,
    },
});

// ---- Estado de filtros / selección ----
const search = ref('');
const activeCategoryId = ref(emojiCategories[0]?.id || null);

const selectedEmoji = ref(null);
const copyFeedback = ref('');
const history = ref([]); // historial de emojis copiados

// ---- Cálculos ----
const visibleCategories = computed(() => {
    const term = search.value.toLowerCase().trim();

    return emojiCategories.map((cat) => {
        let emojis = cat.emojis;

        if (term) {
            emojis = emojis.filter((e) => {
                const name = e.name || '';
                const nameEs = e.nameEs || '';
                const shortcode = e.shortcode || '';
                const keywords = (e.keywords || []).join(' ');
                const base = `${name} ${nameEs} ${shortcode} ${keywords}`;
                return base.toLowerCase().includes(term);
            });
        }

        return {
            ...cat,
            emojis,
        };
    });
});


// ---- Historial: insertamos emoji al frente, sin duplicar, máx N ----
function pushToHistory(emoji) {
    const index = history.value.findIndex((e) => e.char === emoji.char);
    if (index !== -1) {
        history.value.splice(index, 1);
    }
    history.value.unshift(emoji);
    const MAX = 24;
    if (history.value.length > MAX) {
        history.value.splice(MAX);
    }
}

// ---- Acciones ----
async function selectEmoji(emoji) {
    selectedEmoji.value = emoji;
    copyFeedback.value = '';

    // Guardamos en historial
    pushToHistory(emoji);

    try {
        await navigator.clipboard.writeText(emoji.char);
        copyFeedback.value = `Emoji "${emoji.char}" copiado al portapapeles ✅`;
    } catch (e) {
        console.error(e);
        copyFeedback.value =
            'No se pudo copiar el emoji automáticamente. Intenta seleccionarlo y copiarlo manualmente.';
    }

    setTimeout(() => {
        copyFeedback.value = '';
    }, 2500);
}

// Scroll a la categoría al hacer clic en el icono
function scrollToCategory(catId) {
    activeCategoryId.value = catId;
    const el = document.getElementById(`category-${catId}`);
    if (!el) return;

    const offset = 120; // ajustar según el alto del header/sticky
    const top = el.getBoundingClientRect().top + window.scrollY - offset;

    window.scrollTo({
        top,
        behavior: 'smooth',
    });
}

// ---- JSON-LD SEO ----
const jsonLd = computed(() => {
    const faqStructured = (props.seo.faq || []).map((item) => ({
        '@type': 'Question',
        name: item.question,
        acceptedAnswer: {
            '@type': 'Answer',
            text: item.answer,
        },
    }));

    return JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'WebApplication',
        name: props.seo.title,
        url: props.seo.url,
        applicationCategory: 'Utility',
        description: props.seo.description,
        mainEntity: {
            '@type': 'FAQPage',
            mainEntity: faqStructured,
        },
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
        <!-- HEAD SEO -->

        <Head :title="seo.title">
            <meta name="description" :content="seo.description" />
            <meta v-if="seo.keywords && seo.keywords.length" name="keywords" :content="seo.keywords.join(', ')" />
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
                        {{ seo.h1 }}
                    </h1>
                    <p class="lead text-muted mb-2">
                        {{ seo.description }}
                    </p>
                    <p class="text-secondary">
                        Explora emojis organizados por categorías al estilo WhatsApp, búscalos por
                        nombre o palabra clave y cópialos con un solo clic.
                    </p>
                </div>
            </div>

            <!-- CONTENIDO PRINCIPAL -->
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-body p-4 p-md-5">
                            <div class="row g-4">
                                <!-- LADO IZQUIERDO: filtros + listado completo -->
                                <div class="col-lg-8">
                                    <!-- Toolbar sticky: filtros + categorías -->
                                    <div class="emoji-toolbar-sticky">
                                        <!-- Filtro de búsqueda -->
                                        <div class="d-flex mb-3">
                                            <div class="flex-grow-1">
                                                <input type="text" class="form-control form-control-sm" v-model="search"
                                                    placeholder="Buscar emoji en español (ej: feliz, corazón, mano)..." />
                                            </div>
                                        </div>

                                        <!-- Tabs de categorías (sticky) -->
                                        <div class="emoji-category-tabs-wrapper">
                                            <ul class="nav nav-pills nav-justified mb-2 emoji-category-tabs">
                                                <li class="nav-item" v-for="cat in visibleCategories" :key="cat.id">
                                                    <button class="nav-link py-1"
                                                        :class="{ active: cat.id === activeCategoryId }"
                                                        @click="scrollToCategory(cat.id)" type="button"
                                                        :title="cat.label">
                                                        <span class="d-block">{{ cat.icon }}</span>
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>


                                    <!-- Listado completo de categorías + emojis -->
                                    <div class="emoji-categories-list mt-3">
                                        <section v-for="cat in visibleCategories" :key="cat.id"
                                            :id="`category-${cat.id}`" class="mb-4">
                                            <div class="d-flex align-items-center mb-2">
                                                <span class="me-2 fs-4">
                                                    {{ cat.icon }}
                                                </span>
                                                <h2 class="h6 fw-bold mb-0">
                                                    {{ cat.label }}
                                                </h2>
                                            </div>

                                            <div v-if="cat.emojis.length"
                                                class="emoji-library-grid border rounded p-2 bg-white">
                                                <button v-for="emoji in cat.emojis" :key="emoji.char + emoji.name"
                                                    type="button" class="btn btn-light btn-sm emoji-btn"
                                                    :title="emoji.name" @click="selectEmoji(emoji)">
                                                    {{ emoji.char }}
                                                </button>
                                            </div>
                                            <p v-else class="small text-muted mb-0">
                                                No hay emojis en esta categoría para el filtro aplicado.
                                            </p>
                                        </section>
                                    </div>

                                    <p class="small text-muted mt-2 mb-0">
                                        Haz clic sobre cualquier emoji para copiarlo al portapapeles
                                        y reutilizarlo en tus mensajes, publicaciones o páginas web.
                                    </p>
                                </div>

                                <!-- LADO DERECHO: emoji seleccionado + historial + info -->
                                <div class="col-lg-4">
                                    <div class="emoji-sidebar-sticky">
                                        <!-- Emoji seleccionado -->
                                        <h2 class="h6 fw-bold mb-3">
                                            Emoji seleccionado
                                        </h2>

                                        <div class="card border-0 shadow-sm mb-3">
                                            <div class="card-body text-center py-4">
                                                <div class="display-3 mb-2">
                                                    {{ selectedEmoji ? selectedEmoji.char : '🙂' }}
                                                </div>
                                                <p class="mb-1 fw-semibold">
                                                    {{ selectedEmoji ? selectedEmoji.name : 'Selecciona un emoji' }}
                                                </p>

                                                <!-- SHORTCODE -->
                                                <p class="small text-muted mb-1"
                                                    v-if="selectedEmoji && selectedEmoji.shortcode">
                                                    {{ selectedEmoji.shortcode }}
                                                </p>
                                                <p class="small text-muted mb-1" v-else>
                                                    Al hacer clic sobre un emoji se mostrará aquí su información.
                                                </p>

                                                <!-- KEYWORDS -->
                                                <p class="small text-muted mb-0">
                                                    {{
                                                        selectedEmoji && selectedEmoji.keywords
                                                            ? selectedEmoji.keywords.join(', ')
                                                            : ''
                                                    }}
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Historial -->
                                        <section class="mb-3">
                                            <h3 class="h6 fw-bold mb-2">
                                                Historial de emojis copiados
                                            </h3>
                                            <div v-if="history.length"
                                                class="emoji-history-grid border rounded p-2 bg-white">
                                                <button v-for="emoji in history" :key="emoji.char + '-history'"
                                                    type="button" class="btn btn-light btn-sm emoji-btn"
                                                    :title="emoji.name" @click="selectEmoji(emoji)">
                                                    {{ emoji.char }}
                                                </button>
                                            </div>
                                            <p v-else class="small text-muted mb-0">
                                                Aún no hay historial. Cuando copies emojis, aparecerán aquí para
                                                reutilizarlos
                                                con un solo clic.
                                            </p>
                                        </section>

                                        <!-- Cómo usar -->
                                        <section class="mb-3">
                                            <h3 class="h6 fw-bold mb-2">
                                                ¿Cómo usar esta biblioteca?
                                            </h3>
                                            <p class="small text-muted mb-1">
                                                1. Usa el buscador y los filtros de estilo para encontrar el emoji
                                                ideal.
                                            </p>
                                            <p class="small text-muted mb-1">
                                                2. Navega por las categorías con la barra superior fija: al hacer clic,
                                                te
                                                desplazarás directamente a cada sección.
                                            </p>
                                            <p class="small text-muted mb-0">
                                                3. Haz clic sobre cualquier emoji para copiarlo al portapapeles y se
                                                guardará
                                                automáticamente en tu historial reciente.
                                            </p>
                                        </section>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SEO text + FAQ -->
                    <div class="row gy-4 mb-4">
                        <div class="col-12">
                            <section aria-labelledby="how-it-works">
                                <h2 id="how-it-works" class="h4 fw-bold mb-3">
                                    Biblioteca de emojis estilo WhatsApp para copiar y pegar
                                </h2>
                                <p class="text-muted small mb-2">
                                    Esta herramienta organiza los emojis en categorías similares a WhatsApp para que
                                    encuentres rápidamente el icono que necesitas. Puedes navegar por las pestañas,
                                    filtrar por estilo de emoji y utilizar el buscador avanzado para localizar
                                    emojis
                                    específicos mientras ves todo el catálogo en una sola vista.
                                </p>
                                <p class="text-muted small mb-0">
                                    Es ideal para community managers, diseñadores, equipos de marketing y cualquier
                                    persona que quiera enriquecer sus mensajes, publicaciones y páginas web con
                                    emojis
                                    de forma rápida y ordenada.
                                </p>
                            </section>
                        </div>
                    </div>

                    <div class="row gy-4 mb-5">
                        <div class="col-12">
                            <section aria-labelledby="faq">
                                <h2 id="faq" class="h4 fw-bold mb-3">
                                    Preguntas frecuentes sobre la biblioteca de emojis
                                </h2>

                                <div class="accordion" id="accordionFaqEmoji">
                                    <div class="accordion-item" v-for="(item, index) in seo.faq" :key="index">
                                        <h2 class="accordion-header" :id="`heading-emoji-${index}`">
                                            <button class="accordion-button collapsed" type="button"
                                                data-bs-toggle="collapse" :data-bs-target="`#collapse-emoji-${index}`"
                                                aria-expanded="false" :aria-controls="`collapse-emoji-${index}`">
                                                <span class="small fw-semibold">
                                                    {{ item.question }}
                                                </span>
                                            </button>
                                        </h2>
                                        <div :id="`collapse-emoji-${index}`" class="accordion-collapse collapse"
                                            :aria-labelledby="`heading-emoji-${index}`"
                                            data-bs-parent="#accordionFaqEmoji">
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
                                    En Toolbox Codwelt también encuentras herramientas gratuitas para
                                    <a href="/generador-links-whatsapp" class="link-primary">
                                        generar enlaces de WhatsApp
                                    </a>,
                                    <a href="/comprimir-imagenes-online" class="link-primary">
                                        comprimir imágenes
                                    </a>
                                    y
                                    <a href="/quitar-fondo-imagen" class="link-primary">
                                        quitar el fondo de tus fotos
                                    </a>
                                    para optimizar toda tu comunicación digital.
                                </p>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TOAST de copiado -->
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
.emoji-toolbar-sticky {
    position: sticky;
    top: 0;
    z-index: 20;
    background-color: #f8f9fa;
    padding-bottom: 0.5rem;
}

/* barra de categorías */
.emoji-category-tabs-wrapper {
    border-bottom: 1px solid #e0e0e0;
}

.emoji-category-tabs .nav-link {
    font-size: 1rem;
}

/* listado principal */
.emoji-library-grid {
    max-height: 920px;
    overflow-y: auto;
    display: grid;
    grid-template-columns: repeat(8, minmax(0, 1fr));
    /* menos columnas = más espacio por emoji */
    gap: 6px;
}

/* historial */
.emoji-history-grid {
    max-height: 200px;
    overflow-y: auto;
    display: grid;
    grid-template-columns: repeat(8, minmax(0, 1fr));
    gap: 4px;
}

.emoji-btn {
    padding: 6px 0;
    line-height: 1.3;
    font-size: 1.8rem;
    /* 🔹 AQUÍ SE HACE GRANDE EL EMOJI */
    background-color: transparent;
    border: none;
}

.emoji-sidebar-sticky {
    position: sticky;
    top: 80px;
    /* ajusta según la altura de tu navbar/header */
}
</style>
