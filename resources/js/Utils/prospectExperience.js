/* Santovate CRM V9.4.3: display-only prospect UI helpers. */
export const ADVANCED_PROSPECT_FIELDS = Object.freeze([
    'estimated_budget', 'expected_timeline', 'target_go_live', 'urgency',
    'probability', 'source_name', 'source_url', 'notes',
]);

export function shouldExpandProspectAdvanced(editing = false, errors = {}) {
    return Boolean(editing || ADVANCED_PROSPECT_FIELDS.some(key => Boolean(errors?.[key])));
}

export function normalizedWhatsAppNumber(phone) {
    const digits = String(phone ?? '').replace(/\D/g, '');
    if (!digits) return '';
    if (digits.startsWith('0')) return `62${digits.slice(1)}`;
    if (digits.startsWith('8')) return `62${digits}`;
    return digits;
}

export function prospectWhatsAppUrl(phone) {
    const number = normalizedWhatsAppNumber(phone);
    // E.164 numbers: no leading zero; 8-15 digits overall.
    if (!/^[1-9]\d{7,14}$/.test(number)) return null;
    return `https://wa.me/${number}`;
}

export function safeProspectSourceUrl(raw) {
    const input = String(raw ?? '').trim();
    if (!input) return null;
    try {
        const parsed = new URL(input);
        return ['https:', 'http:'].includes(parsed.protocol) && parsed.hostname ? parsed.href : null;
    } catch {
        return null;
    }
}

export function filterProspectTimeline(activities = [], type = 'all') {
    if (!Array.isArray(activities)) return [];
    return type === 'all' ? activities : activities.filter(item => item?.type === type);
}

export function visibleProspectTimeline(activities = [], type = 'all', expanded = false, pageSize = 6) {
    const filtered = filterProspectTimeline(activities, type);
    const size = Math.max(1, Math.min(30, Number(pageSize) || 6));
    return {
        visible: expanded ? filtered : filtered.slice(0, size),
        matchingCount: filtered.length,
        hasMore: !expanded && filtered.length > size,
    };
}
