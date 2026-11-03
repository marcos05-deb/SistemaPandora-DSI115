/**
 * Fechas clínicas: todo se muestra en America/El_Salvador, locale es-SV, formato 24 h.
 * Nunca usar `new Date('YYYY-MM-DD')` (UTC) ni toISOString().slice(0,10) para "hoy".
 */
export const TZ = 'America/El_Salvador';
export const LOCALE = 'es-SV';

const DATE_OPTS = { year: 'numeric', month: 'long', day: 'numeric' };
const DATETIME_OPTS = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' };
const TIME_OPTS = { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' };

const toDate = value => {
    if (value == null || value === '') return null;
    const d = value instanceof Date ? value : new Date(String(value));
    return Number.isNaN(d.getTime()) ? null : d;
};

const partsOf = (date, options) => {
    const parts = new Intl.DateTimeFormat('en-CA', { timeZone: TZ, hourCycle: 'h23', ...options }).formatToParts(date);
    return type => parts.find(item => item.type === type)?.value ?? '';
};

/** @returns {string} YYYY-MM-DD de hoy en El Salvador */
export function todayLocalYmd() {
    const p = partsOf(new Date(), { year: 'numeric', month: '2-digit', day: '2-digit' });
    return `${p('year')}-${p('month')}-${p('day')}`;
}

/**
 * ¿Es una fecha calendario sin hora? (YYYY-MM-DD)
 * @param {unknown} value
 */
export function isDateOnly(value) {
    if (value == null || value === '') return false;
    return /^\d{4}-\d{2}-\d{2}$/.test(String(value).trim());
}

/**
 * Convierte YYYY-MM-DD a Date local (medianoche), sin el desfase de UTC.
 * @param {string|null|undefined} value
 * @returns {Date|null}
 */
export function parseLocalYmd(value) {
    if (!isDateOnly(value)) return null;
    const [y, m, d] = String(value).trim().split('-').map(Number);
    return new Date(y, m - 1, d);
}

/** Suma días a un YYYY-MM-DD (aritmética de calendario, sin zonas). */
export function addDaysYmd(ymd, days) {
    const [y, m, d] = ymd.split('-').map(Number);
    return new Date(Date.UTC(y, m - 1, d + days)).toISOString().slice(0, 10);
}

/**
 * Fecha (sin hora) en El Salvador. Acepta YYYY-MM-DD o instante ISO.
 */
export function formatLocalDate(value, options = DATE_OPTS, locale = LOCALE, emptyLabel = 'No registrada') {
    if (value == null || value === '') return emptyLabel;
    const s = String(value).trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
        const [y, m, d] = s.split('-').map(Number);
        // Fecha calendario: se formatea en UTC para no desplazar el día.
        return new Intl.DateTimeFormat(locale, { ...options, timeZone: 'UTC' }).format(new Date(Date.UTC(y, m - 1, d)));
    }
    const dt = toDate(s);
    if (!dt) return emptyLabel;
    return new Intl.DateTimeFormat(locale, { ...options, timeZone: TZ }).format(dt);
}

/** Fecha y hora (24 h) en El Salvador. */
export function formatElSalvadorDateTime(value, locale = LOCALE, emptyLabel = 'No registrada') {
    const dt = toDate(value);
    if (!dt) return emptyLabel;
    return new Intl.DateTimeFormat(locale, { ...DATETIME_OPTS, timeZone: TZ }).format(dt);
}

export function formatLocalDateTime(value, locale = LOCALE, emptyLabel = '') {
    return formatElSalvadorDateTime(value, locale, emptyLabel);
}

/** Hora (24 h) en El Salvador. */
export function formatLocalTime(value, locale = LOCALE) {
    const dt = toDate(value);
    if (!dt) return '-';
    return new Intl.DateTimeFormat(locale, { ...TIME_OPTS, timeZone: TZ }).format(dt);
}

/**
 * @param {string|Date|null|undefined} value
 * @returns {string} YYYY-MM-DDTHH:mm en El Salvador (para input datetime-local)
 */
export function formatElSalvadorDateTimeInput(value) {
    const date = toDate(value);
    if (!date) return '';
    const p = partsOf(date, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
    return `${p('year')}-${p('month')}-${p('day')}T${p('hour')}:${p('minute')}`;
}

/** Ahora (+ días) como YYYY-MM-DDTHH:mm en El Salvador. */
export function nowElSalvadorInput(plusDays = 0) {
    return formatElSalvadorDateTimeInput(new Date(Date.now() + plusDays * 86_400_000));
}
