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
    const tones = { deal: 'success', proposal: 'violet', negosiasi: 'violet', meeting: 'info', demo: 'info', membalas: 'lime', dihubungi: 'warning', ditolak: 'danger', tidak_cocok: 'neutral' };
    return <Badge tone={tones[status] || 'neutral'}>{label || status}</Badge>;
}

export function Avatar({ name, size = 'md' }) {
    return <span className={`avatar avatar-${size}`}>{initials(name)}</span>;
}

export function Progress({ value = 0, tone = 'green' }) {
    const safe = Math.max(0, Math.min(100, Number(value || 0)));
    return <div className="progress-track"><div className={`progress-fill progress-${tone}`} style={{ width: `${safe}%` }} /></div>;
}

export function EmptyState({ icon = 'building', title, description, action }) {
    return <div className="empty-state"><span className="empty-icon"><Icon name={icon} size={24}/></span><h3>{title}</h3><p>{description}</p>{action}</div>;
}

export function MetricCard({ label, value, helper, icon = 'target', accent = 'green' }) {
    return <article className="metric-card"><div className={`metric-icon metric-${accent}`}><Icon name={icon} size={19}/></div><div><span>{label}</span><strong>{value}</strong>{helper && <small>{helper}</small>}</div></article>;
}

export function ScoreDots({ value = 0 }) {
    return <div className="score-dots" aria-label={`Skor ${value} dari 3`}>{[1,2,3].map((i)=><i key={i} className={i<=value?'on':''}/>)}</div>;
}

export function Currency({ value }) { return <>{money(value)}</>; }
