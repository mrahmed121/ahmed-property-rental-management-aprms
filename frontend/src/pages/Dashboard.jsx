import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import EmptyState from '../components/common/EmptyState';
import PermissionGuard from '../components/common/PermissionGuard';
import Spinner from '../components/common/Spinner';
import { dashboardApi, apiErrorMessage } from '../modules/property/services/propertyApi';
import { financialApi, formatPKR } from '../modules/billing/services/billingApi';

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
  const [financial, setFinancial] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    Promise.all([
      dashboardApi.stats().then(setStats),
      financialApi.dashboard().then(setFinancial).catch(() => {}),
    ])
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
        <PermissionGuard permission="tenants.view" showForbidden={false}>
          <div className="aprms-card">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Leasing</h3>
              <Link to="/leases" className="text-xs text-gold hover:text-gold-light">Open leases →</Link>
            </div>
            {loading ? (
              <div className="mt-3"><Spinner /></div>
            ) : (
              <div className="mt-4 grid grid-cols-3 gap-3">
                <div>
                  <p className="text-2xl font-bold text-slate-100">{stats?.total_tenants ?? 0}</p>
                  <p className="text-xs text-slate-500">Tenants</p>
                </div>
                <div>
                  <p className="text-2xl font-bold text-gold">{stats?.active_leases ?? 0}</p>
                  <p className="text-xs text-slate-500">Active leases</p>
                </div>
                <div>
                  <p className="text-2xl font-bold text-slate-100">{stats?.leases_expiring_soon ?? 0}</p>
                  <p className="text-xs text-slate-500">Expiring ≤ 60 days</p>
                </div>
              </div>
            )}
            {!loading && (stats?.total_tenants || 0) === 0 && (
              <p className="mt-3 text-sm text-slate-500">
                No tenants yet — add tenants as they apply, then run applications and leases from the Leasing menu.
              </p>
            )}
            {!loading && Object.keys(stats?.leases_by_status || {}).length > 0 && (
              <div className="mt-4 flex flex-wrap gap-2 border-t border-charcoal-700/60 pt-3">
                {Object.entries(stats.leases_by_status || {}).map(([status, count]) => (
                  <span
                    key={status}
                    className="inline-flex items-center gap-2 rounded-full bg-charcoal-800 px-3 py-1.5 text-sm text-slate-200 ring-1 ring-charcoal-700"
                  >
                    <span className="capitalize text-slate-400">{status}</span>
                    <span className="font-bold text-gold">{count}</span>
                  </span>
                ))}
              </div>
            )}
          </div>
        </PermissionGuard>
        <PermissionGuard permission="billing.view" showForbidden={false}>
          <div className="aprms-card">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Money In</h3>
              <Link to="/billing" className="text-xs text-gold hover:text-gold-light">Financial overview →</Link>
            </div>
            {loading ? (
              <div className="mt-3"><Spinner /></div>
            ) : financial ? (
              <div className="mt-4 grid grid-cols-2 gap-3">
                <div>
                  <p className="text-xl font-bold text-slate-100">{formatPKR(financial.collected_this_period)}</p>
                  <p className="text-xs text-slate-500">Collected this period</p>
                </div>
                <div>
                  <p className="text-xl font-bold text-red-300">{formatPKR(financial.outstanding)}</p>
                  <p className="text-xs text-slate-500">Outstanding</p>
                </div>
                <div>
                  <p className="text-xl font-bold text-slate-100">{formatPKR(financial.overdue)}</p>
                  <p className="text-xs text-slate-500">Overdue</p>
                </div>
                <div>
                  <p className="text-xl font-bold text-gold">
                    {financial.collection_rate === null ? '—' : `${financial.collection_rate}%`}
                  </p>
                  <p className="text-xs text-slate-500">Collection rate</p>
                </div>
              </div>
            ) : (
              <p className="mt-3 text-sm text-slate-500">No financial data yet.</p>
            )}
          </div>
        </PermissionGuard>
      </div>
    </div>
  );
}
