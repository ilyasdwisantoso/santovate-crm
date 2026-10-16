/** Display identity without turning an individual's name into a business field. */
export function prospectDisplayName(prospect) {
    return String(prospect?.display_name ??
        (prospect?.entity_type === 'individual' ? prospect.individual_name : prospect?.company_name) ?? '');
}
