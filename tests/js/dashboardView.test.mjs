import test from 'node:test';
import assert from 'node:assert/strict';
import { buildDashboardView, dashboardFocusState } from '../../resources/js/Utils/dashboardView.js';

const stats = {
    pipeline_value: 15000000, actual_deal_value: 3000000, won_deals: 2,
    opportunity_value: 8000000, quotation_value: 4000000, need_followup: 4,
    total: 12, priority_high: 3, meeting_plus: 6, conversion_rate: 40,
};
const financeSummary = {
    paid_by_client: 2750000, outstanding: 250000, overdue_count: 1,
    earned_commission: 350000,
};

test('admin sees finance cash metrics and links to permitted finance route', () => {
    const { primary } = buildDashboardView({ stats, financeSummary, isAdmin: true });
    assert.deepEqual(primary.map(card => card.key), ['pipeline', 'won', 'collected', 'outstanding']);
    assert.equal(primary.find(card => card.key === 'collected').value, 2750000);
    assert.equal(primary.find(card => card.key === 'outstanding').helper, '1 invoice overdue');
    assert.equal(primary.find(card => card.key === 'collected').href, '/finance');
});

test('account executive never receives links to admin-only finance dashboard', () => {
    const { primary, quick } = buildDashboardView({ stats, financeSummary, isAdmin: false });
    assert.deepEqual(primary.map(card => card.key), ['pipeline', 'won', 'opportunities', 'followup']);
    assert.ok([...primary, ...quick].every(card => card.href !== '/finance'));
    assert.equal(quick.find(card => card.key === 'earned').href, '/finance/commissions');
    assert.equal(primary.find(card => card.key === 'followup').value, 4);
});

test('every card has a known route, a unique key, a valid display type', () => {
    for (const admin of [true, false]) {
        const { primary, quick } = buildDashboardView({ stats, financeSummary, isAdmin: admin });
        assert.equal(primary.length, 4);
        assert.equal(quick.length, 6);
        for (const cards of [primary, quick]) {
            assert.equal(new Set(cards.map(x => x.key)).size, cards.length);
            assert.ok(cards.every(x => x.href?.startsWith('/')));
            assert.ok(cards.every(x => ['number', 'money', 'percentage'].includes(x.format)));
            assert.ok(cards.every(x => Number.isFinite(x.value)));
        }
    }
});

test('zero and missing statistics remain safe, never NaN', () => {
    const sets = [buildDashboardView(), buildDashboardView({ stats: null, financeSummary: null })];
    for (const set of sets) {
        assert.ok([...set.primary, ...set.quick].every(x => Number.isFinite(x.value)));
    }
});

test('focus copy separates pending follow-up from a clear queue', () => {
    assert.match(dashboardFocusState(stats).heading, /4 follow-up/);
    assert.match(dashboardFocusState({need_followup: 0}).heading, /terkendali/);
    assert.match(dashboardFocusState({need_followup: 0,priority_high: 2}).description, /2 prospek/);
});

test('finance role visibility is independent of target entitlement', () => {
    const a = buildDashboardView({stats, financeSummary, isAdmin: false});
    const b = buildDashboardView({stats, financeSummary, isAdmin: true});
    assert.equal(a.primary[1].value, b.primary[1].value);
    assert.ok(!a.primary.some(x => x.key === 'collected' || x.key === 'outstanding'));
});
