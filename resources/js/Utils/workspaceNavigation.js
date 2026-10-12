/*
 * Santovate CRM V9.4.0 — single source of truth for desktop/mobile navigation.
 * A menu being visible is NOT authorization: all routes must retain middleware.
 */
export const workspaceNav = [
    { href: '/dashboard', label: 'Dashboard', icon: 'home', match: (u) => u.startsWith('/dashboard') },
    { href: '/prospects', label: 'Prospek', icon: 'building', match: (u) => u.startsWith('/prospects') },
    { href: '/opportunities', label: 'Opportunities', icon: 'target', match: (u) => u.startsWith('/opportunities') },
    { href: '/quotations', label: 'Quotations', icon: 'briefcase', match: (u) => u.startsWith('/quotations') },
    { href: '/deals', label: 'Deals', icon: 'check', match: (u) => u.startsWith('/deals') },
    { href: '/finance', label: 'Finance', icon: 'briefcase', adminOnly: true, match: (u) => u.startsWith('/finance') },
];

export const executionNav = [
    { href: '/follow-ups', label: 'Follow Up', icon: 'whatsapp', match: (u) => u.startsWith('/follow-ups') },
    { href: '/pipeline', label: 'Pipeline', icon: 'pipeline', match: (u) => u.startsWith('/pipeline') },
    { href: '/targets', label: 'Target AE', icon: 'target', feature: 'targets', match: (u) => u.startsWith('/targets') },
    { href: '/sales-guide', label: 'Panduan', icon: 'spark', match: (u) => u.startsWith('/sales-guide') },
    { href: '/approvals', label: 'Approvals', icon: 'check', match: (u) => u.startsWith('/approvals') },
];

const managementNav = [
    { href: '/imports', label: 'Import Data', icon: 'upload', feature: 'imports', match: (u) => u.startsWith('/imports') },
    { href: '/admin/users', label: 'Tim & Access', icon: 'users', match: (u) => u.startsWith('/admin/users') },
    { href: '/products', label: 'Katalog Produk', icon: 'briefcase', hideInternal: true, match: (u) => u.startsWith('/products') },
    { href: '/campaigns', label: 'WA Campaign', icon: 'whatsapp', feature: 'campaigns', match: (u) => u.startsWith('/campaigns') },
    { href: '/settings/whatsapp', label: 'WhatsApp API', icon: 'whatsapp', feature: 'whatsapp_api', match: (u) => u.startsWith('/settings/whatsapp') },
    { href: '/settings/business', label: 'Business Config', icon: 'spark', match: (u) => u.startsWith('/settings/business') },
    { href: '/subscription/addons', label: 'Subscription Add-ons', icon: 'spark', match: (u) => u.startsWith('/subscription/addons') },
    { href: '/admin/clients', label: 'Client Onboarding', icon: 'users', platformOnly: true, match: (u) => u.startsWith('/admin/clients') },
];

export function getNavigationGroups(user = {}, entitlements = {}, organization = {}) {
    const eligible = (item) =>
        (!item.adminOnly || Boolean(user?.is_admin)) &&
        (!item.platformOnly || Boolean(user?.is_platform_admin)) &&
        (!item.feature || Boolean(entitlements?.features?.[item.feature])) &&
        (!item.hideInternal || !organization?.is_internal);

    return {
        workspaceItems: workspaceNav.filter(eligible),
        executionItems: executionNav.filter(eligible),
        adminItems: user?.is_admin ? managementNav.filter(eligible) : [],
    };
}

export function getActiveNavigation(url = '', groups = {}) {
    const workspaceItems = groups.workspaceItems || [];
    const executionItems = groups.executionItems || [];
    const adminItems = groups.adminItems || [];
    const activeItem = [...workspaceItems, ...executionItems, ...adminItems].find((item) => item.match(url));
    const sectionLabel = !activeItem ? 'Account' :
        workspaceItems.some((item) => item.href === activeItem.href) ? 'Workspace' :
        executionItems.some((item) => item.href === activeItem.href) ? 'Execution' : 'Management';
    return { activeItem, sectionLabel };
}
