import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import Card from '../shared/Card';
import EmptyState from '../shared/EmptyState';
import ErrorAlert from '../shared/ErrorAlert';
import Spinner from '../shared/Spinner';
import PermissionGuard from '../components/common/PermissionGuard';
import StatusBadge from '../modules/property/components/StatusBadge';
import { dashboardApi } from '../modules/property/services/propertyApi';

function Kpi({ title, value, loading, link, linkLabel }) {
  return (
    <Card title={title}>
      {loading ? (
        <div className="h-9 animate-pulse rounded-lg bg-charcoal-700" />
      ) : (
        <>
          <p className="text-3xl font-bold tabular-nums text-gold">{value}</p>
          {link && (
            <Link to={link} className="mt-2 inline-block text-sm text-gray-400 hover:text-gold hover:underline">
              {linkLabel} →
            </Link>
          )}
        </>
      )}
    </Card>
  );
}

/**
 * Dashboard with REAL query-backed KPIs from GET /dashboard/stats.
 * Shows honest empty states when the portfolio has no records.
 */
export default function Dashboard() {
  const { user, hasPermission, initialized } = useAuth();
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const canView = hasPermission('dashboard.view') || hasPermission('properties.view');

  const load = async () => {
    if (!canView) {
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      setStats(await dashboardApi.stats());
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load dashboard.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (initialized) load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initialized]);

  const agencyName = user?.agency?.name || 'your agency';
  const hasPortfolio = (stats?.total_properties ?? 0) > 0;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-100">Dashboard</h1>
        <p className="mt-1 text-sm text-gray-500">
          Welcome back, {user?.name}. This is {agencyName}&rsquo;s portfolio overview.
        </p>
      </div>

      <ErrorAlert message={error} onRetry={load} />

      {!error && !hasPortfolio && !loading && (
        <EmptyState
          title="No data yet"
          message="Onboard your first property to start seeing portfolio metrics here."
          action={<Link to="/properties" className="font-medium text-gold hover:underline">Go to Properties →</Link>}
        />
      )}

      {(loading || hasPortfolio) && (
        <>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Kpi title="Properties" value={stats?.total_properties ?? 0} loading={loading} link="/properties" linkLabel="View all" />
            <Kpi title="Buildings" value={stats?.total_buildings ?? 0} loading={loading} link="/buildings" linkLabel="View all" />
            <Kpi title="Units" value={stats?.total_units ?? 0} loading={loading} link="/units" linkLabel="View all" />
            <Kpi
              title="Occupied units"
              value={stats?.occupied_units ?? 0}
              loading={loading}
              link="/units?status=occupied"
              linkLabel="View occupied"
            />
          </div>

          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Kpi title="Vacant units" value={stats?.vacant_units ?? 0} loading={loading} link="/units" linkLabel="View units" />
            <Card title="Units by status">
              {loading ? (
                <div className="h-9 animate-pulse rounded-lg bg-charcoal-700" />
              ) : stats?.units_by_status && Object.keys(stats.units_by_status).length > 0 ? (
                <div className="flex flex-wrap gap-2">
                  {Object.entries(stats.units_by_status).map(([s, n]) => (
                    <span key={s} className="flex items-center gap-2 rounded-lg bg-charcoal-700/60 px-3 py-1.5 text-sm">
                      <StatusBadge status={s} />
                      <span className="font-semibold tabular-nums text-gray-200">{n}</span>
                    </span>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-gray-500">No unit data yet.</p>
              )}
            </Card>
          </div>
        </>
      )}

      <PermissionGuard permission="tenants.view">
        <Card>
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Leasing</h3>
            <Link to="/leases" className="text-xs text-gold hover:text-gold-light">Open leases →</Link>
          </div>
          {loading ? (
            <div className="h-9 animate-pulse rounded-lg bg-charcoal-700" />
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
        </Card>
      </PermissionGuard>

      {loading && (
        <div className="flex justify-center py-8">
          <Spinner />
        </div>
      )}
    </div>
  );
}
