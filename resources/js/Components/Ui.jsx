import { Link } from '@inertiajs/react';
import Icon from './Icon';
import { initials, money } from '../Utils/format';

export function Badge({ children, tone = 'neutral' }) {
    return <span className={`badge badge-${tone}`}>{children}</span>;
}

export function PriorityBadge({ priority }) {
    const tone = priority === 'tinggi' ? 'danger' : priority === 'sedang' ? 'warning' : 'neutral';
    return <Badge tone={tone}>{priority ? priority[0].toUpperCase() + priority.slice(1) : '—'}</Badge>;
}

export function StatusBadge({ status, label }) {
    const tones = {
        deal: 'success',
        won: 'success',
        paid: 'success',
        approved: 'success',
        proposal: 'violet',
        negosiasi: 'violet',
        meeting: 'info',
        demo: 'info',
        membalas: 'lime',
        dihubungi: 'warning',
        partially_paid: 'warning',
        pending_payment: 'warning',
        overdue: 'danger',
        ditolak: 'danger',
        rejected: 'danger',
        lost: 'danger',
        tidak_cocok: 'neutral',
        draft: 'neutral',
    };
    return <Badge tone={tones[status] || 'neutral'}>{label || status}</Badge>;
}

const avatarText = (name = '', email = '', initialsText = '') => {
    if (initialsText?.trim()) return initialsText.trim().slice(0, 2).toUpperCase();

    const base = initials(name);
    if (base !== 'SS') return base;

    const local = String(email || '').split('@')[0].replace(/[^a-z0-9]/gi, '');
    if (local.length >= 2) return local.slice(0, 2).toUpperCase();

    return 'SV';
};

export function Avatar({ name, email = '', initialsText = '', size = 'md' }) {
    return <span className={`avatar avatar-${size}`}>{avatarText(name, email, initialsText)}</span>;
}

export function Progress({ value = 0, tone = 'green' }) {
    const safe = Math.max(0, Math.min(100, Number(value || 0)));
    return (
        <div className="progress-track" role="progressbar" aria-valuenow={safe} aria-valuemin="0" aria-valuemax="100">
            <div className={`progress-fill progress-${tone}`} style={{ width: `${safe}%` }}/>
        </div>
    );
}

export function EmptyState({ icon = 'building', title, description, action }) {
    return (
        <div className="empty-state">
            <span className="empty-icon"><Icon name={icon} size={22}/></span>
            <h3>{title}</h3>
            {description && <p>{description}</p>}
            {action}
        </div>
    );
}

export function MetricCard({
    label,
    value,
    helper,
    icon = 'target',
    accent = 'green',
    featured = false,
    compact = false,
    href = null,
    trend = null,
}) {
    const content = (
        <>
            <div className={`metric-icon metric-${accent}`}><Icon name={icon} size={18}/></div>
            <div className="metric-copy">
                <span className="metric-label">{label}</span>
                <div className="metric-value-row">
                    <strong>{value}</strong>
                    {trend && <em className={`metric-trend metric-trend-${trend.tone || 'neutral'}`}>{trend.label}</em>}
                </div>
                {helper && <small>{helper}</small>}
            </div>
            {href && <span className="metric-chevron"><Icon name="chevron" size={15}/></span>}
        </>
    );

    const className = `metric-card ${featured ? 'metric-card-featured' : ''} ${compact ? 'metric-card-compact' : ''} metric-accent-${accent}`;
    return href
        ? <Link href={href} className={className}>{content}</Link>
        : <article className={className}>{content}</article>;
}

export function QuickStat({ label, value, icon = 'target', tone = 'blue', helper = null, href = null }) {
    const content = <>
        <span className={`quick-stat-icon quick-stat-${tone}`}><Icon name={icon} size={17}/></span>
        <span className="quick-stat-copy"><small>{label}</small><strong>{value}</strong>{helper && <em>{helper}</em>}</span>
        {href && <Icon name="chevron" size={15}/>} 
    </>;

    return href
        ? <Link href={href} className="quick-stat">{content}</Link>
        : <div className="quick-stat">{content}</div>;
}

export function SectionHeader({ eyebrow, title, description, action }) {
    return (
        <div className="panel-head premium-section-head">
            <div>
                {eyebrow && <span className="eyebrow">{eyebrow}</span>}
                <h3>{title}</h3>
                {description && <p>{description}</p>}
            </div>
            {action && <div className="section-head-action">{action}</div>}
        </div>
    );
}

export function ScoreDots({ value = 0 }) {
    return <div className="score-dots" aria-label={`Skor ${value} dari 3`}>{[1, 2, 3].map((i) => <i key={i} className={i <= value ? 'on' : ''}/>)}</div>;
}

export function Currency({ value }) {
    return <>{money(value)}</>;
}
