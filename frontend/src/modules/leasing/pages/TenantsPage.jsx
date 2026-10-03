import { useCallback, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import DataTable from '../../property/components/DataTable';
import StatusBadge from '../../property/components/StatusBadge';
import ConfirmDialog from '../../property/components/ConfirmDialog';
import { tenantApi, leasingErrorMessage } from '../services/leasingApi';

const STATUSES = ['', 'prospective', 'active', 'inactive'];

export default function TenantsPage() {
  const { hasPermission } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [confirmId, setConfirmId] = useState(null);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState('');

  const filters = {
    search: searchParams.get('search') || '',
    status: searchParams.get('status') || '',
    page: parseInt(searchParams.get('page') || '1', 10),
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const { items, meta } = await tenantApi.list({
        per_page: 12,
        search: filters.search || undefined,
        status: filters.status || undefined,
        page: filters.page,
      });
      setRows(items);
      setMeta(meta);
    } catch (err) {
      setError(leasingErrorMessage(err, 'Could not load tenants.'));
    } finally {
      setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams.toString()]);

  useEffect(() => { load(); }, [load]);

  const setFilter = (patch) => {
    const next = new URLSearchParams(searchParams);
    Object.entries(patch).forEach(([k, v]) => {
      if (v) next.set(k, v);
      else next.delete(k);
    });
    if (!patch.page) next.delete('page');
    setSearchParams(next);
  };

  const doArchive = async () => {
    setBusy(true);
    try {
      await tenantApi.archive(confirmId);
      setConfirmId(null);
      setNotice('Tenant archived.');
      load();
    } catch (err) {
      setNotice(leasingErrorMessage(err, 'Could not archive the tenant.'));
    } finally {
      setBusy(false);
    }
  };

  const canManage = hasPermission('tenants.manage');

  return (
    <PermissionGuard permission="tenants.view" showForbidden>
      <div className="space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-xl font-bold text-slate-100">Tenants</h2>
            <p className="mt-1 text-sm text-slate-400">People applying for and living in your units.</p>
          </div>
          {canManage && (
            <Link to="/tenants/new" className="aprms-btn-gold">+ New Tenant</Link>
          )}
        </div>

        {notice && (
          <div className="rounded-lg border border-gold/30 bg-gold/10 px-4 py-3 text-sm text-gold-light">{notice}</div>
        )}

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <input
            className="aprms-input"
            placeholder="Search name, email, phone…"
            value={filters.search}
            onChange={(e) => setFilter({ search: e.target.value })}
          />
          <select
            className="aprms-input"
            value={filters.status}
            onChange={(e) => setFilter({ status: e.target.value })}
          >
            {STATUSES.map((s) => (
              <option key={s} value={s}>{s ? s.replace('_', ' ') : 'All statuses'}</option>
            ))}
          </select>
        </div>

        <DataTable
          columns={[
            {
              key: 'name',
              label: 'Tenant',
              render: (r) => (
                <Link to={`/tenants/${r.id}`} className="font-medium text-gold hover:text-gold-light">
                  {r.name}
                </Link>
              ),
            },
            { key: 'email', label: 'Email', render: (r) => <span className="text-slate-400">{r.email || '—'}</span> },
            { key: 'phone', label: 'Phone', render: (r) => <span className="text-slate-400">{r.phone || '—'}</span> },
            { key: 'kyc_status', label: 'KYC', render: (r) => <StatusBadge value={r.kyc_status} /> },
            { key: 'status', label: 'Status', render: (r) => <StatusBadge value={r.status} /> },
            {
              key: 'actions',
              label: '',
              className: 'text-right',
              render: (r) => (
                <div className="flex justify-end gap-2">
                  <Link to={`/tenants/${r.id}`} className="aprms-btn-ghost !px-3 !py-1.5 !text-xs">Open</Link>
                  {canManage && (
                    <>
                      <Link to={`/tenants/${r.id}/edit`} className="aprms-btn-ghost !px-3 !py-1.5 !text-xs">Edit</Link>
                      <button
                        className="inline-flex items-center rounded-lg border border-red-900/60 bg-red-950/40 px-3 py-1.5 text-xs font-medium text-red-300 transition hover:border-red-500/60"
                        onClick={() => setConfirmId(r.id)}
                      >
                        Archive
                      </button>
                    </>
                  )}
                </div>
              ),
            },
          ]}
          rows={rows}
          meta={meta}
          loading={loading}
          error={error}
          emptyTitle="No tenants yet"
          emptyHint="Add tenants as they apply. Applications, screening and leases attach to the tenant record."
          emptyAction={canManage ? <Link to="/tenants/new" className="aprms-btn-gold">+ New Tenant</Link> : undefined}
          onPage={(page) => setFilter({ page })}
        />

        <ConfirmDialog
          open={!!confirmId}
          title="Archive tenant?"
          message="The tenant will be soft-deleted. Their applications and lease history are preserved."
          confirmLabel="Archive"
          busy={busy}
          onConfirm={doArchive}
          onCancel={() => setConfirmId(null)}
        />
      </div>
    </PermissionGuard>
  );
}
