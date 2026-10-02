export const fmtInt = (value) => Math.round(Number(value) || 0).toLocaleString('id-ID');

export const fmtNum = (value, digits = 1) =>
    (Number(value) || 0).toLocaleString('id-ID', { minimumFractionDigits: digits, maximumFractionDigits: digits });

export const fmtPct = (value, digits = 1) => `${(Number(value) || 0).toFixed(digits)}%`;

export const fmtShortDate = (isoDate) =>
    new Date(`${isoDate}T00:00:00`).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });

export function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

export function heatColor(value) {
    if (value < 60) return '#EF4444';
    if (value < 70) return '#F59E0B';
    if (value < 80) return '#EAB308';
    if (value < 90) return '#10B981';
    return '#059669';
}

export const COLORS = {
    green: '#10B981',
    amber: '#E5A91A',
    dark: '#33373E',
    grey: '#555963',
    red: '#EF4444',
    grid: '#E8E4D8',
    track: '#EFECE3',
};
