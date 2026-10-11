/** Presentation-only pricing. Laravel remains the authority for checkout amounts. */
const price = (value) => {
    if (value === null || value === undefined || value === '') return null;
    const number = Number(value);
    return Number.isFinite(number) && number >= 0 ? number : null;
};

export const resolveRegistrationSelection = (items = [], requested, fallbackKey) => {
    const list = Array.isArray(items) ? items : [];
    return list.find(item => item.key === requested) ?? list.find(item => item.key === fallbackKey) ?? list[0] ?? null;
};

export const registrationPriceSummary = (plan, configuration, cycle = 'monthly') => {
    const annual = cycle === 'annual';
    const planAmount = price(plan?.[annual ? 'annual_price' : 'monthly_price']);
    const configurationAmount = price(configuration?.[annual ? 'annual_addon_price' : 'monthly_addon_price']);
    const valid = planAmount !== null && configurationAmount !== null;
    const total = valid ? planAmount + configurationAmount : null;
    const monthlyPlan = price(plan?.monthly_price);
    const monthlyConfig = price(configuration?.monthly_addon_price);
    const comparison = monthlyPlan !== null && monthlyConfig !== null ? 12 * (monthlyPlan + monthlyConfig) : null;
    const annualSavings = annual && total !== null && comparison !== null ? Math.max(0, comparison - total) : null;
    return { annual, planAmount, configurationAmount, total, annualSavings };
};

export const formatRegistrationRupiah = (value) => value === null || value === undefined
    ? '—'
    : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
