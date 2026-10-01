import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import Icon from '../Components/Icon';
import Flash from '../Components/Flash';
import { Avatar } from '../Components/Ui';

const workspaceNav = [
    { href: '/dashboard', label: 'Dashboard', icon: 'home', match: (u) => u.startsWith('/dashboard') },
    { href: '/prospects', label: 'Prospek', icon: 'building', match: (u) => u.startsWith('/prospects') },
    { href: '/opportunities', label: 'Opportunities', icon: 'target', match: (u) => u.startsWith('/opportunities') },
    { href: '/quotations', label: 'Quotations', icon: 'briefcase', match: (u) => u.startsWith('/quotations') },
    { href: '/deals', label: 'Deals', icon: 'check', match: (u) => u.startsWith('/deals') },
    { href: '/finance', label: 'Finance', icon: 'briefcase', match: (u) => u.startsWith('/finance') },
];

const executionNav = [
    { href: '/follow-ups', label: 'Follow Up', icon: 'whatsapp', match: (u) => u.startsWith('/follow-ups') },
    { href: '/pipeline', label: 'Pipeline', icon: 'pipeline', match: (u) => u.startsWith('/pipeline') },
    { href: '/targets', label: 'Target AE', icon: 'target', match: (u) => u.startsWith('/targets') },
    { href: '/sales-guide', label: 'Panduan', icon: 'spark', match: (u) => u.startsWith('/sales-guide') },
];

function NavLink({ item, url, mobile = false, onClick }) {
    const active = item.match(url);
    return (
        <Link
            href={item.href}
            onClick={onClick}
            className={`${mobile ? 'bottom-link' : 'side-link'} ${active ? 'active' : ''}`}
        >
            <span className={mobile ? 'bottom-icon-wrap' : 'side-link-icon'}>
                <Icon name={item.icon} size={mobile ? 20 : 18}/>
            </span>
            <span className={mobile ? '' : 'side-link-label'}>{item.label}</span>
            {!mobile && active && <span className="side-link-indicator"/>}
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
        <div className="notification-popover premium-notification-popover">
            <div className="notification-head">
                <div>
                    <span className="eyebrow">Inbox</span>
                    <strong>Notifikasi</strong>
                    <small>{unread ? `${unread} belum dibaca` : 'Semua sudah dibaca'}</small>
                </div>
                {unread > 0 && <button onClick={readAll}>Tandai semua</button>}
            </div>

            <div className="notification-list">
                {items.length ? items.map((item) => (
                    <button
                        key={item.id}
                        className={`notification-item ${item.read_at ? '' : 'unread'}`}
                        onClick={() => openNotification(item)}
                    >
                        <span className="notification-item-icon"><Icon name="bell" size={16}/></span>
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
                        <span className="empty-icon"><Icon name="bell" size={22}/></span>
                        <strong>Inbox bersih</strong>
                        <small>Update assignment dan aktivitas penting akan muncul di sini.</small>
                    </div>
                )}
            </div>
        </div>
    );
}

function MobileMenuGroup({ label, items, url, onClose }) {
    return (
        <div className="mobile-menu-group">
            <p className="mobile-sidebar-label">{label}</p>
            {items.map((item) => (
                <Link
                    key={item.href}
                    href={item.href}
                    onClick={onClose}
                    className={`mobile-sidebar-link ${item.match(url) ? 'active' : ''}`}
                >
                    <span className="mobile-sidebar-link-icon"><Icon name={item.icon} size={18}/></span>
                    <span className="mobile-sidebar-link-copy"><strong>{item.label}</strong></span>
                    <Icon name="chevron" size={15}/>
                </Link>
            ))}
        </div>
    );
}

function MobileSidebar({ user, url, notifications, open, onClose, onNotifications, onLogout }) {
    const adminItems = [
        { href: '/imports', label: 'Import Data', icon: 'upload', match: (u) => u.startsWith('/imports') },
        { href: '/admin/users', label: 'Tim & Access', icon: 'users', match: (u) => u.startsWith('/admin/users') },
        { href: '/products', label: 'Product Catalog', icon: 'briefcase', match: (u) => u.startsWith('/products') },
        { href: '/campaigns', label: 'WA Campaign', icon: 'whatsapp', match: (u) => u.startsWith('/campaigns') },
        { href: '/settings/whatsapp', label: 'WhatsApp API', icon: 'whatsapp', match: (u) => u.startsWith('/settings/whatsapp') },
        { href: '/settings/business', label: 'Business Config', icon: 'spark', match: (u) => u.startsWith('/settings/business') },
        ...(user.is_platform_admin ? [{ href: '/admin/clients', label: 'Client Onboarding', icon: 'users', match: (u) => u.startsWith('/admin/clients') }] : []),
    ];

    return (
        <div className={`mobile-sidebar-backdrop ${open ? 'is-open' : ''}`} aria-hidden={!open} onClick={onClose}>
            <aside className="mobile-sidebar-drawer premium-mobile-drawer" onClick={(e) => e.stopPropagation()}>
                <div className="mobile-sidebar-head">
                    <div className="mobile-sidebar-brand">
                        <img className="sv-brand-logo sm" src="/images/brand/santovate-crm-logo.png" alt="Santovate CRM"/>
                        <div>
                            <strong>Santovate</strong>
                            <small>Sales Operating System</small>
                        </div>
                    </div>
                    <button className="mobile-sidebar-close" onClick={onClose} aria-label="Tutup menu">
                        <Icon name="close" size={19}/>
                    </button>
                </div>

                <div className="mobile-sidebar-user">
                    <Avatar name={user.name} email={user.email} initialsText={user.profile_initials}/>
                    <div>
                        <strong>{user.name}</strong>
                        <small>{user.job_title || (user.is_admin ? 'Administrator' : user.is_finance ? 'Finance' : 'Account Executive')}</small>
                    </div>
                </div>

                <nav className="mobile-sidebar-nav">
                    <MobileMenuGroup label="Workspace" items={workspaceNav} url={url} onClose={onClose}/>
                    <MobileMenuGroup label="Execution" items={executionNav} url={url} onClose={onClose}/>
                    {user.is_admin && <MobileMenuGroup label="Management" items={adminItems} url={url} onClose={onClose}/>} 

                    <div className="mobile-menu-group">
                        <p className="mobile-sidebar-label">Account</p>
                        <Link href="/profile" onClick={onClose} className={`mobile-sidebar-link ${url.startsWith('/profile') ? 'active' : ''}`}>
                            <span className="mobile-sidebar-link-icon"><Icon name="users" size={18}/></span>
                            <span className="mobile-sidebar-link-copy"><strong>Profile</strong></span>
                            <Icon name="chevron" size={15}/>
                        </Link>
                        <button
                            type="button"
                            className="mobile-sidebar-link"
                            onClick={() => {
                                onClose();
                                onNotifications();
                            }}
                        >
                            <span className="mobile-sidebar-link-icon"><Icon name="bell" size={18}/></span>
                            <span className="mobile-sidebar-link-copy"><strong>Notifikasi</strong></span>
                            {(notifications?.unread_count || 0) > 0 && <span className="mobile-sidebar-badge">{notifications.unread_count > 9 ? '9+' : notifications.unread_count}</span>}
                        </button>
                    </div>
                </nav>

                <div className="mobile-sidebar-footer">
                    <button type="button" className="mobile-sidebar-logout" onClick={onLogout}>
                        <span><Icon name="logout" size={18}/></span>
                        <div><strong>Keluar</strong><small>Akhiri sesi dengan aman</small></div>
                    </button>
                </div>
            </aside>
        </div>
    );
}

function SidebarGroup({ label, items, url }) {
    return (
        <div className="sidebar-group">
            <p className="nav-label">{label}</p>
            <div className="sidebar-group-links">
                {items.map((item) => <NavLink key={item.href} item={item} url={url}/>) }
            </div>
        </div>
    );
}

export default function AppLayout({ children, title, subtitle, action }) {
    const page = usePage();
    const { auth, notifications } = page.props;
    const url = page.url;
    const user = auth.user;
    const routeKey = url.split('?')[0];

    const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);
    const [notificationOpen, setNotificationOpen] = useState(false);

    const adminNav = useMemo(() => [
        { href: '/imports', label: 'Import Data', icon: 'upload', match: (u) => u.startsWith('/imports') },
        { href: '/admin/users', label: 'Tim & Access', icon: 'users', match: (u) => u.startsWith('/admin/users') },
        { href: '/products', label: 'Products', icon: 'briefcase', match: (u) => u.startsWith('/products') },
        { href: '/campaigns', label: 'WA Campaign', icon: 'whatsapp', match: (u) => u.startsWith('/campaigns') },
        { href: '/settings/whatsapp', label: 'WhatsApp API', icon: 'whatsapp', match: (u) => u.startsWith('/settings/whatsapp') },
        { href: '/settings/business', label: 'Business Config', icon: 'spark', match: (u) => u.startsWith('/settings/business') },
        ...(user.is_platform_admin ? [{ href: '/admin/clients', label: 'Client Onboarding', icon: 'users', match: (u) => u.startsWith('/admin/clients') }] : []),
    ], [user.is_platform_admin]);

    const allNav = [...workspaceNav, ...executionNav, ...(user.is_admin ? adminNav : [])];
    const activeItem = allNav.find((item) => item.match(url));
    const sectionLabel = activeItem
        ? (workspaceNav.some((item) => item.href === activeItem.href) ? 'Workspace' : executionNav.some((item) => item.href === activeItem.href) ? 'Execution' : 'Management')
        : 'Account';

    const logout = () => router.post('/logout');

    useEffect(() => {
        setMobileSidebarOpen(false);
        setNotificationOpen(false);
    }, [routeKey]);

    useEffect(() => {
        if (!mobileSidebarOpen) return undefined;
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => { document.body.style.overflow = previous; };
    }, [mobileSidebarOpen]);

    return (
        <div className="app-shell premium-app-shell">
            <aside className="sidebar premium-sidebar">
                <div className="brand premium-brand">
                    <img className="sv-brand-logo" src="/images/brand/santovate-crm-logo.png" alt="Santovate CRM"/>
                    <div className="brand-copy">
                        <strong>Santovate</strong>
                        <small>Sales Operating System</small>
                    </div>
                </div>

                <div className="workspace-chip">
                    <span className="workspace-chip-dot"/>
                    <div>
                        <small>Workspace</small>
                        <strong>{user.organization_name || 'Santovate CRM'}</strong>
                    </div>
                </div>

                <nav className="sidebar-nav premium-sidebar-nav">
                    <SidebarGroup label="Workspace" items={workspaceNav} url={url}/>
                    <SidebarGroup label="Execution" items={executionNav} url={url}/>
                    {user.is_admin && <SidebarGroup label="Management" items={adminNav} url={url}/>} 
                </nav>

                <div className="sidebar-user premium-sidebar-user">
                    <Link href="/profile" className="sidebar-profile-link">
                        <Avatar name={user.name} email={user.email} initialsText={user.profile_initials}/>
                        <div className="sidebar-user-copy">
                            <strong>{user.name}</strong>
                            <small>{user.job_title || (user.is_admin ? 'Administrator' : user.is_finance ? 'Finance' : 'Account Executive')}</small>
                        </div>
                    </Link>
                    <button className="icon-button ghost" onClick={logout} title="Keluar" aria-label="Keluar">
                        <Icon name="logout" size={17}/>
                    </button>
                </div>
            </aside>

            <main className="main-area premium-main-area">
                <header className="topbar premium-topbar">
                    <div className="mobile-brand">
                        <button type="button" className="mobile-menu-trigger" onClick={() => setMobileSidebarOpen(true)} aria-label="Buka menu">
                            <Icon name="menu" size={19}/>
                        </button>
                        <img className="sv-brand-logo sm" src="/images/brand/santovate-crm-logo.png" alt="Santovate CRM"/>
                        <strong>Santovate</strong>
                    </div>

                    <div className="page-heading premium-page-heading">
                        <span className="page-kicker">{sectionLabel}{activeItem ? ` / ${activeItem.label}` : ''}</span>
                        <h1>{title}</h1>
                        {subtitle && <p>{subtitle}</p>}
                    </div>

                    <div className="topbar-tools premium-topbar-tools">
                        {action && <div className="topbar-action">{action}</div>}
                        <div className="notification-center-wrap">
                            <button
                                className={`notification-trigger ${notificationOpen ? 'active' : ''}`}
                                onClick={() => setNotificationOpen(!notificationOpen)}
                                aria-label="Notifikasi"
                            >
                                <Icon name="bell" size={18}/>
                                {(notifications?.unread_count || 0) > 0 && <span>{notifications.unread_count > 9 ? '9+' : notifications.unread_count}</span>}
                            </button>
                            {notificationOpen && <NotificationCenter notifications={notifications} onClose={() => setNotificationOpen(false)}/>} 
                        </div>
                    </div>
                </header>

                <div className="page-content premium-page-content">
                    <div key={routeKey} className="route-stage premium-route-stage">
                        <Flash/>
                        {children}
                    </div>
                </div>
            </main>

            <nav className={`bottom-nav premium-bottom-nav ${mobileSidebarOpen ? 'is-hidden' : ''}`} aria-label="Navigasi utama mobile">
                {[workspaceNav[0], workspaceNav[1], executionNav[0], executionNav[1]].map((item) => <NavLink key={item.href} item={item} url={url} mobile/>)}
                <button className={`bottom-link ${mobileSidebarOpen ? 'active' : ''}`} onClick={() => setMobileSidebarOpen(true)}>
                    <span className="bottom-icon-wrap"><Icon name="more" size={20}/></span>
                    <span>Lainnya</span>
                </button>
            </nav>

            <MobileSidebar
                user={user}
                url={url}
                notifications={notifications}
                open={mobileSidebarOpen}
                onClose={() => setMobileSidebarOpen(false)}
                onNotifications={() => setNotificationOpen(true)}
                onLogout={logout}
            />
        </div>
    );
}

