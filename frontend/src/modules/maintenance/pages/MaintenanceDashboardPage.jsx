import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import PermissionGuard from '../../../components/common/PermissionGuard';
import Spinner from '../../../components/common/Spinner';
import { maintenanceDashboardApi, maintenanceErrorMessage } from '../services/maintenanceApi';
import { formatPKR } from '../../billing/services/billingApi';

function Card({ label, value, sub, accent }) {
  return (
    <div className="aprms-card">
      <p className="text-xs font-medium uppercase tracking-wider text-slate-500">{label}</p>
      <p className={`mt-2 text-2xl font-bold ${accent || 'text-slate-100'}`}>{value}</p>
      {sub && <p className="mt-1 text-xs text-slate-500">{sub}</p>}
    </div>
  );
}

export default function MaintenanceDashboardPage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    maintenanceDashboardApi.metrics()
      .then(setData)
      .catch((err) => setError(maintenanceErrorMessage(err, 'Could not load maintenance metrics.')))
      .finally(() => setLoading(false));
  }, []);

  return (
    <PermissionGuard permission="maintenance.view" showForbidden>
      <div className="space-y-5">
        <div>
          <h2 className="text-xl font-bold text-slate-100">Maintenance Overview</h2>
          <p className="mt-1 text-sm text-slate-400">Live operational numbers — every figure from the database.</p>
        </div>

        {error && (
          <div className="rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-300">{error}</div>
        )}

        {loading ? <Spinner /> : data && (
          <>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <Card label="Open tickets" value={data.open_tickets} />
              <Card label="Urgent" value={data.urgent_tickets} accent="text-red-300" />
              <Card label="SLA breached" value={data.sla_breached} accent="text-red-300" sub={`${data.sla_due_soon} due within 24h`} />
              <Card label="Pending approvals" value={data.pending_approvals} accent="text-yellow-300" />
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <Card label="Assigned" value={data.assigned} />
              <Card label="In progress" value={data.in_progress} />
              <Card label="Completed this month" value={data.completed_this_month} />
              <Card label="Approved spend" value={formatPKR(data.approved_spend)} sub="approved quotes only" />
            </div>

            <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
              <div className="aprms-card">
                <p className="text-xs font-medium uppercase tracking-wider text-slate-500">Open by priority</p>
                <div className="mt-2 space-y-1 text-sm">
                  {Object.entries(data.by_priority).map(([p, n]) => (
                    <div key={p} className="flex justify-between">
                      <span className="capitalize text-slate-400">{p}</span>
                      <span className="font-medium text-slate-100">{n}</span>
                    </div>
                  ))}
                </div>
              </div>
              <div className="aprms-card">
                <p className="text-xs font-medium uppercase tracking-wider text-slate-500">Open by status</p>
                <div className="mt-2 space-y-1 text-sm">
                  {Object.entries(data.by_status).map(([s, n]) => (
                    <div key={s} className="flex justify-between">
                      <span className="text-slate-400">{s.replace(/_/g, ' ')}</span>
                      <span className="font-medium text-slate-100">{n}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            <div className="flex flex-wrap gap-2">
              <Link to="/maintenance/tickets" className="aprms-btn-ghost">All Tickets</Link>
              <Link to="/maintenance/vendors" className="aprms-btn-ghost">Vendors</Link>
            </div>
          </>
        )}
      </div>
    </PermissionGuard>
  );
}
