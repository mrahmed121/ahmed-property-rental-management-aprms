import { useState } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';

const NAV = [
  { to: '/', label: 'Dashboard', permission: null, end: true },
  { to: '/properties', label: 'Properties', permission: 'properties.view', end: false },
  { to: '/buildings', label: 'Buildings', permission: 'buildings.view', end: false },
  { to: '/units', label: 'Units', permission: 'units.view', end: false },
  { to: '/users', label: 'Users', permission: 'users.view', end: false },
];

function navClass({ isActive }) {
  return `flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${
    isActive
      ? 'bg-gold/15 text-gold'
      : 'text-gray-400 hover:bg-charcoal-800 hover:text-gray-200'
  }`;
}

export default function Layout() {
  const { user, logout, hasPermission } = useAuth();
  const navigate = useNavigate();
  const [menuOpen, setMenuOpen] = useState(false);
  const [sidebarOpen, setSidebarOpen] = useState(false);

  const visibleNav = NAV.filter((item) => hasPermission(item.permission));

  const handleLogout = async () => {
    await logout();
    navigate('/login', { replace: true });
  };

  const roleName =
    user?.role?.name ||
    user?.role?.slug?.replace(/_/g, ' ') ||
    '—';
  const agencyName = user?.agency?.name || 'APRMS';

  return (
    <div className="flex min-h-screen bg-charcoal-900">
      {/* Mobile sidebar backdrop */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-30 bg-black/60 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={`fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-charcoal-700 bg-charcoal-950 transition-transform lg:static lg:translate-x-0 ${
          sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <div className="border-b border-charcoal-700 px-5 py-5">
          <p className="text-lg font-bold tracking-wide text-gold">APRMS</p>
          <p className="mt-0.5 text-[11px] text-gray-500">
            Ahmed — Own Every Square Foot.
          </p>
        </div>

        <nav className="flex-1 space-y-1 overflow-y-auto p-4">
          {visibleNav.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              className={navClass}
              onClick={() => setSidebarOpen(false)}
            >
              {item.label}
            </NavLink>
          ))}
        </nav>

        <div className="border-t border-charcoal-700 p-4">
          <p className="text-center text-[11px] text-gray-600">
            Developed by Ahmed
          </p>
        </div>
      </aside>

      {/* Main column */}
      <div className="flex min-w-0 flex-1 flex-col">
        {/* Topbar */}
        <header className="flex items-center justify-between border-b border-charcoal-700 bg-charcoal-950/80 px-4 py-3 backdrop-blur lg:px-6">
          <div className="flex items-center gap-3">
            <button
              className="rounded-lg p-2 text-gray-400 hover:bg-charcoal-800 lg:hidden"
              onClick={() => setSidebarOpen(true)}
              aria-label="Open menu"
            >
              <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>
            <span className="text-sm font-medium text-gray-300">{agencyName}</span>
          </div>

          <div className="relative">
            <button
              onClick={() => setMenuOpen((v) => !v)}
              className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-300 hover:bg-charcoal-800"
              aria-haspopup="true"
              aria-expanded={menuOpen}
            >
              <span className="flex h-8 w-8 items-center justify-center rounded-full bg-gold/20 text-sm font-bold text-gold">
                {(user?.name || 'U').charAt(0).toUpperCase()}
              </span>
              <span className="hidden sm:block">
                <span className="block text-left font-medium">{user?.name}</span>
                <span className="block text-left text-xs capitalize text-gray-500">
                  {roleName}
                </span>
              </span>
            </button>
            {menuOpen && (
              <div className="absolute right-0 z-50 mt-2 w-48 rounded-lg border border-charcoal-600 bg-charcoal-800 py-1 shadow-xl">
                <div className="border-b border-charcoal-700 px-4 py-2">
                  <p className="truncate text-sm font-medium text-gray-200">{user?.name}</p>
                  <p className="truncate text-xs text-gray-500">{user?.email}</p>
                </div>
                <button
                  onClick={handleLogout}
                  className="block w-full px-4 py-2 text-left text-sm text-red-300 hover:bg-charcoal-700"
                >
                  Logout
                </button>
              </div>
            )}
          </div>
        </header>

        {/* Page content */}
        <main className="flex-1 p-4 lg:p-6">
          <Outlet />
        </main>

        {/* Footer */}
        <footer className="border-t border-charcoal-700 px-6 py-3 text-center text-[11px] text-gray-600">
          Developed by Ahmed
        </footer>
      </div>
    </div>
  );
}
