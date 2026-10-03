import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import EmptyState from '../components/common/EmptyState';
import PermissionGuard from '../components/common/PermissionGuard';
import Spinner from '../components/common/Spinner';
import { dashboardApi, apiErrorMessage } from '../modules/property/services/propertyApi';

function RealStatCard({ label, icon, value, loading }) {
  return (
    <div className="aprms-card">
      <div className="flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-slate-400">
        <span className="text-gold">{icon}</span>
        {label}
      </div>
      {loading ? (
        <div className="mt-3"><Spinner /></div>
      ) : (
        <p className="mt-3 text-3xl font-bold text-slate-100">{value}</p>
      )}
    </div>
  );
}

/**
 * Dashboard — real query-backed numbers from /api/v1/dashboard/stats.
 * Agency-scoped and portfolio-scoped by the backend. Zeros are honest zeros.
 */
export default function Dashboard() {
  const { user, hasPermission } = useAuth();
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    dashboardApi
      .stats()
      .then(setStats)
      .catch((err) => setError(apiErrorMessage(err, 'Could not load dashboard statistics.')))
      .finally(() => setLoading(false));
  }, []);

  const hasProperties = (stats?.total_properties || 0) > 0;

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-xl font-bold text-slate-100">
          Welcome back, {user?.name?.split(' ')[0] || 'there'}
        </h2>
        <p className="mt-1 text-sm text-slate-400">
          Live portfolio numbers — every figure below comes from the database, never fabricated.
        </p>
      </div>

      {error && (
        <div className="rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-300">
          {error}
        </div>
      )}

      <PermissionGuard permission="properties.view" showForbidden={false}>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <RealStatCard label="Properties" icon="🏘️" value={stats?.total_properties ?? 0} loading={loading} />
          <RealStatCard label="Buildings" icon="🏢" value={stats?.total_buildings ?? 0} loading={loading} />
          <RealStatCard label="Units" icon="🚪" value={stats?.total_units ?? 0} loading={loading} />
          <RealStatCard label="Vacant units" icon="🔑" value={stats?.vacant_units ?? 0} loading={loading} />
        </div>

        {!loading && !hasProperties && (
          <EmptyState
            icon="🏘️"
            title="No property data yet"
            hint="Add your first property to populate this dashboard with live portfolio numbers."
            action={
              hasPermission('properties.manage') ? (
                <Link to="/properties/new" className="aprms-btn-gold">+ New Property</Link>
              ) : undefined
            }
          />
        )}

        {!loading && hasProperties && (
          <div className="aprms-card">
            <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Units by status</h3>
            <div className="mt-3 flex flex-wrap gap-2">
              {Object.entries(stats.units_by_status || {}).map(([status, count]) => (
                <span
                  key={status}
                  className="inline-flex items-center gap-2 rounded-full bg-charcoal-800 px-3 py-1.5 text-sm text-slate-200 ring-1 ring-charcoal-700"
                >
                  <span className="capitalize text-slate-400">{status}</span>
                  <span className="font-bold text-gold">{count}</span>
                </span>
              ))}
            </div>
          </div>
        )}
      </PermissionGuard>

      <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <EmptyState
          icon="👥"
          title="No tenants yet"
          hint="Tenant onboarding ships with the leasing module in P3. This panel will show active tenancies and upcoming renewals."
        />
        <PermissionGuard permission="reports.view" showForbidden={false}>
          <EmptyState
            icon="📊"
            title="No financial records yet"
            hint="Rent cycles, collections and owner statements land in P4–P7. Reports will be generated from posted ledger entries only."
          />
        </PermissionGuard>
      </div>
    </div>
  );
}
