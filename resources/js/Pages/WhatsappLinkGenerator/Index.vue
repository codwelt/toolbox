<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref, onMounted, onBeforeUnmount, nextTick } from 'vue';
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

// --------- PAISES / CÓDIGOS ---------
const countries = [
    { code: 'CO', dialCode: '57', name: 'Colombia', flag: '🇨🇴' },
    { code: 'MX', dialCode: '52', name: 'México', flag: '🇲🇽' },
    { code: 'AR', dialCode: '54', name: 'Argentina', flag: '🇦🇷' },
    { code: 'CL', dialCode: '56', name: 'Chile', flag: '🇨🇱' },
    { code: 'PE', dialCode: '51', name: 'Perú', flag: '🇵🇪' },
    { code: 'EC', dialCode: '593', name: 'Ecuador', flag: '🇪🇨' },
    { code: 'VE', dialCode: '58', name: 'Venezuela', flag: '🇻🇪' },
    { code: 'PA', dialCode: '507', name: 'Panamá', flag: '🇵🇦' },
    { code: 'US', dialCode: '1', name: 'Estados Unidos', flag: '🇺🇸' },
    { code: 'ES', dialCode: '34', name: 'España', flag: '🇪🇸' },
    { code: 'BR', dialCode: '55', name: 'Brasil', flag: '🇧🇷' },
    { code: 'FR', dialCode: '33', name: 'Francia', flag: '🇫🇷' },
    { code: 'DE', dialCode: '49', name: 'Alemania', flag: '🇩🇪' },
    { code: 'IT', dialCode: '39', name: 'Italia', flag: '🇮🇹' },
];

const selectedDialCode = ref('57'); // por defecto Colombia
const phoneNumber = ref('');

// País actual
const currentCountry = computed(() => {
    return (
        countries.find((c) => c.dialCode === selectedDialCode.value) ||
        countries[0]
    );
});

// --------- MENSAJE / FORMATO ---------
const message = ref('');
const messageType = ref('comercial'); // comercial | consulta | soporte | otro
const messageArea = ref(null);

const quickSuggestions = computed(() => {
    switch (messageType.value) {
        case 'comercial':
            return [
                'Hola 👋, estoy interesado en uno de sus productos/servicios. ¿Podrían brindarme más información?',
                'Buen día 😊, vi su página web y me gustaría cotizar uno de sus servicios.',
                'Hola, quiero agendar una asesoría comercial. ¿Qué disponibilidad tienen esta semana?',
            ];
        case 'consulta':
            return [
                'Hola 👋, tengo una consulta puntual y me gustaría aclararla por este medio.',
                'Buen día, tengo dudas sobre uno de sus servicios. ¿Podrían ayudarme?',
                'Hola, ¿podrían brindarme más detalles sobre las condiciones y tiempos de respuesta?',
            ];
        case 'soporte':
            return [
                'Hola 👋, necesito soporte con un servicio que tengo activo con ustedes.',
                'Buen día, estoy presentando un inconveniente técnico y me gustaría recibir ayuda.',
                'Hola, ¿me pueden orientar para resolver un problema que estoy teniendo?',
            ];
        default:
            return [
                'Hola 👋, me gustaría recibir más información.',
                'Buen día, estoy interesado en sus servicios.',
            ];
    }
});

const formattingGuide = [
    { label: 'Negrilla', example: '*texto*' },
    { label: 'Cursiva', example: '_texto_' },
    { label: 'Tachado', example: '~texto~' },
    { label: 'Monoespacio', example: '```texto```' },
];

// --------- EMOJI PICKER ---------
const showEmojiPicker = ref(false);
const emojiSearch = ref('');

/**
 * Construimos la lista de emojis a partir de emojiCategories,
 * filtrando solo los que pertenecen al set "whatsapp".
 * Cada item tendrá al menos: char, name, keywords, categoryId.
 */
const emojis = computed(() => {
    const list = [];
    emojiCategories.forEach((category) => {
        (category.emojis || []).forEach((emoji) => {
            if (emoji.sets && emoji.sets.includes('whatsapp')) {
                list.push({
                    ...emoji,
                    categoryId: category.id,
                    categoryLabel: category.label,
                });
            }
        });
    });
    return list;
});

const filteredEmojis = computed(() => {
    const q = emojiSearch.value.toLowerCase().trim();
    if (!q) return emojis.value;
    return emojis.value.filter((e) => {
        const nameMatch = (e.name || '').toLowerCase().includes(q);
        const kwMatch = (e.keywords || []).some((k) =>
            k.toLowerCase().includes(q)
        );
        return nameMatch || kwMatch;
    });
});


// --------- FORMATO (BOTONES B I S CODE) ---------
function applyFormat(type) {
    const textarea = messageArea.value;
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = message.value || '';
    const selected = text.slice(start, end);

    let wrapperStart = '';
    let wrapperEnd = '';

    switch (type) {
        case 'bold':
            wrapperStart = wrapperEnd = '*';
            break;
        case 'italic':
            wrapperStart = wrapperEnd = '_';
            break;
        case 'strike':
            wrapperStart = wrapperEnd = '~';
            break;
        case 'mono':
            wrapperStart = wrapperEnd = '```';
            break;
    }

    let newText;
    let cursorStart;
    let cursorEnd;

    if (selected) {
        newText =
            text.slice(0, start) +
            wrapperStart +
            selected +
            wrapperEnd +
            text.slice(end);
        cursorStart = start;
        cursorEnd = end + wrapperStart.length + wrapperEnd.length;
    } else {
        newText =
            text.slice(0, start) +
            wrapperStart +
            wrapperEnd +
            text.slice(end);
        cursorStart = start + wrapperStart.length;
        cursorEnd = cursorStart;
    }

    message.value = newText;

    nextTick(() => {
        textarea.focus();
        textarea.setSelectionRange(cursorStart, cursorEnd);
    });
}

function insertEmoji(emoji) {
    const textarea = messageArea.value;
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = message.value || '';

    const newText = text.slice(0, start) + emoji.char + text.slice(end);
    const newPos = start + emoji.char.length;

    message.value = newText;

    nextTick(() => {
        textarea.focus();
        textarea.setSelectionRange(newPos, newPos);
    });
}

// --------- SUGERENCIAS ---------
function useSuggestion(s) {
    message.value = s;
}

// --------- LINKS DE WHATSAPP ---------
const sanitizedPhone = computed(() => {
    const digits = phoneNumber.value.replace(/\D/g, '');
    const cc = currentCountry.value.dialCode;
    if (!digits) return '';
    return cc + digits;
});

const encodedMessage = computed(() =>
    encodeURIComponent(message.value || '')
);

const waMeLink = computed(() => {
    if (!sanitizedPhone.value) return '';
    return `https://wa.me/${sanitizedPhone.value}${
        encodedMessage.value ? `?text=${encodedMessage.value}` : ''
    }`;
});

const apiWaLink = computed(() => {
    if (!sanitizedPhone.value) return '';
    return `https://api.whatsapp.com/send?phone=${sanitizedPhone.value}${
        encodedMessage.value ? `&text=${encodedMessage.value}` : ''
    }`;
});

const copyFeedback = ref('');

async function copyToClipboard(text) {
    if (!text) return;
    try {
        await navigator.clipboard.writeText(text);
        copyFeedback.value = 'Enlace copiado en el portapapeles ✅';
        setTimeout(() => (copyFeedback.value = ''), 2500);
    } catch (e) {
        console.error(e);
        copyFeedback.value =
            'No se pudo copiar el enlace. Copia manualmente el texto.';
        setTimeout(() => (copyFeedback.value = ''), 2500);
    }
}

// --------- AUTO-SELECT COUNTRY POR PAÍS ---------
onMounted(() => {
    try {
        const lang = navigator.language || navigator.userLanguage || '';
        const parts = lang.split('-');
        if (parts.length === 2) {
            const countryIso = parts[1].toUpperCase();
            const found = countries.find((c) => c.code === countryIso);
            if (found) {
                selectedDialCode.value = found.dialCode;
            }
        }
    } catch (e) {
        // si falla, dejamos el valor por defecto (Colombia)
    }
});

// --------- JSON-LD SEO ---------
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
        applicationCategory: 'CommunicationApplication',
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
                        Genera enlaces de WhatsApp personalizados con mensaje predefinido,
                        formatos de texto y emojis, listos para usar en tu página web.
                    </p>
                </div>
            </div>

            <!-- TARJETA PRINCIPAL -->
            <div class="row justify-content-center mb-4">
                <div class="col-lg-10">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4 p-md-5">
                            <div class="row g-4">
                                <!-- FORMULARIO IZQUIERDA -->
                                <div class="col-lg-5">
                                    <h2 class="h5 fw-bold mb-3">
                                        Configuración del enlace
                                    </h2>

                                    <!-- Código de país + Teléfono -->
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">
                                            Código de país
                                        </label>
                                        <div class="input-group input-group-sm mb-2">
                                            <button
                                                class="btn btn-outline-secondary dropdown-toggle w-100 text-start"
                                                type="button"
                                                data-bs-toggle="dropdown"
                                                aria-expanded="false"
                                            >
                                                <span class="me-2">
                                                    {{ currentCountry.flag }}
                                                </span>
                                                +{{ currentCountry.dialCode }}
                                                · {{ currentCountry.name }}
                                            </button>
                                            <ul
                                                class="dropdown-menu w-100"
                                                style="max-height: 260px; overflow-y: auto;"
                                            >
                                                <li
                                                    v-for="country in countries"
                                                    :key="country.code"
                                                >
                                                    <button
                                                        type="button"
                                                        class="dropdown-item"
                                                        @click="selectedDialCode = country.dialCode"
                                                    >
                                                        <span class="me-2">
                                                            {{ country.flag }}
                                                        </span>
                                                        +{{ country.dialCode }}
                                                        · {{ country.name }}
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>

                                        <label class="form-label small fw-semibold">
                                            Número de WhatsApp (solo números)
                                        </label>
                                        <input
                                            type="text"
                                            class="form-control form-control-sm"
                                            v-model="phoneNumber"
                                            placeholder="Ej: 3001234567"
                                        />
                                        <div class="form-text small">
                                            Se generará el enlace para:
                                            <code>
                                                +{{ currentCountry.dialCode }}
                                                {{ phoneNumber || 'tu número' }}
                                            </code>
                                        </div>
                                    </div>

                                    <!-- Tipo de mensaje -->
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">
                                            Tipo de mensaje rápido
                                        </label>
                                        <select
                                            class="form-select form-select-sm"
                                            v-model="messageType"
                                        >
                                            <option value="comercial">
                                                Comercial / Ventas
                                            </option>
                                            <option value="consulta">
                                                Consulta / Información
                                            </option>
                                            <option value="soporte">
                                                Soporte / Postventa
                                            </option>
                                            <option value="otro">
                                                Otro / Personalizado
                                            </option>
                                        </select>
                                        <div class="form-text small">
                                            Elige un tipo para usar plantillas rápidas
                                            como punto de partida.
                                        </div>
                                    </div>

                                    <!-- Sugerencias -->
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">
                                            Sugerencias de mensaje
                                        </label>
                                        <div class="d-grid gap-2">
                                            <button
                                                v-for="(s, index) in quickSuggestions"
                                                :key="index"
                                                type="button"
                                                class="btn btn-outline-secondary btn-sm text-start"
                                                @click="useSuggestion(s)"
                                            >
                                                {{ s }}
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Guía de formato -->
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">
                                            Formato compatible con WhatsApp
                                        </label>
                                        <ul class="small text-muted mb-0">
                                            <li
                                                v-for="item in formattingGuide"
                                                :key="item.label"
                                            >
                                                {{ item.label }}:
                                                <code>{{ item.example }}</code>
                                            </li>
                                            <li>
                                                También puedes usar emojis 😊👍🔥 y saltos de
                                                línea libremente.
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- MENSAJE + PREVIEW DERECHA -->
                                <div class="col-lg-7">
                                    <h2 class="h6 fw-bold mb-2">
                                        Mensaje personalizado
                                    </h2>

                                    <!-- Toolbar + Emoji -->
                                    <div class="mb-2 d-flex align-items-center gap-2 flex-wrap">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary"
                                                title="Negrilla (*texto*)"
                                                @click="applyFormat('bold')"
                                            >
                                                <strong>B</strong>
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary"
                                                title="Cursiva (_texto_)"
                                                @click="applyFormat('italic')"
                                            >
                                                <em>I</em>
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary"
                                                title="Tachado (~texto~)"
                                                @click="applyFormat('strike')"
                                            >
                                                <span style="text-decoration: line-through;">
                                                    S
                                                </span>
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary"
                                                title="Monoespacio (```texto```)"
                                                @click="applyFormat('mono')"
                                            >
                                                <code>&lt;/&gt;</code>
                                            </button>
                                        </div>

                                        <div class="position-relative">
                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary btn-sm"
                                                @click="showEmojiPicker = !showEmojiPicker"
                                            >
                                                😊 Emojis
                                            </button>

                                            <div
                                                v-if="showEmojiPicker"
                                                class="emoji-picker card shadow-sm p-2"
                                            >
                                                <div class="mb-2">
                                                    <input
                                                        type="text"
                                                        class="form-control form-control-sm"
                                                        v-model="emojiSearch"
                                                        placeholder="Buscar emoji (ej: feliz, amor, ok)"
                                                    />
                                                </div>
                                                <div class="emoji-grid">
                                                    <button
                                                        type="button"
                                                        class="btn btn-light btn-sm"
                                                        v-for="emoji in filteredEmojis"
                                                        :key="emoji.char + emoji.name"
                                                        @click="insertEmoji(emoji)"
                                                    >
                                                        {{ emoji.char }}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Textarea -->
                                    <div class="mb-2">
                                        <textarea
                                            ref="messageArea"
                                            class="form-control"
                                            rows="5"
                                            v-model="message"
                                            placeholder="Escribe aquí el mensaje que aparecerá prellenado cuando el usuario abra el chat de WhatsApp..."
                                        ></textarea>
                                        <div class="form-text small">
                                            El formato se aplicará cuando el usuario envíe el mensaje
                                            desde WhatsApp.
                                        </div>
                                    </div>

                                    <!-- Vista previa estilo WhatsApp -->
                                    <div class="mb-3">
                                        <h3 class="h6 fw-bold mb-2">
                                            Vista previa del mensaje
                                        </h3>
                                        <div class="whatsapp-preview-wrapper">
                                            <div class="whatsapp-preview-header">
                                                <span class="small text-white">
                                                    +{{ currentCountry.dialCode }} · WhatsApp
                                                </span>
                                            </div>
                                            <div class="whatsapp-preview-body">
                                                <div class="whatsapp-bubble">
                                                    <span v-if="message">
                                                        {{ message }}
                                                    </span>
                                                    <span v-else class="text-muted">
                                                        Aquí verás una simulación de cómo se verá tu
                                                        mensaje en la caja de texto de WhatsApp.
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="whatsapp-preview-input">
                                                <span class="text-muted small">
                                                    {{ message || 'Type a message' }}
                                                </span>
                                                <span class="ms-auto text-muted">
                                                    😊
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Enlaces generados -->
                                    <div class="mb-3">
                                        <h3 class="h6 fw-bold mb-2">
                                            Enlaces generados
                                        </h3>

                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold">
                                                Formato corto (wa.me)
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    :value="waMeLink"
                                                    readonly
                                                />
                                                <button
                                                    type="button"
                                                    class="btn btn-outline-primary"
                                                    :disabled="!waMeLink"
                                                    @click="copyToClipboard(waMeLink)"
                                                >
                                                    Copiar
                                                </button>
                                            </div>
                                        </div>

                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold">
                                                Formato clásico (api.whatsapp.com)
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    :value="apiWaLink"
                                                    readonly
                                                />
                                                <button
                                                    type="button"
                                                    class="btn btn-outline-primary"
                                                    :disabled="!apiWaLink"
                                                    @click="copyToClipboard(apiWaLink)"
                                                >
                                                    Copiar
                                                </button>
                                            </div>
                                        </div>

                                        <div
                                            v-if="copyFeedback"
                                            class="small mt-1"
                                            :class="{
                                                'text-success': copyFeedback.includes('copiado'),
                                                'text-danger': copyFeedback.includes('No se pudo'),
                                            }"
                                        >
                                            {{ copyFeedback }}
                                        </div>

                                        <div class="form-text small mt-2">
                                            Puedes pegar estos enlaces en cualquier botón o enlace HTML,
                                            por ejemplo:
                                            <code>
                                                &lt;a href="{{ waMeLink || '#'
                                                }}" target="_blank"&gt;Escríbenos por
                                                WhatsApp&lt;/a&gt;
                                            </code>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CONTENIDO SEO / FAQ -->
            <div class="row gy-4 mb-4">
                <div class="col-lg-10 mx-auto">
                    <section aria-labelledby="how-it-works">
                        <h2 id="how-it-works" class="h4 fw-bold mb-3">
                            ¿Cómo funciona el generador de links de WhatsApp?
                        </h2>
                        <p class="text-muted small mb-2">
                            La herramienta genera automáticamente la URL oficial de WhatsApp a
                            partir del código de país, el número de teléfono y el mensaje
                            personalizado que definas. El texto se codifica para que los emojis,
                            saltos de línea y formatos compatibles se vean correctamente.
                        </p>
                        <p class="text-muted small mb-0">
                            Solo tienes que copiar el enlace y usarlo en botones, banners o
                            enlaces de texto dentro de tu página web o tienda virtual para
                            recibir mensajes directos en tu WhatsApp.
                        </p>
                    </section>
                </div>
            </div>

            <div class="row gy-4 mb-5">
                <div class="col-lg-10 mx-auto">
                    <section aria-labelledby="faq">
                        <h2 id="faq" class="h4 fw-bold mb-3">
                            Preguntas frecuentes sobre enlaces de WhatsApp
                        </h2>

                        <div class="accordion" id="accordionFaqWhatsapp">
                            <div
                                class="accordion-item"
                                v-for="(item, index) in seo.faq"
                                :key="index"
                            >
                                <h2 class="accordion-header" :id="`heading-wa-${index}`">
                                    <button
                                        class="accordion-button collapsed"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        :data-bs-target="`#collapse-wa-${index}`"
                                        aria-expanded="false"
                                        :aria-controls="`collapse-wa-${index}`"
                                    >
                                        <span class="small fw-semibold">
                                            {{ item.question }}
                                        </span>
                                    </button>
                                </h2>
                                <div
                                    :id="`collapse-wa-${index}`"
                                    class="accordion-collapse collapse"
                                    :aria-labelledby="`heading-wa-${index}`"
                                    data-bs-parent="#accordionFaqWhatsapp"
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
                <div class="col-lg-10 mx-auto">
                    <section aria-label="otras-herramientas">
                        <p class="small text-muted">
                            En Toolbox Codwelt también encuentras herramientas gratuitas para
                            <a href="/comprimir-imagenes-online-gratis" class="link-primary">
                                comprimir imágenes
                            </a>,
                            <a href="/redimensionar-imagenes-online-gratis" class="link-primary">
                                redimensionar fotos
                            </a>
                            y
                            <a href="/quitar-fondo-imagen-gratis" class="link-primary">
                                quitar el fondo de tus imágenes
                            </a>
                            y optimizar toda tu presencia digital.
                        </p>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.whatsapp-preview-wrapper {
    border-radius: 20px;
    overflow: hidden;
    background-color: #e5ddd5;
    border: 1px solid #d4c9c0;
    max-width: 420px;
}

.whatsapp-preview-header {
    background-color: #075e54;
    padding: 6px 10px;
}

.whatsapp-preview-body {
    padding: 10px;
    min-height: 90px;
}

.whatsapp-bubble {
    display: inline-block;
    background-color: #ffffff;
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 0.85rem;
    max-width: 100%;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.whatsapp-preview-input {
    background-color: #f0f0f0;
    padding: 6px 10px;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
}

/* Emoji picker */
.emoji-picker {
    position: absolute;
    z-index: 20;
    top: 110%;
    left: 0;
    width: 260px;
}

.emoji-grid {
    max-height: 180px;
    overflow-y: auto;
    display: grid;
    grid-template-columns: repeat(8, minmax(0, 1fr));
    gap: 4px;
}

.emoji-grid button {
    padding: 2px 0;
}

/* Reutilizable */
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
