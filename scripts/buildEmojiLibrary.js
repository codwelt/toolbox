// scripts/buildEmojiLibrary.js
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

// Permite usar require dentro de este módulo ESM
const require = createRequire(import.meta.url);

// 👉 Cargamos el JSON principal (data-by-emoji.json)
const dataByEmoji = require('unicode-emoji-json');

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const allSets = ['whatsapp', 'browser'];

// Mapeo de NOMBRE DE GRUPO (Unicode) → id/label de tu librería
const groupMap = {
    'Smileys & Emotion': {
        id: 'smileys_emotion',
        label: 'Caritas y emociones',
    },
    'People & Body': {
        id: 'people_body',
        label: 'Personas y cuerpo',
    },
    'Animals & Nature': {
        id: 'animals_nature',
        label: 'Animales y naturaleza',
    },
    'Food & Drink': {
        id: 'food_drink',
        label: 'Comida y bebida',
    },
    'Travel & Places': {
        id: 'travel_places',
        label: 'Viajes y lugares',
    },
    'Activities': {
        id: 'activities',
        label: 'Actividades',
    },
    'Objects': {
        id: 'objects',
        label: 'Objetos',
    },
    'Symbols': {
        id: 'symbols',
        label: 'Símbolos',
    },
    'Flags': {
        id: 'flags',
        label: 'Banderas',
    },
};

// Stopwords para limpiar keywords en inglés
const EN_STOPWORDS = new Set([
    'face',
    'with',
    'and',
    'button',
    'symbol',
    'square',
    'circle',
    'flag',
    'of',
    'the',
    'in',
    'on',
    'a',
    'an',
]);

function getSpanishKeywordsForEmoji(meta, groupName) {
    const es = new Set();
    const name = (meta.name || '').toLowerCase();

    // Palabras por grupo
    switch (groupName) {
        case 'Smileys & Emotion':
            es.add('emoji');
            es.add('carita');
            es.add('emoción');
            es.add('sentimiento');
            break;
        case 'People & Body':
            es.add('persona');
            es.add('gente');
            es.add('cuerpo');
            break;
        case 'Animals & Nature':
            es.add('animales');
            es.add('naturaleza');
            es.add('animal');
            break;
        case 'Food & Drink':
            es.add('comida');
            es.add('bebida');
            es.add('alimento');
            break;
        case 'Travel & Places':
            es.add('viaje');
            es.add('lugar');
            es.add('destino');
            break;
        case 'Activities':
            es.add('actividad');
            es.add('deporte');
            es.add('ocio');
            break;
        case 'Objects':
            es.add('objeto');
            es.add('herramienta');
            break;
        case 'Symbols':
            es.add('símbolo');
            es.add('signo');
            break;
        case 'Flags':
            es.add('bandera');
            es.add('país');
            break;
    }

    // Palabras por nombre (muy básicas pero útiles)
    if (name.includes('smiling') || name.includes('grinning') || name.includes('beaming')) {
        es.add('sonrisa');
        es.add('feliz');
    }
    if (name.includes('cry') || name.includes('sad') || name.includes('frown')) {
        es.add('triste');
        es.add('llorando');
    }
    if (name.includes('laugh')) {
        es.add('risa');
        es.add('riendo');
    }
    if (name.includes('angry') || name.includes('pout')) {
        es.add('enojado');
        es.add('molesto');
    }
    if (name.includes('heart')) {
        es.add('corazon');
        es.add('amor');
    }
    if (name.includes('star')) {
        es.add('estrella');
    }
    if (name.includes('fire')) {
        es.add('fuego');
        es.add('caliente');
    }
    if (name.includes('cat')) {
        es.add('gato');
    }
    if (name.includes('dog')) {
        es.add('perro');
    }
    if (name.includes('house') || name.includes('home')) {
        es.add('casa');
        es.add('hogar');
    }
    if (name.includes('money') || name.includes('dollar') || name.includes('euro')) {
        es.add('dinero');
        es.add('pago');
    }
    if (name.includes('computer') || name.includes('laptop') || name.includes('desktop')) {
        es.add('computador');
        es.add('pc');
    }
    if (name.includes('phone') || name.includes('mobile') || name.includes('cell')) {
        es.add('telefono');
        es.add('celular');
    }

    return es;
}

// Aquí vamos guardando las categorías encontradas por nombre de grupo
const categoriesByGroupName = new Map();

// Recorremos TODOS los emojis del JSON
for (const [emojiChar, meta] of Object.entries(dataByEmoji)) {
    const groupName = meta.group;
    if (!groupName) continue;

    const config = groupMap[groupName];
    if (!config) continue; // ignoramos grupos que no usamos

    let category = categoriesByGroupName.get(groupName);
    if (!category) {
        category = {
            id: config.id,
            label: config.label,
            icon: emojiChar, // primer emoji del grupo como icono
            emojis: [],
        };
        categoriesByGroupName.set(groupName, category);
    }

    const name = (meta.name || '').toLowerCase();
    const slug = meta.slug || name.replace(/\s+/g, '_');

    // 👉 keywords EN: tokens de name + slug
    const baseEn = [];
    if (name) baseEn.push(...name.split(/\s+/g));
    if (slug) baseEn.push(...slug.split('_'));

    const englishTokens = baseEn
        .map((k) => k.trim())
        .filter((k) => k && !EN_STOPWORDS.has(k));

    // 👉 keywords ES: derivadas del nombre y el grupo
    const esSet = getSpanishKeywordsForEmoji(meta, groupName);

    // Mezclamos ambas, SIN duplicados
    const keywords = Array.from(new Set([...englishTokens, ...esSet])).slice(0, 12);

    category.emojis.push({
        char: emojiChar,
        name,                     // nombre en inglés en minúsculas
        shortcode: `:${slug}:`,   // estilo :smiling-face-with-hearts:
        keywords,                 // 🔥 mezcla ES + EN
        sets: allSets,
    });
}

// Construimos el array final en el orden de groupMap
const categories = Object.entries(groupMap).map(([groupName, config]) => {
    const existing = categoriesByGroupName.get(groupName);
    if (existing) return existing;

    // fallback si algo viene vacío
    return {
        id: config.id,
        label: config.label,
        icon: '❓',
        emojis: [],
    };
});

const output = `export const emojiSets = [
    { id: 'whatsapp', label: 'Estilo WhatsApp' },
    { id: 'browser', label: 'Estilo Navegador' },
];

export const emojiCategories = ${JSON.stringify(categories, null, 4)};
`;

const outPath = path.join(__dirname, '..', 'resources', 'js', 'emoji', 'emojiLibrary.generated.js');

// Aseguramos la carpeta
fs.mkdirSync(path.dirname(outPath), { recursive: true });

// Escribimos archivo final
fs.writeFileSync(outPath, output, 'utf8');
console.log('✅ emojiLibrary.generated.js creado en', outPath);
