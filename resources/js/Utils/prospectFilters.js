/* Canonical URL/filter helpers shared by desktop and mobile prospect views. */
export const DEFAULT_FILTERS = Object.freeze({
    q: '', entity_type: '', priority: '', status: '', qualification_status: '',
    assigned_to: '', followup: '', import_batch: '', sort: 'score',
});

export function prospectFilterState(filters = {}) {
    return Object.fromEntries(Object.entries(DEFAULT_FILTERS).map(([key, fallback]) => [
        key, typeof filters?.[key] === 'string' ? filters[key] : fallback,
    ]));
}

export function prospectFilterQuery(filters = {}) {
    const source = prospectFilterState(filters);
    const query = {};
    for (const [key, value] of Object.entries(source)) {
        const cleaned = value.trim();
        if (cleaned && !(key === 'sort' && cleaned === 'score')) query[key] = cleaned;
    }
    return query;
}

export function prospectActiveChips(filters = {}, { statuses = {}, qualifications = {}, priorities = {}, salesUsers = [], isAdmin = false } = {}) {
    const state = prospectFilterState(filters);
    const chips = [];
    if (state.q) chips.push({ key: 'q', label: `Pencarian: ${state.q}` });
    if (state.entity_type) chips.push({ key: 'entity_type', label: `Jenis: ${state.entity_type === 'individual' ? 'Individu / B2C' : 'Perusahaan / B2B'}` });
    if (state.priority) chips.push({ key: 'priority', label: `Prioritas: ${priorities[state.priority] || state.priority}` });
    if (state.status) chips.push({ key: 'status', label: `Status: ${statuses[state.status] || state.status}` });
    if (state.qualification_status) chips.push({ key: 'qualification_status', label: `Kualifikasi: ${qualifications[state.qualification_status] || state.qualification_status}` });
    if (state.assigned_to && isAdmin) {
        const name = state.assigned_to === 'unassigned' ? 'Belum ditugaskan' :
            salesUsers.find(user => String(user.id) === state.assigned_to)?.name || 'AE';
        chips.push({ key: 'assigned_to', label: `Account Executive: ${name}` });
    }
    if (state.followup === 'due') chips.push({ key: 'followup', label: 'Follow-up jatuh tempo' });
    if (state.import_batch) chips.push({ key: 'import_batch', label: `Import #${state.import_batch}` });
    if (state.sort !== 'score') {
        const labels = { recent: 'Terbaru', followup: 'Follow-up terdekat', value: 'Potensi terbesar' };
        chips.push({ key: 'sort', label: `Urutkan: ${labels[state.sort] || state.sort}` });
    }
    return chips;
}

export function removeProspectFilter(filters, key) {
    if (!Object.prototype.hasOwnProperty.call(DEFAULT_FILTERS, key)) return prospectFilterState(filters);
    return { ...prospectFilterState(filters), [key]: DEFAULT_FILTERS[key] };
}
