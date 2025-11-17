<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref, onMounted, onBeforeUnmount, watch } from 'vue';
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

// -------------------- ESTADO --------------------
const files = ref([]); // objetos con { id, file, name, size, type, previewUrl }
const compressed = ref([]);
const quality = ref(0.7);
const maxWidth = ref(1920);
const maxHeight = ref(1080);
const isProcessing = ref(false);
const progress = ref(0); // 0–100

const keepAspect = ref(true); // Mantener proporción ancho/alto
const originalAspectRatio = ref(maxWidth.value / maxHeight.value || 16 / 9);
const isAdjustingSize = ref(false);

const uploadMessage = ref('');

// Presets según objetivo
const presets = [
    {
        id: 'web',
        label: 'Sitio web / Landing page',
        width: 1600,
        height: 900,
        quality: 0.75,
        description:
            'Ideal para banners y secciones principales de una página web. Buen equilibrio entre calidad y peso (150–300 KB recomendado).',
    },
    {
        id: 'blog',
        label: 'Blog / Artículos',
        width: 1200,
        height: 800,
        quality: 0.75,
        description:
            'Perfecto para imágenes de contenido y entradas de blog. Peso sugerido: 100–200 KB por imagen.',
    },
    {
        id: 'app',
        label: 'Aplicación web o móvil',
        width: 1024,
        height: 1024,
        quality: 0.8,
        description:
            'Pensado para recursos internos de una app. Prioriza nitidez manteniendo un peso contenido (hasta ~300 KB).',
    },
    {
        id: 'social-square',
        label: 'Redes sociales (cuadrado)',
        width: 1080,
        height: 1080,
        quality: 0.8,
        description:
            'Formato clásico para Instagram y redes sociales. Ideal para posts de feed y carruseles.',
    },
    {
        id: 'social-story',
        label: 'Redes sociales (stories/reels)',
        width: 1080,
        height: 1920,
        quality: 0.8,
        description:
            'Relación vertical para historias y reels. Peso recomendado por pieza: 200–400 KB.',
    },
];

const selectedPreset = ref('custom');

const currentPresetDescription = computed(() => {
    const preset = presets.find((p) => p.id === selectedPreset.value);
    if (!preset) {
        return 'Configura manualmente el ancho, alto y la calidad según las necesidades de tu proyecto.';
    }
    return preset.description;
});

// -------------------- UTILIDADES --------------------
function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return '';
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    const value = bytes / Math.pow(1024, i);
    return `${value.toFixed(2)} ${sizes[i]}`;
}

function clearCompressed() {
    compressed.value.forEach((item) => {
        if (item.url) {
            URL.revokeObjectURL(item.url);
        }
    });
    compressed.value = [];
}

function clearFiles() {
    files.value.forEach((item) => {
        if (item.previewUrl) {
            URL.revokeObjectURL(item.previewUrl);
        }
    });
    files.value = [];
}

// -------------------- MANEJO DE ARCHIVOS --------------------
function handleFileChange(e) {
    const selected = Array.from(e.target.files || []);
    const valid = selected.filter((f) => f.type.startsWith('image/'));

    clearFiles();
    clearCompressed();

    if (valid.length) {
        files.value = valid.map((file, index) => ({
            id: `${Date.now()}-${index}-${file.name}`,
            file,
            name: file.name,
            size: file.size,
            type: file.type,
            previewUrl: URL.createObjectURL(file),
        }));
        uploadMessage.value = `${valid.length} imagen(es) cargada(s) correctamente.`;
    } else if (selected.length) {
        uploadMessage.value = 'Los archivos seleccionados no son imágenes válidas.';
        files.value = [];
    } else {
        uploadMessage.value = '';
        files.value = [];
    }
}

function removeFile(id) {
    const index = files.value.findIndex((f) => f.id === id);
    if (index !== -1) {
        const item = files.value[index];
        if (item.previewUrl) {
            URL.revokeObjectURL(item.previewUrl);
        }
        files.value.splice(index, 1);
    }
    if (!files.value.length) {
        uploadMessage.value = 'No hay imágenes seleccionadas actualmente.';
    }
}

// -------------------- PRESETS --------------------
function onPresetChange() {
    if (selectedPreset.value === 'custom') {
        return;
    }
    const preset = presets.find((p) => p.id === selectedPreset.value);
    if (!preset) return;

    keepAspect.value = true;
    originalAspectRatio.value = preset.width / preset.height;

    maxWidth.value = preset.width;
    maxHeight.value = preset.height;
    quality.value = preset.quality;
}

// -------------------- ASPECT RATIO WATCHERS --------------------
watch(keepAspect, (val) => {
    if (val && maxWidth.value > 0 && maxHeight.value > 0) {
        originalAspectRatio.value = maxWidth.value / maxHeight.value;
    }
});

watch(maxWidth, (newVal) => {
    if (!keepAspect.value || isAdjustingSize.value) return;
    if (!newVal || newVal <= 0) return;
    isAdjustingSize.value = true;
    maxHeight.value = Math.round(newVal / originalAspectRatio.value);
    isAdjustingSize.value = false;
});

watch(maxHeight, (newVal) => {
    if (!keepAspect.value || isAdjustingSize.value) return;
    if (!newVal || newVal <= 0) return;
    isAdjustingSize.value = true;
    maxWidth.value = Math.round(newVal * originalAspectRatio.value);
    isAdjustingSize.value = false;
});

// -------------------- COMPRESIÓN --------------------
function createCanvas(img) {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');

    let width;
    let height;

    if (keepAspect.value) {
        const ratio = Math.min(
            maxWidth.value / img.width || 1,
            maxHeight.value / img.height || 1,
            1
        );
        width = img.width * ratio;
        height = img.height * ratio;
    } else {
        width = maxWidth.value > 0 ? maxWidth.value : img.width;
        height = maxHeight.value > 0 ? maxHeight.value : img.height;
    }

    canvas.width = width;
    canvas.height = height;

    return { canvas, ctx };
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

async function compressImages() {
    if (!files.value.length || isProcessing.value) return;

    isProcessing.value = true;
    progress.value = 0;
    clearCompressed();

    const total = files.value.length;
    const queue = [...files.value];
    let processed = 0;

    for (const item of queue) {
        const result = await compressSingle(item.file);
        if (result) {
            compressed.value.push(result);
        }

        // Eliminar la imagen original de la lista de seleccionadas
        const index = files.value.findIndex((f) => f.id === item.id);
        if (index !== -1) {
            const fileItem = files.value[index];
            if (fileItem.previewUrl) {
                URL.revokeObjectURL(fileItem.previewUrl);
            }
            files.value.splice(index, 1);
        }

        processed++;
        progress.value = Math.round((processed / total) * 100);
    }

    isProcessing.value = false;
    uploadMessage.value = 'Proceso de compresión finalizado correctamente.';
}

// -------------------- DESCARGAS --------------------
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

// -------------------- JSON-LD / SEO --------------------
const jsonLd = computed(() => {
    const faqStructured = Array.isArray(props.seo.faq)
        ? props.seo.faq.map((item) => ({
            '@type': 'Question',
            name: item.question,
            acceptedAnswer: {
                '@type': 'Answer',
                text: item.answer,
            },
        }))
        : [];

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
    clearCompressed();
    clearFiles();
    if (jsonLdScriptEl.value && jsonLdScriptEl.value.parentNode) {
        jsonLdScriptEl.value.parentNode.removeChild(jsonLdScriptEl.value);
    }
});
</script>

<template>
        <!-- HEAD SEO -->

        <Head :title="seo.title">
            <meta name="description" :content="seo.description" />
            <meta v-if="seo.keywords && seo.keywords.length" name="keywords" :content="seo.keywords.join(', ')" />

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
                <div class="col-md">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4 p-md-5 text-center">
                            <div class="mb-3">
                                <div class="upload-area mx-auto mb-2">
                                    <label
                                        class="w-100 h-100 d-flex flex-column align-items-center justify-content-center cursor-pointer">
                                        <div class="mb-2 display-6 text-primary">
                                            📷
                                        </div>
                                        <p class="fw-semibold mb-1">
                                            Selecciona imágenes o suéltalas aquí
                                        </p>
                                        <p class="small text-muted mb-0">
                                            Formatos admitidos: JPG, PNG, WebP
                                        </p>
                                        <input type="file" class="d-none" multiple accept="image/*"
                                            @change="handleFileChange" />
                                    </label>
                                </div>
                                <p v-if="uploadMessage" class="small mt-1 mb-0"
                                    :class="files.length ? 'text-success' : 'text-danger'">
                                    {{ uploadMessage }}
                                </p>
                            </div>

                            <!-- Controles de calidad y tamaño -->
                            <div class="row g-3 justify-content-center mb-3">
                                <div class="col-md-4">
                                    <div class="text-start text-md-center">
                                        <label class="form-label fw-semibold small text-uppercase text-muted">
                                            Calidad
                                        </label>
                                        <input type="range" min="0.2" max="1" step="0.05" v-model.number="quality"
                                            class="form-range" />
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
                                        <input type="number" v-model.number="maxWidth"
                                            class="form-control form-control-sm text-center" min="1" />
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="text-start text-md-center">
                                        <label class="form-label fw-semibold small text-uppercase text-muted">
                                            Alto máximo (px)
                                        </label>
                                        <input type="number" v-model.number="maxHeight"
                                            class="form-control form-control-sm text-center" min="1" />
                                    </div>
                                </div>
                            </div>

                            <!-- Presets y proporción -->
                            <div class="row g-3 justify-content-center mb-3">
                                <div class="col-md-6">
                                    <div class="text-start text-md-center">
                                        <label class="form-label fw-semibold small text-uppercase text-muted">
                                            Objetivo de uso
                                        </label>
                                        <select v-model="selectedPreset" @change="onPresetChange"
                                            class="form-select form-select-sm">
                                            <option value="custom">Configuración personalizada</option>
                                            <option v-for="preset in presets" :key="preset.id" :value="preset.id">
                                                {{ preset.label }}
                                            </option>
                                        </select>
                                        <p class="small text-muted mt-2 mb-0">
                                            {{ currentPresetDescription }}
                                        </p>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="text-start text-md-center mt-md-4 pt-md-1">
                                        <div
                                            class="form-check form-switch d-flex align-items-center justify-content-md-center justify-content-start">
                                            <input class="form-check-input me-2" type="checkbox" id="keepAspect"
                                                v-model="keepAspect" />
                                            <label class="form-check-label small text-muted" for="keepAspect">
                                                Mantener proporción entre ancho y alto
                                            </label>
                                        </div>
                                        <p class="small text-muted mt-1 mb-0">
                                            Desactiva esta opción si quieres forzar exactamente las medidas
                                            indicadas (puede deformar la imagen).
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Botón de compresión -->
                            <div class="d-grid gap-2 d-sm-flex justify-content-sm-center mt-4">
                                <button type="button" class="btn btn-primary btn-lg px-4"
                                    :disabled="!files.length || isProcessing" @click="compressImages">
                                    <span v-if="!isProcessing">
                                        Comprimir imágenes
                                    </span>
                                    <span v-else>
                                        Procesando...
                                    </span>
                                </button>
                            </div>

                            <!-- BARRA DE PROGRESO -->
                            <div v-if="isProcessing" class="mt-3">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar" role="progressbar" :style="{ width: progress + '%' }"
                                        :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <p class="small text-muted mt-2 mb-0 text-center">
                                    Comprimiendo imágenes... {{ progress }}% completado
                                </p>
                            </div>

                            <p class="small text-muted mt-3 mb-0">
                                Todas las imágenes se comprimen manteniendo el equilibrio entre
                                calidad visual y peso del archivo.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- COLUMNA DERECHA: LISTAS -->
                <div class="col-md card border-0 shadow-sm mt-3 mt-md-0">
                    <!-- LISTA DE IMÁGENES SELECCIONADAS -->
                    <div v-if="files.length" class="row mb-4">
                        <div class="col-md-12 mx-auto">
                            <div>
                                <div class="card-header bg-white">
                                    <h2 class="h6 mb-0 fw-semibold">
                                        Imágenes seleccionadas
                                    </h2>
                                </div>
                                <div class="card-body p-3">
                                    <ul class="list-group list-group-flush">
                                        <li v-for="f in files" :key="f.id"
                                            class="list-group-item d-flex align-items-center">
                                            <div class="d-flex align-items-center flex-grow-1">
                                                <img :src="f.previewUrl" alt="Vista previa de la imagen"
                                                    class="me-3 rounded"
                                                    style="width: 48px; height: 48px; object-fit: cover;" />
                                                <div class="me-2 flex-grow-1">
                                                    <div class="text-truncate small fw-semibold">
                                                        {{ f.name }}
                                                    </div>
                                                    <div class="small text-muted">
                                                        {{ formatBytes(f.size) }}
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                @click="removeFile(f.id)">
                                                Quitar
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RESULTADOS COMPRIMIDOS -->
                    <div v-if="compressed.length" class="row mb-5">
                        <div class="col-lg-12 mx-auto">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                    <h2 class="h6 mb-0 fw-semibold">
                                        Imágenes comprimidas listas para descargar
                                    </h2>
                                    <button type="button" class="btn btn-link btn-sm text-decoration-none"
                                        @click="downloadAll">
                                        Descargar todas
                                    </button>
                                </div>
                                <div class="card-body p-3">
                                    <div class="list-group list-group-flush">
                                        <div v-for="file in compressed" :key="file.url"
                                            class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
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
                                            <button type="button" class="btn btn-outline-primary btn-sm"
                                                @click="download(file)">
                                                Descargar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Recomendaciones rápidas -->
            <div class="row gy-4 mb-4">
                <div class="col-lg-8 mx-auto">
                    <section aria-labelledby="how-it-works">
                        <h2 id="how-it-works" class="h4 fw-bold mb-3">Recomendaciones rápidas de tamaño y peso:</h2>
                        <p class="text-muted small mb-2">✅ <strong>Web / Landing:</strong> 1600–1920 px de ancho, &lt; 300 KB para imágenes principales.</p>
                        <p class="text-muted small mb-2">✅ <strong>Blog / Contenido:</strong> ~1200 px de ancho, 100–200 KB por imagen.</p>
                        <p class="text-muted small mb-2">✅ <strong>Aplicaciones:</strong> entre 800 y 1200 px de ancho, priorizando nitidez sin superar 300 KB.</p>
                        <p class="text-muted small mb-2">✅ <strong>Redes sociales:</strong> 1080x1080 (feed) o 1080x1920 (stories/reels), hasta 400 KB por pieza.</p>
                    </section>
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
                                <div class="accordion-item" v-for="(item, index) in seo.faq" :key="index">
                                    <h2 class="accordion-header" :id="`heading-${index}`">
                                        <button class="accordion-button collapsed" type="button"
                                            data-bs-toggle="collapse" :data-bs-target="`#collapse-${index}`"
                                            aria-expanded="false" :aria-controls="`collapse-${index}`">
                                            <span class="small fw-semibold">
                                                {{ item.question }}
                                            </span>
                                        </button>
                                    </h2>
                                    <div :id="`collapse-${index}`" class="accordion-collapse collapse"
                                        :aria-labelledby="`heading-${index}`" data-bs-parent="#accordionFaq">
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
                                <a href="/redimensionar-imagenes-online-gratis" class="link-primary">
                                    redimensionar imágenes online
                                </a>
                                y
                                <a href="/convertir-imagenes-gratis" class="link-primary">
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
