export const money = (value = 0) => new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR', maximumFractionDigits: 0,
}).format(Number(value || 0));

export const compactMoney = (value = 0) => new Intl.NumberFormat('id-ID', {
    notation: 'compact', maximumFractionDigits: 1,
}).format(Number(value || 0));

export const date = (value, fallback = '—') => {
    if (!value) return fallback;
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value));
};

export const dateTime = (value, fallback = '—') => {
    if (!value) return fallback;
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(value));
};

export const initials = (name = '') => name.split(/\s+/).filter(Boolean).slice(0, 2).map((x) => x[0]).join('').toUpperCase() || 'S';

export const isDue = (value) => value && new Date(value) <= new Date();
