import { NavLink } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';

// P1 navigation + P2 property module. Each item declares the permission that reveals it.
// Future modules (P3+) append their items here with their own permissions.
const NAV_GROUPS = [
  {
    label: 'Overview',
    items: [{ to: '/', label: 'Dashboard', icon: '◈', permission: 'dashboard.view' }],
  },
  {
    label: 'Portfolio',
    items: [{ to: '/properties', label: 'Properties', icon: '🏘️', permission: 'properties.view' }],
  },
  {
    label: 'Leasing',
    items: [
      { to: '/tenants', label: 'Tenants', icon: '🧑‍💼', permission: 'tenants.view' },
      { to: '/applications', label: 'Applications', icon: '📝', permission: 'applications.view' },
      { to: '/leases', label: 'Leases', icon: '📄', permission: 'leases.view' },
      { to: '/inspections', label: 'Inspections', icon: '🔍', permission: 'inspections.view' },
    ],
  },
  {
    label: 'Billing',
    items: [
      { to: '/billing', label: 'Overview', icon: '💰', permission: 'billing.view' },
      { to: '/billing/invoices', label: 'Invoices', icon: '🧾', permission: 'invoices.view' },
      { to: '/billing/payments', label: 'Payments', icon: '💳', permission: 'payments.view' },
      { to: '/billing/arrears', label: 'Arrears', icon: '⚠️', permission: 'billing.view' },
      { to: '/billing/dunning', label: 'Dunning', icon: '📬', permission: 'dunning.view' },
    ],
  },
  {
    label: 'Deposits',
    items: [
      { to: '/deposits', label: 'Deposits', icon: '🔐', permission: 'deposits.view' },
    ],
  },
  {
    label: 'Maintenance',
    items: [
      { to: '/maintenance', label: 'Overview', icon: '🔧', permission: 'maintenance.view' },
      { to: '/maintenance/tickets', label: 'Tickets', icon: '🎫', permission: 'maintenance.view' },
      { to: '/maintenance/vendors', label: 'Vendors', icon: '🏭', permission: 'vendors.view' },
    ],
  },
  {
    label: 'Utilities',
    items: [
      { to: '/utilities/meters', label: 'Meters', icon: '⚡', permission: 'utilities.view' },
      { to: '/utilities/bills', label: 'Bills', icon: '🧾', permission: 'utilities.view' },
    ],
  },
  {
    label: 'Expenses',
    items: [
      { to: '/expenses', label: 'Expenses', icon: '💸', permission: 'expenses.view' },
      { to: '/expenses/approvals', label: 'Approvals', icon: '✅', permission: 'expenses.approve' },
    ],
  },
  {
    label: 'Administration',
    items: [
      { to: '/users', label: 'Users & Roles', icon: '👥', permission: 'users.view' },
      { to: '/audit-logs', label: 'Audit Logs', icon: '📜', permission: 'audit.view' },
      { to: '/settings', label: 'Settings', icon: '⚙', permission: 'settings.view' },
    ],
  },
];

export default function Sidebar({ open, onClose }) {
  const { hasPermission, user } = useAuth();

  return (
    <>
      {open && (
        <div
          className="fixed inset-0 z-30 bg-black/60 lg:hidden"
          onClick={onClose}
          aria-hidden="true"
        />
      )}
      <aside
        className={`fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-charcoal-700/60 bg-charcoal-900/95 backdrop-blur transition-transform lg:static lg:translate-x-0 ${
          open ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <div className="flex items-center gap-3 border-b border-charcoal-700/60 px-5 py-4">
          <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-gold/15 text-xl text-gold ring-1 ring-gold/40">
            🏢
          </div>
          <div>
            <p className="text-sm font-bold tracking-wide text-slate-100">APRMS</p>
            <p className="text-[11px] text-slate-400">Property & Rental Mgmt</p>
          </div>
        </div>

        <nav className="flex-1 overflow-y-auto px-3 py-4">
          {NAV_GROUPS.map((group) => {
            const visible = group.items.filter((i) => hasPermission(i.permission));
            if (!visible.length) return null;
            return (
              <div key={group.label} className="mb-5">
                <p className="mb-2 px-3 text-[11px] font-semibold uppercase tracking-widest text-slate-500">
                  {group.label}
                </p>
                <ul className="space-y-1">
                  {visible.map((item) => (
                    <li key={item.to}>
                      <NavLink
                        to={item.to}
                        onClick={onClose}
                        className={({ isActive }) =>
                          `flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition ${
                            isActive
                              ? 'bg-gold/15 text-gold ring-1 ring-gold/30'
                              : 'text-slate-300 hover:bg-charcoal-800 hover:text-slate-100'
                          }`
                        }
                      >
                        <span className="text-base">{item.icon}</span>
                        {item.label}
                      </NavLink>
                    </li>
                  ))}
                </ul>
              </div>
            );
          })}
        </nav>

        <div className="border-t border-charcoal-700/60 px-5 py-4">
          <p className="truncate text-sm font-medium text-slate-200">{user?.name}</p>
          <p className="truncate text-xs text-slate-500">{(user?.roles || []).join(', ')}</p>
          <p className="mt-2 text-[11px] text-slate-600">Developed by Ahmed</p>
        </div>
      </aside>
    </>
  );
}
