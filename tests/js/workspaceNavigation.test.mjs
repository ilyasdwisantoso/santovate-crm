import test from 'node:test';
import assert from 'node:assert/strict';
import { getNavigationGroups, getActiveNavigation, workspaceNav, executionNav } from '../../resources/js/Utils/workspaceNavigation.js';

const admin = { is_admin: true, is_platform_admin: false };
const sales = { is_admin: false, is_platform_admin: false };
const features = { features: { targets: true, imports: true, campaigns: true, whatsapp_api: true } };
const links = (items) => items.map(({ href }) => href);

test('sales navigation cannot expose management or finance', () => {
    const n = getNavigationGroups(sales, features, { is_internal: false });
    assert.deepEqual(n.adminItems, []);
    assert.ok(!links(n.workspaceItems).includes('/finance'));
    assert.ok(links(n.workspaceItems).includes('/prospects'));
});

test('admin sees finance and entitled management features', () => {
    const n = getNavigationGroups(admin, features, { is_internal: false });
    assert.ok(links(n.workspaceItems).includes('/finance'));
    assert.ok(links(n.executionItems).includes('/targets'));
    assert.ok(links(n.adminItems).includes('/campaigns'));
    assert.ok(links(n.adminItems).includes('/products'));
});

test('entitlement gates hide targets/imports/campaign/WhatsApp settings', () => {
    const n = getNavigationGroups(admin, { features: {} }, { is_internal: false });
    assert.ok(!links(n.executionItems).includes('/targets'));
    for (const href of ['/imports', '/campaigns', '/settings/whatsapp']) {
        assert.ok(!links(n.adminItems).includes(href));
    }
});

test('internal tenant hides products without hiding other admin tools', () => {
    const n = getNavigationGroups(admin, features, { is_internal: true });
    assert.ok(!links(n.adminItems).includes('/products'));
    assert.ok(links(n.adminItems).includes('/admin/users'));
});

test('platform-only management link appears only for admin platform owner', () => {
    const regular = getNavigationGroups(admin, features, {});
    const owner = getNavigationGroups({ is_admin: true, is_platform_admin: true }, features, {});
    const ownerSales = getNavigationGroups({ is_admin: false, is_platform_admin: true }, features, {});
    assert.ok(!links(regular.adminItems).includes('/admin/clients'));
    assert.ok(links(owner.adminItems).includes('/admin/clients'));
    assert.ok(!links(ownerSales.adminItems).includes('/admin/clients'));
});

test('active menu retains matching and section breadcrumbs', () => {
    const groups = getNavigationGroups(admin, features, {});
    assert.deepEqual(getActiveNavigation('/prospects/15', groups).activeItem?.href, '/prospects');
    assert.equal(getActiveNavigation('/settings/whatsapp', groups).sectionLabel, 'Management');
    assert.equal(getActiveNavigation('/targets', groups).sectionLabel, 'Execution');
    assert.equal(getActiveNavigation('/profile', groups).sectionLabel, 'Account');
});

test('mobile primary menu points to existing shared definitions', () => {
    assert.equal(workspaceNav[0].href, '/dashboard');
    assert.equal(workspaceNav[1].href, '/prospects');
    assert.equal(executionNav[0].href, '/follow-ups');
    assert.equal(executionNav[1].href, '/pipeline');
});
