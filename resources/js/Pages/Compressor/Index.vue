<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    seo: {
        type: Object,
        required: true,
    },
});

// -------------------- ESTADO Y LÓGICA DE COMPRESIÓN --------------------
const files = ref([]);
const compressed = ref([]);
const quality = ref(0.7);
const maxWidth = ref(1920);
const maxHeight = ref(1080);
const isProcessing = ref(false);

function handleFileChange(e) {
    const selected = Array.from(e.target.files || []);
    files.value = selected;
    compressed.value = [];
}

function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return '';
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    const value = bytes / Math.pow(1024, i);
    return `${value.toFixed(2)} ${sizes[i]}`;
}

async function compressImages() {
    if (!files.value.length) return;
    isProcessing.value = true;
    compressed.value = [];

    for (const file of files.value) {
        const result = await compressSingle(file);
        if (result) {
            compressed.value.push(result);
        }
    }

    isProcessing.value = false;
}

function compressSingle(file) {
    return new Promise((resolve) => {
        const reader = new FileReader();

        reader.onload = (event) => {
            const img = new Image();
            img.onload = () => {
                const { canvas, ctx } = createCanvas(img);
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                canvas.toBlob(
                    (blob) => {
                        if (!blob) {
                            return resolve(null);
                        }
                        const url = URL.createObjectURL(blob);
                        resolve({
                            name: file.name,
                            originalSize: file.size,
                            newSize: blob.size,
                            url,
                        });
                    },
                    file.type || 'image/jpeg',
                    quality.value
                );
            };
            img.src = event.target.result;
        };

        reader.readAsDataURL(file);
    });
}

function createCanvas(img) {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');

    let width = img.width;
    let height = img.height;

    const ratio = Math.min(maxWidth.value / width, maxHeight.value / height, 1);
    width = width * ratio;
    height = height * ratio;

    canvas.width = width;
    canvas.height = height;

    return { canvas, ctx };
}

function download(file) {
    const a = document.createElement('a');
    a.href = file.url;
    a.download = file.name.replace(/\.(\w+)$/, '_compressed.$1');
    document.body.appendChild(a);
    a.click();
    a.remove();
}

function downloadAll() {
    compressed.value.forEach((f, index) => {
        setTimeout(() => download(f), index * 400);
    });
}

// -------------------- JSON-LD / SEO STRUCTURED DATA --------------------
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
            <meta
                v-if="seo.keywords && seo.keywords.length"
                name="keywords"
                :content="seo.keywords.join(', ')"
            />

            <!-- Open Graph -->
            <meta property="og:type" content="website" />
            <meta property="og:title" :content="seo.title" />
            <meta property="og:description" :content="seo.description" />
            <meta property="og:url" :content="seo.canonical" />

            <!-- Twitter Cards -->
            <meta name="twitter:card" content="summary_large_image" />
            <meta name="twitter:title" :content="seo.title" />
            <meta name="twitter:description" :content="seo.description" />

            <!-- Canonical -->
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
                        Optimiza tus imágenes para web, tiendas virtuales y campañas digitales
                        con una experiencia rápida y sencilla.
                    </p>
                </div>
            </div>

            <!-- TARJETA PRINCIPAL DE HERRAMIENTA -->
            <div class="row justify-content-center mb-4">
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4 p-md-5 text-center">
                            <div class="mb-3">
                                <div class="upload-area mx-auto mb-3">
                                    <label class="w-100 h-100 d-flex flex-column align-items-center justify-content-center cursor-pointer">
                                        <div class="mb-2 display-6 text-primary">
                                            <!-- Puedes cambiar por icono de Bootstrap Icons si los usas -->
                                            📷
                                        </div>
                                        <p class="fw-semibold mb-1">
                                            Selecciona imágenes o suéltalas aquí
                                        </p>
                                        <p class="small text-muted mb-0">
                                            Formatos admitidos: JPG, PNG, WebP
                                        </p>
                                        <input
                                            type="file"
                                            class="d-none"
                                            multiple
                                            accept="image/*"
                                            @change="handleFileChange"
                                        />
                                    </label>
                                </div>
                            </div>

                            <div class="row g-3 justify-content-center mb-3">
                                <div class="col-md-4">
                                    <div class="text-start text-md-center">
                                        <label class="form-label fw-semibold small text-uppercase text-muted">
                                            Calidad
                                        </label>
                                        <input
                                            type="range"
                                            min="0.2"
                                            max="1"
                                            step="0.05"
                                            v-model.number="quality"
                                            class="form-range"
                                        />
                                        <div class="small text-muted">
                                            {{ Math.round(quality * 100) }}% de calidad aproximada
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="text-start text-md-center">
                                        <label class="form-label fw-semibold small text-uppercase text-muted">
                                            Ancho máximo (px)
                                        </label>
                                        <input
                                            type="number"
                                            v-model.number="maxWidth"
                                            class="form-control form-control-sm text-center"
                                        />
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="text-start text-md-center">
                                        <label class="form-label fw-semibold small text-uppercase text-muted">
                                            Alto máximo (px)
                                        </label>
                                        <input
                                            type="number"
                                            v-model.number="maxHeight"
                                            class="form-control form-control-sm text-center"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                                <button
                                    type="button"
                                    class="btn btn-primary btn-lg px-4"
                                    :disabled="!files.length || isProcessing"
                                    @click="compressImages"
                                >
                                    <span v-if="!isProcessing">
                                        Comprimir imágenes
                                    </span>
                                    <span v-else>
                                        Procesando...
                                    </span>
                                </button>
                            </div>

                            <p class="small text-muted mt-3 mb-0">
                                Todas las imágenes se comprimen manteniendo el equilibrio entre
                                calidad visual y peso del archivo.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- LISTA DE IMÁGENES SELECCIONADAS -->
            <div
                v-if="files.length"
                class="row mb-4"
            >
                <div class="col-lg-8 mx-auto">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h2 class="h6 mb-0 fw-semibold">
                                Imágenes seleccionadas
                            </h2>
                        </div>
                        <div class="card-body p-3">
                            <ul class="list-group list-group-flush">
                                <li
                                    v-for="f in files"
                                    :key="f.name"
                                    class="list-group-item d-flex justify-content-between align-items-center px-2"
                                >
                                    <span class="text-truncate me-2">
                                        {{ f.name }}
                                    </span>
                                    <span class="badge bg-light text-secondary">
                                        {{ formatBytes(f.size) }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RESULTADOS COMPRIMIDOS -->
            <div
                v-if="compressed.length"
                class="row mb-5"
            >
                <div class="col-lg-8 mx-auto">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h2 class="h6 mb-0 fw-semibold">
                                Imágenes comprimidas listas para descargar
                            </h2>
                            <button
                                type="button"
                                class="btn btn-link btn-sm text-decoration-none"
                                @click="downloadAll"
                            >
                                Descargar todas
                            </button>
                        </div>
                        <div class="card-body p-3">
                            <div class="list-group list-group-flush">
                                <div
                                    v-for="file in compressed"
                                    :key="file.url"
                                    class="list-group-item d-flex justify-content-between align-items-center flex-wrap"
                                >
                                    <div class="me-2">
                                        <div class="fw-semibold small">
                                            {{ file.name }}
                                        </div>
                                        <div class="small text-muted">
                                            Original: {{ formatBytes(file.originalSize) }} ·
                                            Nuevo: {{ formatBytes(file.newSize) }} ·
                                            Ahorro:
                                            {{
                                                (100 - (file.newSize / file.originalSize) * 100).toFixed(1)
                                            }}%
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary btn-sm"
                                        @click="download(file)"
                                    >
                                        Descargar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CONTENIDO SEO: CÓMO FUNCIONA + FAQ + ENLACES INTERNOS -->
            <div class="row gy-4 mb-4">
                <div class="col-lg-8 mx-auto">
                    <section aria-labelledby="how-it-works">
                        <h2 id="how-it-works" class="h4 fw-bold mb-3">
                            ¿Cómo funciona el compresor de imágenes de Toolbox Codwelt?
                        </h2>
                        <p class="text-muted small mb-2">
                            La compresión se realiza directamente en tu navegador utilizando
                            tecnologías modernas de procesamiento de imágenes. Así evitas subir
                            archivos a servidores externos y mantienes el control de tu
                            información.
                        </p>
                        <p class="text-muted small mb-0">
                            Ideal para optimizar imágenes antes de subirlas a tu página web,
                            tienda virtual o campañas de publicidad, mejorando la velocidad de
                            carga y el rendimiento SEO.
                        </p>
                    </section>
                </div>
            </div>

            <div class="row gy-4 mb-5">
                <div class="col-lg-8 mx-auto">
                    <section aria-labelledby="faq">
                        <h2 id="faq" class="h4 fw-bold mb-3">
                            Preguntas frecuentes sobre la compresión de imágenes online
                        </h2>

                        <div class="accordion" id="accordionFaq">
                            <div
                                class="accordion-item"
                                v-for="(item, index) in seo.faq"
                                :key="index"
                            >
                                <h2 class="accordion-header" :id="`heading-${index}`">
                                    <button
                                        class="accordion-button collapsed"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        :data-bs-target="`#collapse-${index}`"
                                        aria-expanded="false"
                                        :aria-controls="`collapse-${index}`"
                                    >
                                        <span class="small fw-semibold">
                                            {{ item.question }}
                                        </span>
                                    </button>
                                </h2>
                                <div
                                    :id="`collapse-${index}`"
                                    class="accordion-collapse collapse"
                                    :aria-labelledby="`heading-${index}`"
                                    data-bs-parent="#accordionFaq"
                                >
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
                <div class="col-lg-8 mx-auto">
                    <section aria-label="otras-herramientas">
                        <p class="small text-muted">
                            Próximamente encontrarás en Toolbox Codwelt otras herramientas
                            gratuitas como
                            <a
                                href="/redimensionar-imagenes-online-gratis"
                                class="link-primary"
                            >
                                redimensionar imágenes online
                            </a>
                            y
                            <a
                                href="/convertir-imagenes-gratis"
                                class="link-primary"
                            >
                                conversores de formato para JPG, PNG y WebP.
                            </a>
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
</style>
