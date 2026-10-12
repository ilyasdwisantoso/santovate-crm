/**
 * V9.4.1 Dashboard display policy.
 *
 * Values come from DashboardController / FinanceService. This module only
 * chooses the appropriate cards for each role. It never recalculates revenue,
 * conversion rates, commissions, or accounting values.
 */
const numeric = (value) => Number.isFinite(Number(value)) ? Number(value) : 0;

export function buildDashboardView({ stats = {}, financeSummary = {}, isAdmin = false } = {}) {
    const s = stats ?? {};
    const f = financeSummary ?? {};
    const money = (key, label, value, helper, icon, accent, href) =>
        ({ key, label, value: numeric(value), format: 'money', helper, icon, accent, href });
    const count = (key, label, value, helper, icon, accent, href) =>
        ({ key, label, value: numeric(value), format: 'number', helper, icon, accent, href });
    const percent = (key, label, value, helper, icon, accent, href) =>
        ({ key, label, value: numeric(value), format: 'percentage', helper, icon, accent, href });

    const overdue = numeric(f.overdue_count);
    const shared = [
        money('pipeline', isAdmin ? 'Pipeline Aktif' : 'Pipeline Saya',
            s.pipeline_value, 'Estimasi prospek terbuka', 'pipeline', 'violet', '/pipeline'),
        money('won', isAdmin ? 'Deal Won' : 'Deal Won Saya',
            s.actual_deal_value, `${numeric(s.won_deals)} deal berhasil`, 'check', 'green', '/deals'),
    ];

    const primary = isAdmin
        ? [
            ...shared,
            money('collected', 'Pembayaran Diterima',
                f.paid_by_client, 'Pembayaran bersih setelah refund', 'check', 'blue', '/finance'),
            money('outstanding', 'Piutang Belum Dibayar',
                f.outstanding, overdue > 0 ? `${overdue} invoice overdue` : 'Piutang berjalan',
                'alert', overdue > 0 ? 'red' : 'amber', '/finance'),
        ]
        : [
            ...shared,
            money('opportunities', 'Opportunity Saya', s.opportunity_value,
                'Nilai opportunity terbuka', 'target', 'blue', '/opportunities'),
            count('followup', 'Follow-up Perlu Tindakan', s.need_followup,
                'Lihat antrean tindak lanjut', 'calendar',
                numeric(s.need_followup) > 0 ? 'amber' : 'green', '/follow-ups'),
        ];

    const quick = [
        count('prospects', 'Total Prospek', s.total, 'Database prospek', 'building', 'blue', '/prospects'),
        ...(isAdmin
            ? [count('followups', 'Follow-up', s.need_followup, 'Perlu ditangani',
                'calendar', numeric(s.need_followup) > 0 ? 'amber' : 'green', '/follow-ups')]
            : [count('meetings', 'Meeting+', s.meeting_plus, 'Prospek di tahap lanjut',
                'calendar', 'blue', '/pipeline')]),
        ...(isAdmin
            ? [money('opportunity', 'Nilai Opportunity', s.opportunity_value,
                'Opportunity terbuka', 'target', 'violet', '/opportunities')]
            : [money('earned', 'Komisi Earned', f.earned_commission,
                'Komisi dari pembayaran', 'target', 'amber', '/finance/commissions')]),
        money('quotation', 'Nilai Quotation', s.quotation_value,
            'Penawaran dalam proses', 'briefcase', 'blue', '/quotations'),
        percent('conversion', 'Konversi', s.conversion_rate,
            'Opportunity closed yang won', 'pipeline', 'green', '/pipeline'),
        count('priority', 'Prioritas Tinggi', s.priority_high,
            'Prospek berprioritas tinggi', 'target', 'amber', '/prospects?priority=tinggi'),
    ];

    return { primary, quick };
}

export function dashboardFocusState(stats = {}) {
    const due = Math.max(0, numeric(stats?.need_followup));
    const priority = Math.max(0, numeric(stats?.priority_high));
    return {
        heading: due > 0 ? `${due} follow-up perlu ditangani` : 'Follow-up sudah terkendali',
        description: due > 0
            ? `Mulai dari antrean follow-up. ${priority} prospek berprioritas tinggi dalam database Anda.`
            : `${priority} prospek berprioritas tinggi dapat ditinjau untuk peluang berikutnya.`,
    };
}
