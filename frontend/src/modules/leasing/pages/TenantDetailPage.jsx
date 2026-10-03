import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import Spinner from '../../../components/common/Spinner';
import StatusBadge from '../../property/components/StatusBadge';
import { tenantApi, applicationApi, leaseApi, leasingErrorMessage } from '../services/leasingApi';

function Row({ label, children }) {
  return (
    <div className="flex flex-col gap-1 py-2 sm:flex-row sm:items-center">
      <dt className="w-40 shrink-0 text-xs font-medium uppercase tracking-wider text-slate-500">{label}</dt>
      <dd className="text-sm text-slate-200">{children}</dd>
    </div>
  );
}

export default function TenantDetailPage() {
  const { id } = useParams();
  const { hasPermission } = useAuth();
  const [tenant, setTenant] = useState(null);
  const [applications, setApplications] = useState([]);
  const [leases, setLeases] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    (async () => {
      try {
        const t = await tenantApi.get(id);
        setTenant(t);
        const [apps, lss] = await Promise.all([
          applicationApi.list({ tenant_id: t.id, per_page: 20 }).catch(() => ({ items: [] })),
          leaseApi.list({ tenant_id: t.id, per_page: 20 }).catch(() => ({ items: [] })),
        ]);
        setApplications(apps.items || []);
        setLeases(lss.items || []);
      } catch (err) {
        setError(leasingErrorMessage(err, 'Could not load the tenant.'));
      } finally {
        setLoading(false);
      }
    })();
  }, [id]);

  const canManage = hasPermission('tenants.manage');

  return (
    <PermissionGuard permission="tenants.view" showForbidden>
      <div className="space-y-5">
        <div>
          <Link to="/tenants" className="text-sm text-gold hover:text-gold-light">← Tenants</Link>
          {loading ? <div className="mt-4"><Spinner /></div> : error ? (
            <div className="mt-4 rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-300">{error}</div>
          ) : tenant && (
            <>
              <div className="mt-1 flex flex-wrap items-center gap-3">
                <h2 className="text-xl font-bold text-slate-100">{tenant.name}</h2>
                <StatusBadge value={tenant.status} />
                <StatusBadge value={tenant.kyc_status} />
              </div>
              {canManage && (
                <div className="mt-3 flex gap-2">
                  <Link to={`/tenants/${tenant.id}/edit`} className="aprms-btn-ghost !px-3 !py-1.5 !text-xs">Edit</Link>
                  <Link to={`/applications/new?tenant_id=${tenant.id}`} className="aprms-btn-ghost !px-3 !py-1.5 !text-xs">+ New Application</Link>
                </div>
              )}
            </>
          )}
        </div>

        {tenant && (
          <div className="grid grid-cols-1 gap-5 xl:grid-cols-2">
            <div className="aprms-card">
              <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Profile</h3>
              <dl className="mt-2 divide-y divide-charcoal-700/50">
                <Row label="Email">{tenant.email || '—'}</Row>
                <Row label="Phone">{tenant.phone || '—'}</Row>
                <Row label="City">{tenant.city || '—'}</Row>
                <Row label="Address">{tenant.address || '—'}</Row>
                <Row label="National ID">{tenant.national_id_masked || '—'}</Row>
                <Row label="Notes">{tenant.notes || '—'}</Row>
              </dl>
            </div>

            <div className="space-y-5">
              <div className="aprms-card">
                <div className="flex items-center justify-between">
                  <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Applications</h3>
                  <Link to={`/applications?tenant_id=${tenant.id}`} className="text-xs text-gold hover:text-gold-light">View all</Link>
                </div>
                <div className="mt-3 space-y-2">
                  {applications.length === 0 && <p className="text-sm text-slate-500">No applications yet.</p>}
                  {applications.map((a) => (
                    <Link key={a.id} to={`/applications/${a.id}`} className="flex items-center justify-between rounded-lg bg-charcoal-800 px-3 py-2 ring-1 ring-charcoal-700 hover:ring-gold/40">
                      <span className="text-sm text-slate-200">{a.application_number} · {a.property?.name || '—'}</span>
                      <StatusBadge value={a.status} />
                    </Link>
                  ))}
                </div>
              </div>

              <div className="aprms-card">
                <div className="flex items-center justify-between">
                  <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Leases</h3>
                  <Link to={`/leases?tenant_id=${tenant.id}`} className="text-xs text-gold hover:text-gold-light">View all</Link>
                </div>
                <div className="mt-3 space-y-2">
                  {leases.length === 0 && <p className="text-sm text-slate-500">No leases yet.</p>}
                  {leases.map((l) => (
                    <Link key={l.id} to={`/leases/${l.id}`} className="flex items-center justify-between rounded-lg bg-charcoal-800 px-3 py-2 ring-1 ring-charcoal-700 hover:ring-gold/40">
                      <span className="text-sm text-slate-200">{l.lease_number} · {l.unit?.unit_number || '—'}</span>
                      <StatusBadge value={l.status} />
                    </Link>
                  ))}
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </PermissionGuard>
  );
}
