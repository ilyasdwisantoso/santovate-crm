import test from 'node:test';
import assert from 'node:assert/strict';
import { formatRegistrationRupiah, registrationPriceSummary, resolveRegistrationSelection } from '../../resources/js/Utils/registrationPricing.js';

const plans = [{ key: 'growth', name: 'Growth', monthly_price: 650000, annual_price: 6500000 }, { key: 'scale', name: 'Scale', monthly_price: 1250000, annual_price: 12500000 }];
const configs = [{ key: 'software-agency', name: 'Software & Agency', monthly_addon_price: 50000, annual_addon_price: 500000 }, { key: 'retail', name: 'Retail', monthly_addon_price: 0, annual_addon_price: 0 }];

test('URL preselection resolves only catalog keys and uses a real catalog fallback', () => {
    assert.equal(resolveRegistrationSelection(plans, 'scale', 'growth').key, 'scale');
    assert.equal(resolveRegistrationSelection(plans, 'unknown', 'growth').key, 'growth');
    assert.equal(resolveRegistrationSelection([], 'scale', 'growth'), null);
});

test('monthly price breakdown includes plan plus configuration', () => {
    assert.deepEqual(registrationPriceSummary(plans[0], configs[0], 'monthly'), {
        annual: false, planAmount: 650000, configurationAmount: 50000, total: 700000, annualSavings: null,
    });
});

test('annual billing uses its own prices and shows only genuine savings', () => {
    assert.deepEqual(registrationPriceSummary(plans[0], configs[0], 'annual'), {
        annual: true, planAmount: 6500000, configurationAmount: 500000, total: 7000000, annualSavings: 1400000,
    });
});

test('zero-cost configurations are not mistaken for missing prices', () => {
    assert.equal(registrationPriceSummary(plans[1], configs[1], 'monthly').total, 1250000);
});

test('incomplete catalog never displays a fictitious price', () => {
    assert.equal(registrationPriceSummary(plans[1], {key: 'incomplete'}, 'monthly').total, null);
    assert.equal(formatRegistrationRupiah(null), '—');
    assert.match(formatRegistrationRupiah(700000), /700\.000/);
});
