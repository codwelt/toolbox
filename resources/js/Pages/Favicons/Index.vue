<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';

const props = defineProps({
    seo: {
        type: Object,
        required: true,
    },
});

// ESTADO
const selectedFile = ref(null);
const fileInfo = ref(null);
const preview = ref(null);

const isProcessing = ref(false);
const errorMessage = ref(null);

const htmlSnippet = ref('');
const zipUrl = ref('');

// HANDLERS
function onFileChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;

    selectedFile.value = file;
    fileInfo.value = {
        name: file.name,
        size: formatBytes(file.size),
        type: file.type,
    };
    errorMessage.value = null;
    htmlSnippet.value = '';
    zipUrl.value = '';

    const reader = new FileReader();
    reader.onload = (event) => {
        preview.value = event.target?.result || null;
    };
    reader.readAsDataURL(file);
}

function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return '';
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    const value = bytes / Math.pow(1024, i);
    return `${value.toFixed(2)} ${sizes[i]}`;
}

async function generateFavicons() {
    if (!selectedFile.value) {
        errorMessage.value = 'Por favor selecciona una imagen para generar tus favicons.';
        return;
    }

    isProcessing.value = true;
    errorMessage.value = null;
    htmlSnippet.value = '';
    zipUrl.value = '';

    try {
        const formData = new FormData();
        formData.append('image', selectedFile.value);

        const response = await axios.post(
            '/api/tools/favicons',
            formData
        );

        htmlSnippet.value = response.data.html_tags;
        zipUrl.value = response.data.zip_url;
    } catch (error) {
        console.error(error);
        if (error.response?.data?.message) {
            errorMessage.value = error.response.data.message;
        } else {
            errorMessage.value = 'Ocurrió un error al generar los favicons.';
        }
    } finally {
        isProcessing.value = false;
    }
}

function downloadZip() {
    if (!zipUrl.value) return;
    window.location.href = zipUrl.value;
}

// JSON-LD
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
        applicationCategory: 'Multimedia',
        offers: {
            '@type': 'Offer',
            price: '0',
            priceCurrency: 'USD',
        },
        description: props.seo.description,
        potentialAction: {
            '@type': 'UseAction',
            target: props.seo.url,
        },
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
                        Sube una imagen cuadrada (ideal 512x512px) y recibe un paquete de favicons
                        listos para usar más el código HTML para integrarlos en tu sitio web.
                    </p>
                </div>
            </div>

            <!-- TARJETA PRINCIPAL -->
            <div class="row justify-content-center mb-4">
                <div class="col-lg-10">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4 p-md-5">
                            <div class="row g-4 align-items-start">
                                <!-- UPLOAD + ACCIONES -->
                                <div class="col-lg-5">
                                    <div class="mb-3 text-center text-lg-start">
                                        <h2 class="h5 fw-bold mb-2">
                                            Sube tu imagen base
                                        </h2>
                                        <p class="small text-muted mb-0">
                                            Lo ideal es usar una imagen cuadrada de al menos 512x512 px.
                                            Formatos sugeridos: PNG (con fondo transparente) o JPG.
                                        </p>
                                    </div>

                                    <div class="upload-area mb-3 mx-auto mx-lg-0">
                                        <label
                                            class="w-100 h-100 d-flex flex-column align-items-center justify-content-center cursor-pointer">
                                            <div class="mb-2 display-6 text-primary">
                                                ⭐
                                            </div>
                                            <p class="fw-semibold mb-1">
                                                Selecciona una imagen o suéltala aquí
                                            </p>
                                            <p class="small text-muted mb-0">
                                                Generaremos favicon.ico y varios tamaños PNG.
                                            </p>
                                            <input type="file" class="d-none" accept="image/*" @change="onFileChange" />
                                        </label>
                                    </div>

                                    <div v-if="fileInfo" class="mb-3">
                                        <div class="border rounded p-2 bg-white small">
                                            <div class="fw-semibold text-truncate">
                                                {{ fileInfo.name }}
                                            </div>
                                            <div class="text-muted">
                                                {{ fileInfo.size }} · {{ fileInfo.type }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-primary"
                                            :disabled="!selectedFile || isProcessing" @click="generateFavicons">
                                            <span v-if="!isProcessing">
                                                Generar favicons y código HTML
                                            </span>
                                            <span v-else>
                                                Procesando...
                                            </span>
                                        </button>
                                        <p class="small text-muted mb-0">
                                            El procesamiento generará todas las versiones necesarias y
                                            un paquete ZIP descargable.
                                        </p>
                                    </div>

                                    <div v-if="errorMessage" class="alert alert-danger mt-3 py-2 small" role="alert">
                                        {{ errorMessage }}
                                    </div>
                                </div>

                                <!-- PREVIEW + RESULTADOS -->
                                <div class="col-lg-7">
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <h3 class="h6 fw-bold mb-2 text-center">
                                                Vista previa de la imagen
                                            </h3>
                                            <div
                                                class="preview-box border rounded bg-white d-flex align-items-center justify-content-center">
                                                <img v-if="preview" :src="preview" alt="Vista previa imagen base"
                                                    class="img-fluid" />
                                                <span v-else class="text-muted small text-center px-2">
                                                    Aún no has seleccionado ninguna imagen.
                                                </span>
                                            </div>
                                        </div>

                                        <div class="col-md-6 d-flex flex-column">
                                            <h3 class="h6 fw-bold mb-2 text-center">
                                                Paquete de favicons
                                            </h3>
                                            <div
                                                class="flex-grow-1 d-flex align-items-center justify-content-center border rounded bg-light">
                                                <span v-if="!zipUrl" class="text-muted small text-center px-3">
                                                    Cuando generes los favicons podrás descargar un archivo ZIP con
                                                    todos los iconos.
                                                </span>
                                                <button v-else type="button" class="btn btn-outline-primary btn-sm"
                                                    @click="downloadZip">
                                                    Descargar paquete de favicons (.zip)
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- SNIPPET HTML -->
                                    <div class="mb-3">
                                        <h3 class="h6 fw-bold mb-2">
                                            Código HTML para tu favicon
                                        </h3>
                                        <p class="small text-muted mb-1">
                                            Copia este bloque y pégalo dentro de la etiqueta
                                            <code>&lt;head&gt;</code> de tu sitio web, preferiblemente
                                            antes de <code>&lt;/head&gt;</code>.
                                        </p>
                                        <textarea class="form-control form-control-sm font-monospace" rows="8" readonly
                                            v-model="htmlSnippet"
                                            placeholder="Genera tus favicons para ver el código HTML aquí..."></textarea>
                                    </div>

                                    <p class="small text-muted mb-0">
                                        En Laravel, puedes incluir este código en tu layout principal
                                        (por ejemplo <code>resources/views/layouts/app.blade.php</code>).
                                        En WordPress, puedes integrarlo en <code>header.php</code> o
                                        mediante las opciones del tema si ofrece un campo específico
                                        para favicons.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CONTENIDO SEO / EDUCACIÓN -->
            <div class="row gy-4 mb-4">
                <div class="col-lg-10 mx-auto">
                    <section aria-labelledby="how-it-works">
                        <h2 id="how-it-works" class="h4 fw-bold mb-3">
                            ¿Dónde se pone el código del favicon y cómo usarlo correctamente?
                        </h2>
                        <p class="text-muted small mb-2">
                            El snippet HTML que genera esta herramienta debe ir dentro de la
                            etiqueta <code>&lt;head&gt;</code> de tu sitio. Esto garantiza que
                            navegadores, sistemas operativos y motores de búsqueda detecten
                            correctamente tu favicon.
                        </p>
                        <p class="text-muted small mb-2">
                            En proyectos Laravel, lo habitual es tener una vista de layout
                            principal (por ejemplo <code>app.blade.php</code>) donde defines
                            los metadatos globales. El bloque de favicons debe incluirse ahí
                            para que aplique a todas las páginas.
                        </p>
                        <p class="text-muted small mb-0">
                            En WordPress, puedes pegar el código en <code>header.php</code>, o
                            utilizar las opciones del tema si permite configurar la imagen del
                            sitio. Si optas por subir las imágenes manualmente, puedes seguir
                            usando las rutas generadas por esta herramienta y mantener un
                            control más preciso de tus favicons.
                        </p>
                    </section>
                </div>
            </div>

            <!-- FAQ SEO -->
            <div class="row gy-4 mb-5">
                <div class="col-lg-10 mx-auto">
                    <section aria-labelledby="faq">
                        <h2 id="faq" class="h4 fw-bold mb-3">
                            Preguntas frecuentes sobre favicons para sitios web
                        </h2>

                        <div class="accordion" id="accordionFaqFavicons">
                            <div class="accordion-item" v-for="(item, index) in seo.faq" :key="index">
                                <h2 class="accordion-header" :id="`heading-favicon-${index}`">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                        :data-bs-target="`#collapse-favicon-${index}`" aria-expanded="false"
                                        :aria-controls="`collapse-favicon-${index}`">
                                        <span class="small fw-semibold">
                                            {{ item.question }}
                                        </span>
                                    </button>
                                </h2>
                                <div :id="`collapse-favicon-${index}`" class="accordion-collapse collapse"
                                    :aria-labelledby="`heading-favicon-${index}`"
                                    data-bs-parent="#accordionFaqFavicons">
                                    <div class="accordion-body small text-muted">
                                        {{ item.answer }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <!-- ENLACES A OTRAS HERRAMIENTAS -->
            <div class="row mb-4">
                <div class="col-lg-10 mx-auto">
                    <section aria-label="otras-herramientas">
                        <p class="small text-muted">
                            Complementa tus favicons con otras herramientas gratuitas de
                            Toolbox Codwelt, como
                            <a href="/comprimir-imagenes-online" class="link-primary">
                                comprimir imágenes online
                            </a>,
                            <a href="/redimensionar-imagenes-online" class="link-primary">
                                redimensionar imágenes
                            </a>
                            y
                            <a href="/quitar-fondo-imagen" class="link-primary">
                                quitar el fondo de tus imágenes
                            </a>.
                        </p>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.upload-area {
    max-width: 420px;
    height: 190px;
    border: 2px dashed #ced4da;
    border-radius: 0.75rem;
    background-color: #f8f9fa;
    transition: background-color 0.15s ease-in-out, border-color 0.15s ease-in-out,
        transform 0.15s ease-in-out;
}

.upload-area:hover {
    background-color: #eef2f7;
    border-color: #0d6efd;
    transform: translateY(-1px);
    cursor: pointer;
}

.preview-box {
    width: 100%;
    height: 220px;
    overflow: hidden;
}

.preview-box img {
    max-height: 100%;
    object-fit: contain;
}
</style>