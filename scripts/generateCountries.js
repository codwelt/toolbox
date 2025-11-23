// scripts/generateCountries.js

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

// __dirname emulado en ESM
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// 1. Cargar dataset base (JSON local)
const basePath = path.resolve(__dirname, 'data', 'base-countries.json');
const raw = fs.readFileSync(basePath, 'utf8');
const baseCountries = JSON.parse(raw);

// Función para convertir ISO2 a bandera emoji
function iso2ToFlag(iso2) {
    if (!iso2) return '';
    const code = iso2.toUpperCase();
    return [...code]
        .map((c) => String.fromCodePoint(0x1F1E6 + (c.charCodeAt(0) - 65)))
        .join('');
}

// 2. Mapa país -> continente (por ISO2)
// Puedes ir completando este mapa poco a poco.
// Los que no estén aquí quedarán como "Unknown".
const continentMap = {
    // América del Sur
    AR: 'South America',
    BO: 'South America',
    BR: 'South America',
    CL: 'South America',
    CO: 'South America',
    EC: 'South America',
    GY: 'South America',
    PY: 'South America',
    PE: 'South America',
    SR: 'South America',
    UY: 'South America',
    VE: 'South America',

    // América del Norte y Central
    CA: 'North America',
    US: 'North America',
    MX: 'North America',
    CR: 'North America',
    SV: 'North America',
    GT: 'North America',
    HN: 'North America',
    NI: 'North America',
    PA: 'North America',
    DO: 'North America',
    HT: 'North America',
    CU: 'North America',

    // Europa (ejemplos, puedes extender)
    ES: 'Europe',
    PT: 'Europe',
    FR: 'Europe',
    DE: 'Europe',
    IT: 'Europe',
    GB: 'Europe',
    IE: 'Europe',
    NL: 'Europe',
    BE: 'Europe',
    LU: 'Europe',
    CH: 'Europe',
    SE: 'Europe',
    NO: 'Europe',
    DK: 'Europe',
    FI: 'Europe',
    PL: 'Europe',
    CZ: 'Europe',
    AT: 'Europe',
    GR: 'Europe',

    // Asia (ejemplos)
    CN: 'Asia',
    JP: 'Asia',
    KR: 'Asia',
    KP: 'Asia',
    IN: 'Asia',
    ID: 'Asia',
    SG: 'Asia',
    TH: 'Asia',
    MY: 'Asia',
    PH: 'Asia',
    SA: 'Asia',
    AE: 'Asia',
    QA: 'Asia',
    TR: 'Asia',

    // África (ejemplos)
    ZA: 'Africa',
    NG: 'Africa',
    EG: 'Africa',
    MA: 'Africa',
    DZ: 'Africa',
    KE: 'Africa',
    ET: 'Africa',
    GH: 'Africa',
    TZ: 'Africa',
    SN: 'Africa',

    // Oceanía (ejemplos)
    AU: 'Oceania',
    NZ: 'Oceania',
    FJ: 'Oceania',
    PG: 'Oceania'
    // 👉 Puedes seguir completando el resto si quieres tener todos 100% mapeados
};

// 3. Transformar al formato que va a usar Vue
const transformed = baseCountries.map((c) => {
    const iso2 = (c.iso2 || '').toUpperCase();
    const iso3 = (c.iso3 || '').toUpperCase();

    return {
        nameEn: c.nameEN || c.nameEn || c.name || '',
        nameEs: c.nameES || c.nameEs || c.name || '',
        iso2,
        iso3,
        dialCode: c.phoneCode ? '+' + c.phoneCode.replace(/\s+/g, '') : '',
        currencyCode: c.currencyCode || '',
        currencyName: c.currencyName || '',
        flag: iso2ToFlag(iso2),
        continent: continentMap[iso2] || 'Unknown'
    };
});

// 4. Generar archivo JS final para tu frontend
const output =
    '// Generated file. Do not edit manually.\n' +
    'export const countries = ' +
    JSON.stringify(transformed, null, 4) +
    ';\n';

const outPath = path.resolve(
    __dirname,
    '..',
    'resources',
    'js',
    'countries',
    'countries.generated.js'
);

// nos aseguramos de que el directorio exista
const outDir = path.dirname(outPath);
if (!fs.existsSync(outDir)) {
    fs.mkdirSync(outDir, { recursive: true });
}

fs.writeFileSync(outPath, output, 'utf8');

console.log(`✅ Countries file generated at: ${outPath}`);
