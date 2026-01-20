<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
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
const pageTitle = computed(() => seoData.value.title || 'Decodificar Base64 online gratis');
const pageDescription = computed(
    () => seoData.value.description || 'Convierte texto Base64 a formato legible sin salir del navegador.'
);
const { ogImageUrl } = useOgImage(seoData.value);

const base64Input = ref('U29wYSB1biBzdHJpbmcgZW4gQmFzZTY0IGFwcm9waWFkby4=');
const decodedText = computed(() => {
    if (!base64Input.value) return '';
    try {
        const raw = atob(base64Input.value);
        return decodeURIComponent(
            raw
                .split('')
                .map((char) => {
                    const code = char.charCodeAt(0);
                    return '%' + code.toString(16).padStart(2, '0');
                })
                .join('')
        );
    } catch (error) {
        return '';
    }
});

const copyFeedback = ref('');

async function copyDecoded() {
    if (!decodedText.value) return;
    try {
        await navigator.clipboard.writeText(decodedText.value);
        copyFeedback.value = 'Texto decodificado copiado';
        setTimeout(() => (copyFeedback.value = ''), 1800);
    } catch (error) {
        console.error(error);
        copyFeedback.value = 'Copia manual recomendada';
        setTimeout(() => (copyFeedback.value = ''), 1800);
    }
}

function clearFields() {
    base64Input.value = '';
    copyFeedback.value = '';
}
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
                    <div class="col-lg-10">
                        <p class="text-uppercase small mb-2 text-info fw-semibold">Base64</p>
                        <h1 class="display-5 fw-bold mb-3">{{ seoData.h1 }}</h1>
                        <p class="lead text-white-50 mb-3">
                            {{ pageDescription }}
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-info text-dark">Sin instalar nada</span>
                            <span class="badge bg-info text-dark">Listo para inspectores</span>
                            <span class="badge bg-info text-dark">Funciona offline</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="container py-5">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h2 class="h6 fw-semibold mb-1">Base64 de origen</h2>
                                    <p class="small text-muted mb-0">Pega aquí cualquier cadena codificada.</p>
                                </div>
                                <button type="button" class="btn btn-link btn-sm text-decoration-none" @click="clearFields">
                                    Limpiar
                                </button>
                            </div>
                            <textarea
                                class="form-control flex-grow-1 font-monospace border-0 bg-light p-3"
                                rows="9"
                                v-model="base64Input"
                                placeholder="Ingresa tu Base64 aquí..."
                            ></textarea>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100 d-flex flex-column">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h2 class="h6 fw-semibold mb-1">Texto decodificado</h2>
                                    <p class="small text-muted mb-0">Resultado en texto plano.</p>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" :disabled="!decodedText" @click="copyDecoded">
                                    Copiar
                                </button>
                            </div>
                            <textarea
                                class="form-control flex-grow-1 font-monospace border-0 bg-light p-3"
                                rows="9"
                                :value="decodedText"
                                readonly
                            ></textarea>
                            <div class="d-flex justify-content-between align-items-center mt-3 gap-2 flex-wrap">
                                <span class="small text-muted flex-grow-1">{{ copyFeedback }}</span>
                                <span class="badge bg-secondary-subtle text-dark">{{ decodedText ? 'Listo para pegar' : 'Ingresa Base64' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h3 class="h6 fw-bold mb-3">¿Cómo funciona?</h3>
                            <p class="small text-muted mb-2">
                                Base64 codifica bytes como texto seguro para máquinas. Aquí invertimos ese proceso y entregamos el texto original manteniendo la compatibilidad con UTF-8.
                            </p>
                            <p class="small text-muted mb-0">
                                Todo el trabajo ocurre en el navegador; no enviamos ni almacenamos los datos decodificados.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
