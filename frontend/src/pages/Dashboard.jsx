import { useAuth } from '../context/AuthContext';
import StatCard from '../components/common/StatCard';
import EmptyState from '../components/common/EmptyState';
import PermissionGuard from '../components/common/PermissionGuard';

/**
 * P1 Dashboard shell. HONEST EMPTY STATES ONLY — no fake KPIs.
 * P2+ modules will replace each panel with query-backed widgets.
 */
export default function Dashboard() {
  const { user } = useAuth();

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-xl font-bold text-slate-100">
          Welcome back, {user?.name?.split(' ')[0] || 'there'}
        </h2>
        <p className="mt-1 text-sm text-slate-400">
          Foundation is live. Portfolio modules arrive in P2 — every number here will be
          query-backed, never fabricated.
        </p>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Occupancy rate" icon="🏠" />
        <StatCard label="Rent collected (MTD)" icon="💰" />
        <StatCard label="Overdue invoices" icon="🧾" />
        <StatCard label="Open work orders" icon="🔧" />
      </div>

      <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <EmptyState
          icon="🏘️"
          title="No property data yet"
          hint="Properties, buildings and units are managed in P2. The portfolio module will populate this panel with live occupancy."
        />
        <EmptyState
          icon="👥"
          title="No tenants yet"
          hint="Tenant onboarding ships with the leasing module in P3. This panel will show active tenancies and upcoming renewals."
        />
      </div>

      <PermissionGuard permission="reports.view" showForbidden={false}>
        <EmptyState
          icon="📊"
          title="No financial records yet"
          hint="Rent cycles, collections and owner statements land in P4–P7. Reports will be generated from posted ledger entries only."
        />
      </PermissionGuard>
    </div>
  );
}
