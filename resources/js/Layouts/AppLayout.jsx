import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '../Components/Icon';
import Flash from '../Components/Flash';
import { Avatar } from '../Components/Ui';

const nav = [
    { href: '/', label: 'Dashboard', icon: 'home', match: (u) => u === '/' },
    { href: '/prospects', label: 'Prospek', icon: 'building', match: (u) => u.startsWith('/prospects') },
    { href: '/follow-ups', label: 'Follow Up', icon: 'whatsapp', match: (u) => u.startsWith('/follow-ups') },
    { href: '/pipeline', label: 'Pipeline', icon: 'pipeline', match: (u) => u.startsWith('/pipeline') },
    { href: '/targets', label: 'Target AE', icon: 'target', match: (u) => u.startsWith('/targets') },
];

function NavLink({ item, url, mobile = false }) {
    const active = item.match(url);
    return (
        <Link
            href={item.href}
            className={`${mobile ? 'bottom-link' : 'side-link'} ${active ? 'active' : ''}`}
        >
            <span className={mobile ? 'bottom-icon-wrap' : ''}>
                <Icon name={item.icon} size={mobile ? 21 : 19}/>
            </span>
            <span>{item.label}</span>
        </Link>
    );
}

function NotificationCenter({ notifications, onClose }) {
    const unread = notifications?.unread_count || 0;
    const items = notifications?.items || [];

    const openNotification = (item) => {
        router.post(`/notifications/${item.id}/read`, {}, {
            preserveScroll: true,
            onSuccess: () => router.visit(item.data?.action_url || '/prospects'),
        });
        onClose?.();
    };

    const readAll = () => router.post('/notifications/read-all', {}, { preserveScroll: true });

    return (
        <div className="notification-popover">
            <div className="notification-head">
                <div>
                    <strong>Notifikasi</strong>
                    <small>{unread ? `${unread} belum dibaca` : 'Semua sudah dibaca'}</small>
                </div>
                {unread > 0 && <button onClick={readAll}>Tandai semua dibaca</button>}
            </div>

            <div className="notification-list">
                {items.length ? items.map((item) => (
                    <button
                        key={item.id}
                        className={`notification-item ${item.read_at ? '' : 'unread'}`}
                        onClick={() => openNotification(item)}
                    >
                        <span className="notification-item-icon"><Icon name="building" size={17}/></span>
                        <span>
                            <strong>{item.data?.title || 'Notifikasi CRM'}</strong>
                            <small>{item.data?.message || ''}</small>
                            <em>
                                {item.created_at
                                    ? new Date(item.created_at).toLocaleString('id-ID', {
                                        day: 'numeric',
                                        month: 'short',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })
                                    : ''}
                            </em>
                        </span>
                        {!item.read_at && <i/>}
                    </button>
                )) : (
                    <div className="notification-empty">
                        <Icon name="bell" size={24}/>
                        <strong>Belum ada notifikasi</strong>
                        <small>Assignment prospek baru akan muncul di sini.</small>
                    </div>
                )}
            </div>
        </div>
    );
}

export default function AppLayout({ children, title, subtitle, action }) {
    const page = usePage();
    const { auth, notifications } = page.props;
    const url = page.url;
    const user = auth.user;

    const [moreOpen, setMoreOpen] = useState(false);
    const [notificationOpen, setNotificationOpen] = useState(false);

    const logout = () => router.post('/logout');

    return (
        <div className="app-shell">
            <aside className="sidebar">
                <div className="brand">
                    <span className="brand-mark">S</span>
                    <div>
                        <strong>Santovate</strong>
                        <small>CRM Account Executive</small>
                    </div>
                </div>

                <nav className="sidebar-nav">
                    <p className="nav-label">Workspace</p>
                    {nav.map((item) => <NavLink key={item.href} item={item} url={url}/>)}

                    <p className="nav-label nav-label-spaced">Account</p>
                    <NavLink
                        item={{
                            href: '/profile',
                            label: 'Profile',
                            icon: 'users',
                            match: (u) => u.startsWith('/profile'),
                        }}
                        url={url}
                    />

                    {user.is_admin && (
                        <>
                            <p className="nav-label nav-label-spaced">Data & Team</p>
                            <NavLink
                                item={{
                                    href: '/imports',
                                    label: 'Import Data',
                                    icon: 'upload',
                                    match: (u) => u.startsWith('/imports'),
                                }}
                                url={url}
                            />
                            <NavLink
                                item={{
                                    href: '/admin/users',
                                    label: 'Tim Account Executive',
                                    icon: 'users',
                                    match: (u) => u.startsWith('/admin/users'),
                                }}
                                url={url}
                            />
                        </>
                    )}
                </nav>

                <div className="sidebar-user">
                    <Link href="/profile" className="sidebar-profile-link">
                        <Avatar
                            name={user.name}
                            email={user.email}
                            initialsText={user.profile_initials}
                        />
                        <div className="sidebar-user-copy">
                            <strong>{user.name}</strong>
                            <small>{user.job_title || (user.is_admin ? 'Administrator' : 'Account Executive')}</small>
                        </div>
                    </Link>
                    <button className="icon-button ghost" onClick={logout} title="Keluar">
                        <Icon name="logout" size={18}/>
                    </button>
                </div>
            </aside>

            <main className="main-area">
                <header className="topbar">
                    <div className="mobile-brand">
                        <span className="brand-mark sm">S</span>
                        <strong>Santovate</strong>
                    </div>

                    <div className="page-heading">
                        <h1>{title}</h1>
                        {subtitle && <p>{subtitle}</p>}
                    </div>

                    <div className="topbar-tools">
                        <div className="topbar-action">{action}</div>
                        <div className="notification-center-wrap">
                            <button
                                className={`notification-trigger ${notificationOpen ? 'active' : ''}`}
                                onClick={() => setNotificationOpen(!notificationOpen)}
                                aria-label="Notifikasi"
                            >
                                <Icon name="bell" size={19}/>
                                {(notifications?.unread_count || 0) > 0 && (
                                    <span>{notifications.unread_count > 9 ? '9+' : notifications.unread_count}</span>
                                )}
                            </button>
                            {notificationOpen && (
                                <NotificationCenter
                                    notifications={notifications}
                                    onClose={() => setNotificationOpen(false)}
                                />
                            )}
                        </div>
                    </div>
                </header>

                <div className="page-content">
                    <Flash/>
                    {children}
                </div>
            </main>

            <nav className="bottom-nav" aria-label="Navigasi utama mobile">
                {nav
                    .filter((item) => ['/', '/prospects', '/follow-ups', '/pipeline'].includes(item.href))
                    .map((item) => <NavLink key={item.href} item={item} url={url} mobile/>)}

                <button
                    className={`bottom-link ${moreOpen ? 'active' : ''}`}
                    onClick={() => setMoreOpen(true)}
                >
                    <span className="bottom-icon-wrap"><Icon name="more" size={21}/></span>
                    <span>Lainnya</span>
                </button>
            </nav>

            {moreOpen && (
                <div className="sheet-backdrop" onClick={() => setMoreOpen(false)}>
                    <section className="bottom-sheet" onClick={(e) => e.stopPropagation()}>
                        <div className="sheet-handle"/>
                        <div className="sheet-head">
                            <div className="sheet-user-head">
                                <Avatar
                                    name={user.name}
                                    email={user.email}
                                    initialsText={user.profile_initials}
                                    size="sm"
                                />
                                <div>
                                    <strong>{user.name}</strong>
                                    <small>{user.job_title || (user.is_admin ? 'Administrator' : 'Account Executive')}</small>
                                </div>
                            </div>
                            <button className="icon-button" onClick={() => setMoreOpen(false)}>
                                <Icon name="close" size={20}/>
                            </button>
                        </div>

                        <div className="sheet-menu">
                            <Link href="/profile" onClick={() => setMoreOpen(false)}>
                                <span className="sheet-menu-icon"><Icon name="users" size={19}/></span>
                                <div>
                                    <strong>Profile</strong>
                                    <small>Update nama, inisial, jabatan, WhatsApp, dan password</small>
                                </div>
                                <Icon name="chevron" size={18}/>
                            </Link>

                            <Link href="/targets" onClick={() => setMoreOpen(false)}>
                                <span className="sheet-menu-icon"><Icon name="target" size={19}/></span>
                                <div>
                                    <strong>Target AE</strong>
                                    <small>Target dan performance Account Executive</small>
                                </div>
                                <Icon name="chevron" size={18}/>
                            </Link>

                            {user.is_admin && (
                                <Link href="/imports" onClick={() => setMoreOpen(false)}>
                                    <span className="sheet-menu-icon"><Icon name="upload" size={19}/></span>
                                    <div>
                                        <strong>Import Data</strong>
                                        <small>Upload dan assign database prospek ke Account Executive</small>
                                    </div>
                                    <Icon name="chevron" size={18}/>
                                </Link>
                            )}

                            {user.is_admin && (
                                <Link href="/admin/users" onClick={() => setMoreOpen(false)}>
                                    <span className="sheet-menu-icon"><Icon name="users" size={19}/></span>
                                    <div>
                                        <strong>Tim Account Executive</strong>
                                        <small>Kelola akun dan assignment Account Executive</small>
                                    </div>
                                    <Icon name="chevron" size={18}/>
                                </Link>
                            )}

                            <button onClick={() => { setMoreOpen(false); setNotificationOpen(true); }}>
                                <span className="sheet-menu-icon"><Icon name="bell" size={19}/></span>
                                <div>
                                    <strong>Notifikasi</strong>
                                    <small>{notifications?.unread_count || 0} notifikasi belum dibaca</small>
                                </div>
                                <Icon name="chevron" size={18}/>
                            </button>

                            <button onClick={logout}>
                                <span className="sheet-menu-icon danger"><Icon name="logout" size={19}/></span>
                                <div>
                                    <strong>Keluar</strong>
                                    <small>Akhiri sesi CRM</small>
                                </div>
                                <Icon name="chevron" size={18}/>
                            </button>
                        </div>
                    </section>
                </div>
            )}
        </div>
    );
}
