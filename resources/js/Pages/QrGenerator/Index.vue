<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppLayout from './../../Layouts/AppLayout.vue';
import { useOgImage } from '@/composables/useOgImage';

/*
 * Generador QR ligero basado en qrcode-generator (MIT, Kazuhiko Arase)
 * https://github.com/kazuhikoarase/qrcode-generator
 */
/* eslint-disable */
const QRCode = (function () {
    const QRErrorCorrectionLevel = { L: 1, M: 0, Q: 3, H: 2 };
    const QRMaskPattern = { PATTERN000: 0, PATTERN001: 1, PATTERN010: 2, PATTERN011: 3, PATTERN100: 4, PATTERN101: 5, PATTERN110: 6, PATTERN111: 7 };
    const QRUtil = {
        PATTERN_POSITION_TABLE: [
            [],
            [6, 18],
            [6, 22],
            [6, 26],
            [6, 30],
            [6, 34],
            [6, 22, 38],
            [6, 24, 42],
            [6, 26, 46],
            [6, 28, 50],
            [6, 30, 54],
            [6, 32, 58],
            [6, 34, 62],
            [6, 26, 46, 66],
            [6, 26, 48, 70],
            [6, 26, 50, 74],
            [6, 30, 54, 78],
            [6, 30, 56, 82],
            [6, 30, 58, 86],
            [6, 34, 62, 90],
        ],
        G15: (1 << 10) | (1 << 8) | (1 << 5) | (1 << 4) | (1 << 2) | (1 << 1) | 1,
        G18: (1 << 12) | (1 << 11) | (1 << 10) | (1 << 9) | (1 << 8) | (1 << 5) | (1 << 2) | 1,
        G15_MASK: (1 << 14) | (1 << 12) | (1 << 10) | (1 << 4) | (1 << 1),
        getBCHTypeInfo(data) {
            let d = data << 10;
            while (QRUtil.getBCHDigit(d) - QRUtil.getBCHDigit(QRUtil.G15) >= 0) d ^= QRUtil.G15 << (QRUtil.getBCHDigit(d) - QRUtil.getBCHDigit(QRUtil.G15));
            return ((data << 10) | d) ^ QRUtil.G15_MASK;
        },
        getBCHTypeNumber(data) {
            let d = data << 12;
            while (QRUtil.getBCHDigit(d) - QRUtil.getBCHDigit(QRUtil.G18) >= 0) d ^= QRUtil.G18 << (QRUtil.getBCHDigit(d) - QRUtil.getBCHDigit(QRUtil.G18));
            return (data << 12) | d;
        },
        getBCHDigit(data) {
            let digit = 0;
            while (data !== 0) {
                digit++;
                data >>>= 1;
            }
            return digit;
        },
        getPatternPosition(typeNumber) {
            return QRUtil.PATTERN_POSITION_TABLE[typeNumber - 1] || [];
        },
        getMask(maskPattern, i, j) {
            switch (maskPattern) {
                case QRMaskPattern.PATTERN000:
                    return (i + j) % 2 === 0;
                case QRMaskPattern.PATTERN001:
                    return i % 2 === 0;
                case QRMaskPattern.PATTERN010:
                    return j % 3 === 0;
                case QRMaskPattern.PATTERN011:
                    return (i + j) % 3 === 0;
                case QRMaskPattern.PATTERN100:
                    return (Math.floor(i / 2) + Math.floor(j / 3)) % 2 === 0;
                case QRMaskPattern.PATTERN101:
                    return ((i * j) % 2) + ((i * j) % 3) === 0;
                case QRMaskPattern.PATTERN110:
                    return (((i * j) % 2) + ((i * j) % 3)) % 2 === 0;
                case QRMaskPattern.PATTERN111:
                    return (((i + j) % 2) + ((i * j) % 3)) % 2 === 0;
                default:
                    return false;
            }
        },
    };
    const QRMath = (function () {
        const EXP_TABLE = new Array(256);
        const LOG_TABLE = new Array(256);
        for (let i = 0; i < 8; i++) EXP_TABLE[i] = 1 << i;
        for (let i = 8; i < 256; i++) EXP_TABLE[i] = EXP_TABLE[i - 4] ^ EXP_TABLE[i - 5] ^ EXP_TABLE[i - 6] ^ EXP_TABLE[i - 8];
        for (let i = 0; i < 255; i++) LOG_TABLE[EXP_TABLE[i]] = i;
        return {
            glog(n) {
                if (n < 1) throw new Error(`glog(${n})`);
                return LOG_TABLE[n];
            },
            gexp(n) {
                while (n < 0) n += 255;
                while (n >= 256) n -= 255;
                return EXP_TABLE[n];
            },
        };
    })();

    function QRPolynomial(num, shift) {
        let offset = 0;
        while (offset < num.length && num[offset] === 0) offset++;
        this.num = new Array(num.length - offset + shift);
        for (let i = 0; i < num.length - offset; i++) this.num[i] = num[i + offset];
    }
    QRPolynomial.prototype = {
        get(index) {
            return this.num[index];
        },
        getLength() {
            return this.num.length;
        },
        multiply(e) {
            const num = new Array(this.getLength() + e.getLength() - 1);
            for (let i = 0; i < this.getLength(); i++) {
                for (let j = 0; j < e.getLength(); j++) {
                    num[i + j] = (num[i + j] || 0) ^ QRMath.gexp(QRMath.glog(this.get(i)) + QRMath.glog(e.get(j)));
                }
            }
            return new QRPolynomial(num, 0);
        },
        mod(e) {
            if (this.getLength() - e.getLength() < 0) return this;
            const ratio = QRMath.glog(this.get(0)) - QRMath.glog(e.get(0));
            const num = new Array(this.getLength());
            for (let i = 0; i < this.getLength(); i++) num[i] = this.get(i);
            for (let i = 0; i < e.getLength(); i++) num[i] ^= QRMath.gexp(QRMath.glog(e.get(i)) + ratio);
            return new QRPolynomial(num, 0).mod(e);
        },
    };

    function QRRSBlock(totalCount, dataCount) {
        this.totalCount = totalCount;
        this.dataCount = dataCount;
    }
    QRRSBlock.getRSBlocks = function (typeNumber, errorCorrectionLevel) {
        const table = RS_BLOCK_TABLE[(typeNumber - 1) * 4 + errorCorrectionLevel];
        if (!table) throw new Error('RS block not found');
        const list = [];
        for (let i = 0; i < table.length / 3; i++) {
            const count = table[i * 3];
            const totalCount = table[i * 3 + 1];
            const dataCount = table[i * 3 + 2];
            for (let j = 0; j < count; j++) list.push(new QRRSBlock(totalCount, dataCount));
        }
        return list;
    };

    function QRBitBuffer() {
        this.buffer = [];
        this.length = 0;
    }
    QRBitBuffer.prototype = {
        put(num, length) {
            for (let i = 0; i < length; i++) this.putBit(((num >>> (length - i - 1)) & 1) === 1);
        },
        getLengthInBits() {
            return this.length;
        },
        putBit(bit) {
            const bufIndex = Math.floor(this.length / 8);
            if (this.buffer.length <= bufIndex) this.buffer.push(0);
            if (bit) this.buffer[bufIndex] |= 0x80 >>> (this.length % 8);
            this.length++;
        },
    };

    function QR8BitByte(data) {
        this.mode = 4;
        this.data = data;
    }
    QR8BitByte.prototype = {
        getLength() {
            return this.data.length;
        },
        get(index) {
            return this.data.charCodeAt(index);
        },
    };

    const RS_BLOCK_TABLE = [
        [1, 26, 19, 1, 26, 16, 1, 26, 13, 1, 26, 9],
        [1, 44, 34, 1, 44, 28, 1, 44, 22, 1, 44, 16],
        [1, 70, 55, 1, 70, 44, 2, 35, 17, 2, 35, 13],
        [1, 100, 80, 2, 50, 32, 2, 50, 24, 4, 25, 9],
        [1, 134, 108, 2, 67, 43, 2, 33, 15, 2, 34, 16, 2, 33, 11, 2, 34, 12],
        [2, 86, 68, 4, 43, 27, 4, 43, 19, 4, 43, 15],
        [2, 98, 78, 4, 49, 31, 2, 32, 14, 4, 33, 15, 4, 39, 13, 1, 40, 14],
        [2, 121, 97, 2, 60, 38, 2, 61, 39, 4, 40, 18, 2, 41, 19, 4, 40, 14, 2, 41, 15],
        [2, 146, 116, 3, 58, 36, 2, 59, 37, 4, 36, 16, 4, 37, 17, 4, 36, 12, 4, 37, 13],
        [2, 86, 68, 2, 87, 69, 4, 69, 43, 1, 70, 44, 6, 43, 19, 2, 44, 20, 6, 43, 15, 2, 44, 16],
    ];

    function QRCodeModel(typeNumber, errorCorrectionLevel) {
        this.typeNumber = typeNumber;
        this.errorCorrectionLevel = errorCorrectionLevel;
        this.modules = null;
        this.moduleCount = 0;
        this.dataCache = null;
        this.dataList = [];
    }

    QRCodeModel.prototype = {
        addData(data) {
            this.dataList.push(new QR8BitByte(data));
            this.dataCache = null;
        },
        isDark(row, col) {
            return this.modules[row][col];
        },
        getModuleCount() {
            return this.moduleCount;
        },
        make() {
            this.typeNumber = this.getMinimumTypeNumber();
            this.makeImpl(false, this.getBestMaskPattern());
        },
        getMinimumTypeNumber() {
            for (let type = 1; type <= 10; type++) {
                const rsBlocks = QRRSBlock.getRSBlocks(type, this.errorCorrectionLevel);
                const totalDataCount = rsBlocks.reduce((acc, b) => acc + b.dataCount, 0);
                let length = 0;
                this.dataList.forEach((d) => {
                    length += 4 + 8 + d.getLength() * 8;
                });
                if (length <= totalDataCount * 8) return type;
            }
            return 10;
        },
        makeImpl(test, maskPattern) {
            this.moduleCount = this.typeNumber * 4 + 17;
            this.modules = Array.from({ length: this.moduleCount }, () => Array(this.moduleCount).fill(null));
            this.setupPositionProbePattern(0, 0);
            this.setupPositionProbePattern(this.moduleCount - 7, 0);
            this.setupPositionProbePattern(0, this.moduleCount - 7);
            this.setupPositionAdjustPattern();
            this.setupTimingPattern();
            this.setupTypeInfo(test, maskPattern);
            if (this.typeNumber >= 7) this.setupTypeNumber(test);
            if (!this.dataCache) this.dataCache = QRCodeModel.createData(this.typeNumber, this.errorCorrectionLevel, this.dataList);
            this.mapData(this.dataCache, maskPattern);
        },
        setupPositionProbePattern(row, col) {
            for (let r = -1; r <= 7; r++) {
                if (row + r <= -1 || this.moduleCount <= row + r) continue;
                for (let c = -1; c <= 7; c++) {
                    if (col + c <= -1 || this.moduleCount <= col + c) continue;
                    if (0 <= r && r <= 6 && (c === 0 || c === 6)) this.modules[row + r][col + c] = true;
                    else if (0 <= c && c <= 6 && (r === 0 || r === 6)) this.modules[row + r][col + c] = true;
                    else if (2 <= r && r <= 4 && 2 <= c && c <= 4) this.modules[row + r][col + c] = true;
                    else this.modules[row + r][col + c] = false;
                }
            }
        },
        getBestMaskPattern() {
            let minLost = 0;
            let pattern = 0;
            for (let i = 0; i < 8; i++) {
                this.makeImpl(true, i);
                const lost = QRUtilGetLostPoint(this);
                if (i === 0 || minLost > lost) {
                    minLost = lost;
                    pattern = i;
                }
            }
            return pattern;
        },
        setupTimingPattern() {
            for (let r = 8; r < this.moduleCount - 8; r++) if (this.modules[r][6] === null) this.modules[r][6] = r % 2 === 0;
            for (let c = 8; c < this.moduleCount - 8; c++) if (this.modules[6][c] === null) this.modules[6][c] = c % 2 === 0;
        },
        setupPositionAdjustPattern() {
            const pos = QRUtil.getPatternPosition(this.typeNumber) || [];
            for (let i = 0; i < pos.length; i++) {
                for (let j = 0; j < pos.length; j++) {
                    const row = pos[i];
                    const col = pos[j];
                    if (this.modules[row][col] !== null) continue;
                    for (let r = -2; r <= 2; r++) {
                        for (let c = -2; c <= 2; c++) {
                            if (r === -2 || r === 2 || c === -2 || c === 2 || (r === 0 && c === 0)) this.modules[row + r][col + c] = true;
                            else this.modules[row + r][col + c] = false;
                        }
                    }
                }
            }
        },
        setupTypeNumber(test) {
            const bits = QRUtil.getBCHTypeNumber(this.typeNumber);
            for (let i = 0; i < 18; i++) {
                const mod = !test && ((bits >> i) & 1) === 1;
                this.modules[Math.floor(i / 3)][(i % 3) + this.moduleCount - 8 - 3] = mod;
                this.modules[(i % 3) + this.moduleCount - 8 - 3][Math.floor(i / 3)] = mod;
            }
        },
        setupTypeInfo(test, maskPattern) {
            const data = (this.errorCorrectionLevel << 3) | maskPattern;
            const bits = QRUtil.getBCHTypeInfo(data);
            for (let i = 0; i < 15; i++) {
                const mod = !test && ((bits >> i) & 1) === 1;
                if (i < 6) this.modules[i][8] = mod;
                else if (i < 8) this.modules[i + 1][8] = mod;
                else this.modules[this.moduleCount - 15 + i][8] = mod;
            }
            for (let i = 0; i < 15; i++) {
                const mod = !test && ((bits >> i) & 1) === 1;
                if (i < 8) this.modules[8][this.moduleCount - i - 1] = mod;
                else if (i < 9) this.modules[8][15 - i - 1 + 1] = mod;
                else this.modules[8][15 - i - 1] = mod;
            }
            this.modules[this.moduleCount - 8][8] = !test;
        },
        mapData(data, maskPattern) {
            let inc = -1;
            let row = this.moduleCount - 1;
            let bitIndex = 7;
            let byteIndex = 0;
            for (let col = this.moduleCount - 1; col > 0; col -= 2) {
                if (col === 6) col--;
                while (true) {
                    for (let c = 0; c < 2; c++) {
                        if (this.modules[row][col - c] === null) {
                            let dark = false;
                            if (byteIndex < data.length) dark = ((data[byteIndex] >>> bitIndex) & 1) === 1;
                            if (QRUtil.getMask(maskPattern, row, col - c)) dark = !dark;
                            this.modules[row][col - c] = dark;
                            bitIndex--;
                            if (bitIndex === -1) {
                                byteIndex++;
                                bitIndex = 7;
                            }
                        }
                    }
                    row += inc;
                    if (row < 0 || this.moduleCount <= row) {
                        row -= inc;
                        inc = -inc;
                        break;
                    }
                }
            }
        },
    };

    function QRUtilGetLostPoint(qrCode) {
        const moduleCount = qrCode.getModuleCount();
        let lostPoint = 0;
        for (let row = 0; row < moduleCount; row++) {
            for (let col = 0; col < moduleCount; col++) {
                let sameCount = 0;
                const dark = qrCode.isDark(row, col);
                for (let r = -1; r <= 1; r++) {
                    if (row + r < 0 || moduleCount <= row + r) continue;
                    for (let c = -1; c <= 1; c++) {
                        if (col + c < 0 || moduleCount <= col + c) continue;
                        if (r === 0 && c === 0) continue;
                        if (dark === qrCode.isDark(row + r, col + c)) sameCount++;
                    }
                }
                if (sameCount > 5) lostPoint += 3 + sameCount - 5;
            }
        }
        for (let row = 0; row < moduleCount - 1; row++) {
            for (let col = 0; col < moduleCount - 1; col++) {
                let count = 0;
                if (qrCode.isDark(row, col)) count++;
                if (qrCode.isDark(row + 1, col)) count++;
                if (qrCode.isDark(row, col + 1)) count++;
                if (qrCode.isDark(row + 1, col + 1)) count++;
                if (count === 0 || count === 4) lostPoint += 3;
            }
        }
        for (let row = 0; row < moduleCount; row++) {
            for (let col = 0; col < moduleCount - 6; col++) {
                if (qrCode.isDark(row, col) && !qrCode.isDark(row, col + 1) && qrCode.isDark(row, col + 2) && qrCode.isDark(row, col + 3) && qrCode.isDark(row, col + 4) && !qrCode.isDark(row, col + 5) && qrCode.isDark(row, col + 6)) lostPoint += 40;
            }
        }
        for (let col = 0; col < moduleCount; col++) {
            for (let row = 0; row < moduleCount - 6; row++) {
                if (qrCode.isDark(row, col) && !qrCode.isDark(row + 1, col) && qrCode.isDark(row + 2, col) && qrCode.isDark(row + 3, col) && qrCode.isDark(row + 4, col) && !qrCode.isDark(row + 5, col) && qrCode.isDark(row + 6, col)) lostPoint += 40;
            }
        }
        let darkCount = 0;
        for (let col = 0; col < moduleCount; col++) {
            for (let row = 0; row < moduleCount; row++) {
                if (qrCode.isDark(row, col)) darkCount++;
            }
        }
        const ratio = Math.abs((100 * darkCount) / moduleCount / moduleCount - 50) / 5;
        lostPoint += ratio * 10;
        return lostPoint;
    }

    QRCodeModel.createData = function (typeNumber, errorCorrectionLevel, dataList) {
        const rsBlocks = QRRSBlock.getRSBlocks(typeNumber, errorCorrectionLevel);
        const buffer = new QRBitBuffer();
        dataList.forEach((d) => {
            buffer.put(4, 4);
            buffer.put(d.getLength(), 8);
            for (let i = 0; i < d.getLength(); i++) buffer.put(d.get(i), 8);
        });
        let totalDataCount = 0;
        rsBlocks.forEach((b) => (totalDataCount += b.dataCount));
        while (buffer.getLengthInBits() + 4 <= totalDataCount * 8) buffer.put(0, 4);
        while (buffer.getLengthInBits() % 8 !== 0) buffer.putBit(false);
        while (buffer.getLengthInBits() < totalDataCount * 8) {
            buffer.put(0xec, 8);
            if (buffer.getLengthInBits() >= totalDataCount * 8) break;
            buffer.put(0x11, 8);
        }
        return QRCodeModel.createBytes(buffer, rsBlocks);
    };

    QRCodeModel.createBytes = function (buffer, rsBlocks) {
        let offset = 0;
        let maxDcCount = 0;
        let maxEcCount = 0;
        const dcdata = new Array(rsBlocks.length);
        const ecdata = new Array(rsBlocks.length);
        for (let r = 0; r < rsBlocks.length; r++) {
            const dcCount = rsBlocks[r].dataCount;
            const ecCount = rsBlocks[r].totalCount - dcCount;
            maxDcCount = Math.max(maxDcCount, dcCount);
            maxEcCount = Math.max(maxEcCount, ecCount);
            dcdata[r] = new Array(dcCount);
            for (let i = 0; i < dcCount; i++) dcdata[r][i] = 0xff & buffer.buffer[i + offset];
            offset += dcCount;
            const rsPoly = getErrorCorrectPolynomial(ecCount);
            const rawPoly = new QRPolynomial(dcdata[r], rsPoly.getLength() - 1);
            const modPoly = rawPoly.mod(rsPoly);
            ecdata[r] = new Array(rsPoly.getLength() - 1);
            for (let i = 0; i < ecdata[r].length; i++) {
                const modIndex = i + modPoly.getLength() - ecdata[r].length;
                ecdata[r][i] = modIndex >= 0 ? modPoly.get(modIndex) : 0;
            }
        }
        const totalCodeCount = rsBlocks.reduce((sum, b) => sum + b.totalCount, 0);
        const data = new Array(totalCodeCount);
        let index = 0;
        for (let i = 0; i < maxDcCount; i++) {
            for (let r = 0; r < rsBlocks.length; r++) if (i < dcdata[r].length) data[index++] = dcdata[r][i];
        }
        for (let i = 0; i < maxEcCount; i++) {
            for (let r = 0; r < rsBlocks.length; r++) if (i < ecdata[r].length) data[index++] = ecdata[r][i];
        }
        return data;
    };

    function getErrorCorrectPolynomial(length) {
        let a = new QRPolynomial([1], 0);
        for (let i = 0; i < length; i++) a = a.multiply(new QRPolynomial([1, QRMath.gexp(i)], 0));
        return a;
    }

    QRCodeModel.create = function (typeNumber, errorCorrectionLevel) {
        return new QRCodeModel(typeNumber, errorCorrectionLevel);
    };
    QRCodeModel.QRErrorCorrectionLevel = QRErrorCorrectionLevel;
    return QRCodeModel;
})();
/* eslint-enable */

const props = defineProps({ seo: { type: Object, required: true } });

defineOptions({ layout: AppLayout });

const seoData = computed(() => props.seo || {});
const pageTitle = computed(() => seoData.value.title || 'Generador de código QR online');
const pageDescription = computed(
    () =>
        seoData.value.description ||
        'Crea códigos QR personalizados con colores, degradados, logo y marcos para múltiples tipos de contenido.'
);
const { ogImageUrl } = useOgImage(seoData.value);

const form = ref({
    type: 'url',
    data: {
        url: 'https://toolsbox.codwelt.com',
        text: 'Texto de ejemplo',
        email: { to: 'hola@ejemplo.com', subject: 'Hola', body: 'Mensaje desde mi QR' },
        location: { query: 'Bogotá, Colombia', lat: '', lng: '' },
        phone: '+573001112233',
        sms: { number: '+573001112233', message: 'Hola desde SMS QR' },
        whatsapp: { number: '+573001112233', message: 'Hola desde WhatsApp QR' },
        zoom: { url: 'https://zoom.us/j/123456789' },
        wifi: { ssid: 'MiWiFi', password: 'contraseña', security: 'WPA', hidden: false },
        vcard: {
            firstName: 'Ada',
            lastName: 'Lovelace',
            org: 'Analytical Engines',
            title: 'Matemática',
            phone: '+573001112233',
            email: 'ada@example.com',
            url: 'https://example.com',
            address: 'Londres, Reino Unido',
            note: 'Pionera de la computación',
        },
        event: {
            title: 'Demo Toolsbox',
            location: 'Online',
            description: 'Presentación de herramientas',
            start: '2025-01-10T10:00',
            end: '2025-01-10T11:00',
        },
    },
    size: 360,
    margin: 4,
    errorCorrection: 'M',
    darkColor: '#0b2239',
    lightColor: '#ffffff',
    useGradient: true,
    gradientType: 'linear',
    gradientStart: '#0b2239',
    gradientEnd: '#00bcd4',
    backgroundColor: '#ffffff',
    frame: false,
    frameColor: '#ffffff',
    framePadding: 16,
    logoUrl: '',
});

const canvasRef = ref(null);
const downloadUrl = ref('');
const copied = ref(false);
const jsonLdScriptEl = ref(null);

const faqStructured = computed(() => {
    const items = seoData.value.faq || [];
    return items.map((item) => ({
        '@type': 'Question',
        name: item.question,
        acceptedAnswer: { '@type': 'Answer', text: item.answer },
    }));
});

const jsonLd = computed(() =>
    JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'WebApplication',
        name: pageTitle.value,
        url: seoData.value.canonical,
        applicationCategory: 'Utility',
        description: pageDescription.value,
        offers: { '@type': 'Offer', price: '0', priceCurrency: 'USD' },
        potentialAction: { '@type': 'UseAction', target: seoData.value.canonical },
        mainEntity: { '@type': 'FAQPage', mainEntity: faqStructured.value },
    })
);

const errorCorrectionOptions = [
    { value: 'L', label: 'L (baja, más datos)' },
    { value: 'M', label: 'M (media)' },
    { value: 'Q', label: 'Q (alta)' },
    { value: 'H', label: 'H (máxima precisión)' },
];

function buildDataString() {
    const t = form.value.type;
    const d = form.value.data;
    switch (t) {
        case 'url':
            return d.url || '';
        case 'text':
            return d.text || '';
        case 'email':
            return `MATMSG:TO:${d.email.to};SUB:${d.email.subject};BODY:${d.email.body};;`;
        case 'location':
            if (d.location.lat && d.location.lng) return `geo:${d.location.lat},${d.location.lng}`;
            return d.location.query ? `geo:0,0?q=${encodeURIComponent(d.location.query)}` : '';
        case 'phone':
            return d.phone ? `tel:${d.phone}` : '';
        case 'sms':
            return d.sms.number ? `SMSTO:${d.sms.number}:${d.sms.message || ''}` : '';
        case 'whatsapp':
            return d.whatsapp.number ? `https://wa.me/${d.whatsapp.number.replace(/\D/g, '')}?text=${encodeURIComponent(d.whatsapp.message || '')}` : '';
        case 'zoom':
            return d.zoom.url || '';
        case 'wifi':
            return `WIFI:T:${d.wifi.security || 'WPA'};S:${d.wifi.ssid};P:${d.wifi.password};${d.wifi.hidden ? 'H:true;' : ''};`;
        case 'vcard':
            return `BEGIN:VCARD\nVERSION:3.0\nN:${d.vcard.lastName};${d.vcard.firstName};;;\nFN:${d.vcard.firstName} ${d.vcard.lastName}\nORG:${d.vcard.org}\nTITLE:${d.vcard.title}\nTEL;TYPE=CELL:${d.vcard.phone}\nEMAIL:${d.vcard.email}\nURL:${d.vcard.url}\nADR;TYPE=HOME:${d.vcard.address}\nNOTE:${d.vcard.note}\nEND:VCARD`;
        case 'event':
            return `BEGIN:VEVENT\nSUMMARY:${d.event.title}\nLOCATION:${d.event.location}\nDESCRIPTION:${d.event.description}\nDTSTART:${formatDateICS(d.event.start)}\nDTEND:${formatDateICS(d.event.end)}\nEND:VEVENT`;
        default:
            return '';
    }
}

function formatDateICS(value) {
    if (!value) return '';
    const date = new Date(value);
    const pad = (n) => `${n}`.padStart(2, '0');
    return `${date.getUTCFullYear()}${pad(date.getUTCMonth() + 1)}${pad(date.getUTCDate())}T${pad(date.getUTCHours())}${pad(date.getUTCMinutes())}${pad(date.getUTCSeconds())}Z`;
}

function generateQr() {
    const data = buildDataString();
    if (!data) return;
    const qr = QRCode.create(1, QRCode.QRErrorCorrectionLevel[form.value.errorCorrection]);
    qr.addData(data);
    qr.make();
    drawQr(qr);
}

function drawQr(qr) {
    const size = Math.max(180, Number(form.value.size) || 320);
    const margin = Math.max(0, Number(form.value.margin) || 0);
    const moduleCount = qr.getModuleCount();
    const moduleSize = size / (moduleCount + margin * 2);
    const framePadding = form.value.frame ? Math.max(6, Number(form.value.framePadding) || 0) : 0;
    const totalSize = size + framePadding * 2;

    const canvas = canvasRef.value;
    if (!canvas) return;
    canvas.width = totalSize;
    canvas.height = totalSize;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    ctx.fillStyle = form.value.backgroundColor || '#ffffff';
    ctx.fillRect(0, 0, totalSize, totalSize);

    if (form.value.frame) {
        ctx.strokeStyle = form.value.frameColor || '#ffffff';
        ctx.lineWidth = framePadding;
        ctx.strokeRect(framePadding / 2, framePadding / 2, totalSize - framePadding, totalSize - framePadding);
    }

    const offset = framePadding + margin * moduleSize;
    let gradient = null;
    if (form.value.useGradient) {
        gradient =
            form.value.gradientType === 'radial'
                ? ctx.createRadialGradient(totalSize / 2, totalSize / 2, totalSize * 0.1, totalSize / 2, totalSize / 2, totalSize * 0.6)
                : ctx.createLinearGradient(0, 0, totalSize, totalSize);
        gradient.addColorStop(0, form.value.gradientStart || '#000000');
        gradient.addColorStop(1, form.value.gradientEnd || '#000000');
    }

    for (let r = 0; r < moduleCount; r++) {
        for (let c = 0; c < moduleCount; c++) {
            const isDark = qr.isDark(r, c);
            ctx.fillStyle = isDark ? gradient || form.value.darkColor || '#000000' : form.value.lightColor || '#ffffff';
            const x = offset + c * moduleSize;
            const y = offset + r * moduleSize;
            ctx.fillRect(Math.round(x), Math.round(y), Math.ceil(moduleSize), Math.ceil(moduleSize));
        }
    }

    if (form.value.logoUrl) {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => {
            const logoSize = size * 0.2;
            const x = (totalSize - logoSize) / 2;
            const y = (totalSize - logoSize) / 2;
            ctx.save();
            ctx.fillStyle = '#ffffffcc';
            ctx.fillRect(x - 6, y - 6, logoSize + 12, logoSize + 12);
            ctx.drawImage(img, x, y, logoSize, logoSize);
            ctx.restore();
            updateDownloadUrl();
        };
        img.onerror = () => updateDownloadUrl();
        img.src = form.value.logoUrl;
    } else {
        updateDownloadUrl();
    }
}

function updateDownloadUrl() {
    const canvas = canvasRef.value;
    if (!canvas) return;
    canvas.toBlob((blob) => {
        if (!blob) return;
        if (downloadUrl.value) URL.revokeObjectURL(downloadUrl.value);
        downloadUrl.value = URL.createObjectURL(blob);
    });
}

function downloadPng() {
    if (!downloadUrl.value) return;
    const a = document.createElement('a');
    a.href = downloadUrl.value;
    a.download = 'codigo-qr.png';
    document.body.appendChild(a);
    a.click();
    a.remove();
}

function copyPng() {
    const canvas = canvasRef.value;
    if (!canvas || !navigator.clipboard || !window.ClipboardItem) return;
    canvas.toBlob((blob) => {
        if (!blob) return;
        navigator.clipboard
            .write([new ClipboardItem({ 'image/png': blob })])
            .then(() => {
                copied.value = true;
                setTimeout(() => (copied.value = false), 1500);
            })
            .catch((error) => console.error(error));
    });
}

const typeOptions = [
    { value: 'url', label: 'Enlace' },
    { value: 'text', label: 'Texto' },
    { value: 'email', label: 'Correo' },
    { value: 'location', label: 'Ubicación' },
    { value: 'phone', label: 'Teléfono' },
    { value: 'sms', label: 'SMS' },
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'zoom', label: 'Zoom' },
    { value: 'wifi', label: 'WiFi' },
    { value: 'vcard', label: 'vCard' },
    { value: 'event', label: 'Evento' },
];

onMounted(() => {
    generateQr();
    const el = document.createElement('script');
    el.type = 'application/ld+json';
    el.text = jsonLd.value;
    document.head.appendChild(el);
    jsonLdScriptEl.value = el;
});

onBeforeUnmount(() => {
    if (jsonLdScriptEl.value && jsonLdScriptEl.value.parentNode) jsonLdScriptEl.value.parentNode.removeChild(jsonLdScriptEl.value);
    if (downloadUrl.value) URL.revokeObjectURL(downloadUrl.value);
});
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
                        <p class="text-uppercase small mb-2 text-info fw-semibold">QR personalizado</p>
                        <h1 class="display-5 fw-bold mb-3">{{ seoData.h1 }}</h1>
                        <p class="lead text-white-50 mb-3">
                            {{ pageDescription }}
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-info text-dark">Colores y degradados</span>
                            <span class="badge bg-info text-dark">Logo y marco</span>
                            <span class="badge bg-info text-dark">Múltiples tipos de datos</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-5">
            <div class="container">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h2 class="h5 fw-semibold mb-1">Configura tu código QR</h2>
                                        <p class="text-muted small mb-0">Elige el tipo de contenido y estilo.</p>
                                    </div>
                                    <button class="btn btn-outline-secondary btn-sm" type="button" @click="generateQr">Generar</button>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Tipo de QR</label>
                                    <select class="form-select" v-model="form.type">
                                        <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                    </select>
                                </div>

                                <div v-if="form.type === 'url'" class="mb-3">
                                    <label class="form-label small fw-semibold">Enlace de destino</label>
                                    <input type="url" class="form-control" v-model="form.data.url" />
                                </div>

                                <div v-if="form.type === 'text'" class="mb-3">
                                    <label class="form-label small fw-semibold">Texto</label>
                                    <textarea class="form-control" rows="2" v-model="form.data.text"></textarea>
                                </div>

                                <div v-if="form.type === 'email'" class="row g-3 mb-3">
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Correo destinatario</label>
                                        <input type="email" class="form-control" v-model="form.data.email.to" />
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Asunto</label>
                                        <input type="text" class="form-control" v-model="form.data.email.subject" />
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Mensaje</label>
                                        <input type="text" class="form-control" v-model="form.data.email.body" />
                                    </div>
                                </div>

                                <div v-if="form.type === 'location'" class="row g-3 mb-3">
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Dirección o lugar</label>
                                        <input type="text" class="form-control" v-model="form.data.location.query" />
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Latitud (opcional)</label>
                                        <input type="text" class="form-control" v-model="form.data.location.lat" />
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Longitud (opcional)</label>
                                        <input type="text" class="form-control" v-model="form.data.location.lng" />
                                    </div>
                                </div>

                                <div v-if="form.type === 'phone'" class="mb-3">
                                    <label class="form-label small fw-semibold">Teléfono</label>
                                    <input type="text" class="form-control" v-model="form.data.phone" />
                                </div>

                                <div v-if="form.type === 'sms'" class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Número</label>
                                        <input type="text" class="form-control" v-model="form.data.sms.number" />
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Mensaje</label>
                                        <input type="text" class="form-control" v-model="form.data.sms.message" />
                                    </div>
                                </div>

                                <div v-if="form.type === 'whatsapp'" class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Número</label>
                                        <input type="text" class="form-control" v-model="form.data.whatsapp.number" />
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Mensaje</label>
                                        <input type="text" class="form-control" v-model="form.data.whatsapp.message" />
                                    </div>
                                </div>

                                <div v-if="form.type === 'zoom'" class="mb-3">
                                    <label class="form-label small fw-semibold">Enlace de Zoom</label>
                                    <input type="url" class="form-control" v-model="form.data.zoom.url" />
                                </div>

                                <div v-if="form.type === 'wifi'" class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">SSID</label>
                                        <input type="text" class="form-control" v-model="form.data.wifi.ssid" />
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Password</label>
                                        <input type="text" class="form-control" v-model="form.data.wifi.password" />
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Seguridad</label>
                                        <select class="form-select" v-model="form.data.wifi.security">
                                            <option value="WPA">WPA/WPA2</option>
                                            <option value="WEP">WEP</option>
                                            <option value="nopass">Sin contraseña</option>
                                        </select>
                                    </div>
                                    <div class="col-6 d-flex align-items-center">
                                        <div class="form-check mt-3">
                                            <input class="form-check-input" type="checkbox" v-model="form.data.wifi.hidden" id="hiddenWifi" />
                                            <label class="form-check-label small" for="hiddenWifi">Red oculta</label>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="form.type === 'vcard'" class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Nombre</label>
                                        <input type="text" class="form-control" v-model="form.data.vcard.firstName" />
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Apellido</label>
                                        <input type="text" class="form-control" v-model="form.data.vcard.lastName" />
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Teléfono</label>
                                        <input type="text" class="form-control" v-model="form.data.vcard.phone" />
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Correo</label>
                                        <input type="email" class="form-control" v-model="form.data.vcard.email" />
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Empresa</label>
                                        <input type="text" class="form-control" v-model="form.data.vcard.org" />
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Cargo</label>
                                        <input type="text" class="form-control" v-model="form.data.vcard.title" />
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Sitio web</label>
                                        <input type="url" class="form-control" v-model="form.data.vcard.url" />
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Dirección</label>
                                        <input type="text" class="form-control" v-model="form.data.vcard.address" />
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Nota</label>
                                        <textarea class="form-control" rows="2" v-model="form.data.vcard.note"></textarea>
                                    </div>
                                </div>

                                <div v-if="form.type === 'event'" class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Título</label>
                                        <input type="text" class="form-control" v-model="form.data.event.title" />
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Lugar</label>
                                        <input type="text" class="form-control" v-model="form.data.event.location" />
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Descripción</label>
                                        <textarea class="form-control" rows="2" v-model="form.data.event.description"></textarea>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Inicio</label>
                                        <input type="datetime-local" class="form-control" v-model="form.data.event.start" />
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Fin</label>
                                        <input type="datetime-local" class="form-control" v-model="form.data.event.end" />
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Tamaño (px)</label>
                                        <input type="number" class="form-control" v-model.number="form.size" min="180" max="1024" />
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Margen (módulos)</label>
                                        <input type="number" class="form-control" v-model.number="form.margin" min="0" max="12" />
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Precisión</label>
                                        <select class="form-select" v-model="form.errorCorrection">
                                            <option v-for="opt in errorCorrectionOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Color de fondo</label>
                                        <input type="color" class="form-control form-control-color" v-model="form.backgroundColor" />
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Color principal</label>
                                        <input type="color" class="form-control form-control-color" v-model="form.darkColor" />
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Color claro</label>
                                        <input type="color" class="form-control form-control-color" v-model="form.lightColor" />
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" v-model="form.useGradient" id="useGradient" />
                                        <label class="form-check-label small" for="useGradient">Usar degradado</label>
                                    </div>
                                    <div v-if="form.useGradient" class="row g-3 mt-2">
                                        <div class="col-6">
                                            <label class="form-label small fw-semibold">Color inicio</label>
                                            <input type="color" class="form-control form-control-color" v-model="form.gradientStart" />
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small fw-semibold">Color fin</label>
                                            <input type="color" class="form-control form-control-color" v-model="form.gradientEnd" />
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small fw-semibold">Tipo</label>
                                            <select class="form-select" v-model="form.gradientType">
                                                <option value="linear">Lineal</option>
                                                <option value="radial">Radial</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Logo dentro del QR (URL)</label>
                                    <input type="url" class="form-control" v-model="form.logoUrl" placeholder="https://..." />
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" v-model="form.frame" id="frameCheck" />
                                            <label class="form-check-label small" for="frameCheck">Agregar marco</label>
                                        </div>
                                    </div>
                                    <div class="col-6" v-if="form.frame">
                                        <label class="form-label small fw-semibold">Color marco</label>
                                        <input type="color" class="form-control form-control-color" v-model="form.frameColor" />
                                    </div>
                                    <div class="col-12" v-if="form.frame">
                                        <label class="form-label small fw-semibold">Grosor marco (px)</label>
                                        <input type="number" class="form-control" v-model.number="form.framePadding" min="6" max="60" />
                                    </div>
                                </div>

                                <button class="btn btn-primary w-100" type="button" @click="generateQr">Generar código QR</button>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h2 class="h5 fw-semibold mb-1">Vista previa</h2>
                                        <p class="text-muted small mb-0">Personaliza y descarga tu QR.</p>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <button class="btn btn-outline-primary btn-sm" type="button" @click="copyPng" :disabled="!downloadUrl">
                                            {{ copied ? 'Copiado' : 'Copiar' }}
                                        </button>
                                        <button class="btn btn-outline-success btn-sm" type="button" @click="downloadPng" :disabled="!downloadUrl">
                                            Descargar
                                        </button>
                                    </div>
                                </div>

                                <div class="qr-preview mb-3 d-flex justify-content-center align-items-center flex-grow-1">
                                    <canvas ref="canvasRef" class="img-fluid shadow-sm"></canvas>
                                </div>

                                <p class="text-muted small mb-0">Todo se genera en tu navegador. No almacenamos tu información.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mt-4">
                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <h2 class="h5 fw-semibold mb-3">¿Cómo usar esta herramienta?</h2>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item small">1) Elige el tipo de QR (enlace, texto, wifi, vCard, etc.).</li>
                                    <li class="list-group-item small">2) Personaliza colores, degradados, logo y marco.</li>
                                    <li class="list-group-item small">3) Genera y descarga tu código en PNG.</li>
                                    <li class="list-group-item small">4) Usa el QR en tarjetas, flyers o sitios web.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <h2 class="h5 fw-semibold mb-3">Preguntas frecuentes</h2>
                                <div class="accordion" id="accordionQr">
                                    <div class="accordion-item" v-for="(item, index) in seoData.faq" :key="index">
                                        <h2 class="accordion-header" :id="`heading-qr-${index}`">
                                            <button
                                                class="accordion-button collapsed"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                :data-bs-target="`#collapse-qr-${index}`"
                                                aria-expanded="false"
                                                :aria-controls="`collapse-qr-${index}`"
                                            >
                                                <span class="small fw-semibold">
                                                    {{ item.question }}
                                                </span>
                                            </button>
                                        </h2>
                                        <div
                                            :id="`collapse-qr-${index}`"
                                            class="accordion-collapse collapse"
                                            :aria-labelledby="`heading-qr-${index}`"
                                            data-bs-parent="#accordionQr"
                                        >
                                            <div class="accordion-body small text-muted">
                                                {{ item.answer }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
.qr-preview {
    min-height: 360px;
    background: #f8f9fa;
    border-radius: 12px;
    padding: 16px;
}

canvas {
    max-width: 100%;
    height: auto;
}
</style>
