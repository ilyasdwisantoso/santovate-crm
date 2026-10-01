const metaToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const xsrfCookie = () => {
    const row = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='));
    if (!row) return '';
    try { return decodeURIComponent(row.substring('XSRF-TOKEN='.length)); }
    catch { return row.substring('XSRF-TOKEN='.length); }
};

const request = (input, options = {}) => {
    const headers = new Headers(options.headers || {});
    const meta = metaToken();
    const cookie = xsrfCookie();
    if (meta) headers.set('X-CSRF-TOKEN', meta);
    if (cookie) headers.set('X-XSRF-TOKEN', cookie);
    headers.set('X-Requested-With', 'XMLHttpRequest');
    return fetch(input, { ...options, credentials: 'include', headers });
};

export default async function csrfFetch(input, options = {}) {
    let response = await request(input, options);
    if (response.status !== 419) return response;

    const refresh = await fetch('/session/csrf-token', {
        method: 'GET',
        credentials: 'include',
        cache: 'no-store',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });

    if (!refresh.ok) return response;
    const data = await refresh.json().catch(() => ({}));
    if (data?.token) {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', data.token);
    }

    response = await request(input, options);
    return response;
}