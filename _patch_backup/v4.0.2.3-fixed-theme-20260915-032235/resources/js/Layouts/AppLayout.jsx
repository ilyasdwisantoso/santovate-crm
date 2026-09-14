import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '../Components/Icon';
import Flash from '../Components/Flash';
import { Avatar } from '../Components/Ui';

const nav = [
    { href: '/dashboard', label: 'Dashboard', icon: 'home', match: (u) => u.startsWith('/dashboard') },
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

function MobileSidebar({ user, url, notifications, onClose, onNotifications, onLogout }) {
    const menu = [
        ...nav,
        {
            href: '/profile',
            label: 'Profile',
            icon: 'users',
            description: 'Update data akun dan Account Executive',
            match: (u) => u.startsWith('/profile'),
        },
    ];

    return (
        <div className="mobile-sidebar-backdrop" onClick={onClose}>
            <aside className="mobile-sidebar-drawer" onClick={(e) => e.stopPropagation()}>
                <div className="mobile-sidebar-head">
                    <div className="mobile-sidebar-brand">
                        <span className="brand-mark sm">S</span>
                        <div>
                            <strong>Santovate</strong>
                            <small>{organization?.business_configuration?.name || 'CRM Account Executive'}</small>
                        </div>
                    </div>
                    <button className="mobile-sidebar-close" onClick={onClose} aria-label="Tutup menu">
                        <Icon name="close" size={20}/>
                    </button>
                </div>

                <div className="mobile-sidebar-user">
                    <Avatar
                        name={user.name}
                        email={user.email}
                        initialsText={user.profile_initials}
                    />
                    <div>
                        <strong>{user.name}</strong>
                        <small>{user.job_title || (user.is_admin ? 'Administrator' : 'Account Executive')}</small>
                    </div>
                </div>

                <nav className="mobile-sidebar-nav">
                    <p className="mobile-sidebar-label">Workspace</p>
                    {menu.map((item, index) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            onClick={onClose}
                            className={`mobile-sidebar-link mobile-tone-${(index % 5) + 1} ${item.match(url) ? 'active' : ''}`}
                        >
                            <span className="mobile-sidebar-link-icon">
                                <Icon name={item.icon} size={19}/>
                            </span>
                            <span className="mobile-sidebar-link-copy">
                                <strong>{item.label}</strong>
                                {item.description && <small>{item.description}</small>}
                            </span>
                            <Icon name="chevron" size={17}/>
                        </Link>
                    ))}

                    {user.is_admin && (
                        <>
                            <p className="mobile-sidebar-label">Data & Team</p>
                            <Link href="/imports" onClick={onClose} className={`mobile-sidebar-link mobile-tone-2 ${url.startsWith('/imports') ? 'active' : ''}`}>
                                <span className="mobile-sidebar-link-icon"><Icon name="upload" size={19}/></span>
                                <span className="mobile-sidebar-link-copy">
                                    <strong>Import Data</strong>
                                    <small>Upload dan assign database prospek</small>
                                </span>
                                <Icon name="chevron" size={17}/>
                            </Link>
                            <Link href="/admin/users" onClick={onClose} className={`mobile-sidebar-link mobile-tone-4 ${url.startsWith('/admin/users') ? 'active' : ''}`}>
                                <span className="mobile-sidebar-link-icon"><Icon name="users" size={19}/></span>
                                <span className="mobile-sidebar-link-copy">
                                    <strong>Tim Account Executive</strong>
                                    <small>Kelola user dan assignment AE</small>
                                </span>
                                <Icon name="chevron" size={17}/>
                            </Link>
                        </>
                    )}

                    <p className="mobile-sidebar-label">Account</p>
                    <button
                        type="button"
                        className="mobile-sidebar-link mobile-tone-3"
                        onClick={() => {
                            onClose();
                            onNotifications();
                        }}
                    >
                        <span className="mobile-sidebar-link-icon"><Icon name="bell" size={19}/></span>
                        <span className="mobile-sidebar-link-copy">
                            <strong>Notifikasi</strong>
                            <small>{notifications?.unread_count || 0} notifikasi belum dibaca</small>
                        </span>
                        {(notifications?.unread_count || 0) > 0 && (
                            <span className="mobile-sidebar-badge">
                                {notifications.unread_count > 9 ? '9+' : notifications.unread_count}
                            </span>
                        )}
                    </button>

                    <Link href="/" onClick={onClose} className="mobile-sidebar-link mobile-tone-5">
                        <span className="mobile-sidebar-link-icon"><Icon name="home" size={19}/></span>
                        <span className="mobile-sidebar-link-copy">
                            <strong>Landing Page</strong>
                            <small>Kembali ke halaman utama Santovate CRM</small>
                        </span>
                        <Icon name="chevron" size={17}/>
                    </Link>
                                    {user.is_admin && <>
                        <p className="mobile-sidebar-label">SaaS & Automation</p>
                        <Link href="/products" onClick={onClose} className={`mobile-sidebar-link mobile-tone-2 ${url.startsWith('/products') ? 'active' : ''}`}><span className="mobile-sidebar-link-icon"><Icon name="briefcase" size={19}/></span><span className="mobile-sidebar-link-copy"><strong>Product Catalog</strong><small>Produk, varian, harga dan gambar</small></span><Icon name="chevron" size={17}/></Link>
                        <Link href="/campaigns" onClick={onClose} className={`mobile-sidebar-link mobile-tone-3 ${url.startsWith('/campaigns') ? 'active' : ''}`}><span className="mobile-sidebar-link-icon"><Icon name="whatsapp" size={19}/></span><span className="mobile-sidebar-link-copy"><strong>WA Campaign</strong><small>Campaign untuk kontak opt-in</small></span><Icon name="chevron" size={17}/></Link>
                        <Link href="/settings/whatsapp" onClick={onClose} className={`mobile-sidebar-link mobile-tone-4 ${url.startsWith('/settings/whatsapp') ? 'active' : ''}`}><span className="mobile-sidebar-link-icon"><Icon name="whatsapp" size={19}/></span><span className="mobile-sidebar-link-copy"><strong>WhatsApp API</strong><small>Meta Cloud API channel</small></span><Icon name="chevron" size={17}/></Link>
                        <Link href="/settings/business" onClick={onClose} className={`mobile-sidebar-link mobile-tone-5 ${url.startsWith('/settings/business') ? 'active' : ''}`}><span className="mobile-sidebar-link-icon"><Icon name="spark" size={19}/></span><span className="mobile-sidebar-link-copy"><strong>Business Config</strong><small>Pipeline dan workflow aktif</small></span><Icon name="chevron" size={17}/></Link>
                    </>}
</nav>

                <div className="mobile-sidebar-footer">
                    <button type="button" className="mobile-sidebar-logout" onClick={onLogout}>
                        <span><Icon name="logout" size={19}/></span>
                        <div>
                            <strong>Keluar</strong>
                            <small>Akhiri sesi CRM</small>
                        </div>
                    </button>
                </div>
            </aside>
        </div>
    );
}

export default function AppLayout({ children, title, subtitle, action }) {
    const page = usePage();
    const { auth, notifications, organization } = page.props;
    const url = page.url;
    const user = auth.user;
    const businessTheme = organization?.business_configuration?.theme || null;
    const themeStyle = businessTheme ? {
        '--business-primary': businessTheme.primary || '#10382b',
        '--business-secondary': businessTheme.secondary || '#1d5a45',
        '--business-accent': businessTheme.accent || '#9bc653',
        '--business-soft': businessTheme.soft || '#eef7df',
        '--business-surface': businessTheme.surface || '#f8faf9',
    } : undefined;

    const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);
    const [notificationOpen, setNotificationOpen] = useState(false);

    const logout = () => router.post('/logout');

    return (
        <div className={`app-shell ${businessTheme ? 'business-themed' : ''}`} style={themeStyle}>
            <aside className="sidebar">
                <div className="brand">
                    <span className="brand-mark">S</span>
                    <div>
                        <strong>Santovate</strong>
                        <small>{organization?.business_configuration?.name || 'CRM Account Executive'}</small>
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
                            <NavLink item={{ href: '/products', label: 'Product Catalog', icon: 'briefcase', match: (u) => u.startsWith('/products') }} url={url}/>
                            <NavLink item={{ href: '/campaigns', label: 'WA Campaign', icon: 'whatsapp', match: (u) => u.startsWith('/campaigns') }} url={url}/>
                            <NavLink item={{ href: '/settings/whatsapp', label: 'WhatsApp API', icon: 'whatsapp', match: (u) => u.startsWith('/settings/whatsapp') }} url={url}/>
                            <NavLink item={{ href: '/settings/business', label: 'Business Config', icon: 'spark', match: (u) => u.startsWith('/settings/business') }} url={url}/>
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
                        <button
                            type="button"
                            className="mobile-menu-trigger"
                            onClick={() => setMobileSidebarOpen(true)}
                            aria-label="Buka menu"
                        >
                            <Icon name="menu" size={20}/>
                        </button>
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

            <nav
                className={`bottom-nav ${mobileSidebarOpen ? 'is-hidden' : ''}`}
                aria-label="Navigasi utama mobile"
            >
                {nav
                    .filter((item) => ['/dashboard', '/prospects', '/follow-ups', '/pipeline'].includes(item.href))
                    .map((item) => <NavLink key={item.href} item={item} url={url} mobile/>)}

                <button
                    className={`bottom-link ${mobileSidebarOpen ? 'active' : ''}`}
                    onClick={() => setMobileSidebarOpen(true)}
                >
                    <span className="bottom-icon-wrap"><Icon name="more" size={21}/></span>
                    <span>Lainnya</span>
                </button>
            </nav>

            {mobileSidebarOpen && (
                <MobileSidebar
                    user={user}
                    url={url}
                    notifications={notifications}
                    onClose={() => setMobileSidebarOpen(false)}
                    onNotifications={() => setNotificationOpen(true)}
                    onLogout={logout}
                />
            )}
        </div>
    );
}


