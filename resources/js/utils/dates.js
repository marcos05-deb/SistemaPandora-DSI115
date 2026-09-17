/**
 * Fechas clínicas: America/El_Salvador.
 * Nunca usar `new Date('YYYY-MM-DD')` (UTC) ni toISOString().slice(0,10) para "hoy" local.
 */

/** @returns {string} YYYY-MM-DD en zona local del navegador */
export function todayLocalYmd() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

/**
 * ¿Es una fecha calendario sin hora? (YYYY-MM-DD o prefijo de timestamp sin T usable)
 * @param {unknown} value
 */
export function isDateOnly(value) {
    if (value == null || value === '') return false;
    const s = String(value).trim();
    return /^\d{4}-\d{2}-\d{2}$/.test(s);
}

/**
 * Formatea fecha calendario o datetime sin restar un día por UTC.
 * @param {string|null|undefined} value
 * @param {Intl.DateTimeFormatOptions} options
 * @param {string} locale
 * @param {string} emptyLabel
 */
export function formatLocalDate(
    value,
    options = { year: 'numeric', month: 'long', day: 'numeric' },
    locale = 'es-ES',
    emptyLabel = 'No registrada'
) {
    if (value == null || value === '') return emptyLabel;

    const s = String(value).trim();

    if (isDateOnly(s) || (/^\d{4}-\d{2}-\d{2}/.test(s) && !s.includes('T') && s.length <= 10)) {
        const [y, m, d] = s.slice(0, 10).split('-').map(Number);
        return new Date(y, m - 1, d).toLocaleDateString(locale, options);
    }

    // ISO datetime o timestamp: respetar zona del instante
    const dt = new Date(s);
    if (Number.isNaN(dt.getTime())) return emptyLabel;
    return dt.toLocaleDateString(locale, options);
}

/**
 * @param {string|null|undefined} value
 * @param {string} locale
 * @param {string} emptyLabel
 */
export function formatLocalDateTime(
    value,
    locale = 'es-ES',
    emptyLabel = ''
) {
    if (value == null || value === '') return emptyLabel;
    const dt = new Date(String(value));
    if (Number.isNaN(dt.getTime())) return emptyLabel;
    return dt.toLocaleString(locale, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * Hora local a partir de un instante ISO.
 * @param {string|null|undefined} value
 */
export function formatLocalTime(value, locale = 'es-ES') {
    if (value == null || value === '') return '-';
    const dt = new Date(String(value));
    if (Number.isNaN(dt.getTime())) return '-';
    return dt.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit', hour12: true }).toUpperCase();
}
