import { useCallback, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import DataTable from '../../property/components/DataTable';
import StatusBadge from '../../property/components/StatusBadge';
import { applicationApi, leasingErrorMessage } from '../services/leasingApi';

const STATUSES = ['', 'draft', 'submitted', 'under_review', 'screening', 'approved', 'rejected'];

export default function ApplicationsPage() {
  const { hasPermission } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const filters = {
    search: searchParams.get('search') || '',
    status: searchParams.get('status') || '',
    tenant_id: searchParams.get('tenant_id') || '',
    page: parseInt(searchParams.get('page') || '1', 10),
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const { items, meta } = await applicationApi.list({
        per_page: 12,
        search: filters.search || undefined,
        status: filters.status || undefined,
        tenant_id: filters.tenant_id || undefined,
        page: filters.page,
      });
      setRows(items);
      setMeta(meta);
    } catch (err) {
      setError(leasingErrorMessage(err, 'Could not load applications.'));
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

  const canManage = hasPermission('applications.manage');

  return (
    <PermissionGuard permission="applications.view" showForbidden>
      <div className="space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-xl font-bold text-slate-100">Lease Applications</h2>
            <p className="mt-1 text-sm text-slate-400">Draft → submitted → review → screening → decision.</p>
          </div>
          {canManage && (
            <Link
              to={filters.tenant_id ? `/applications/new?tenant_id=${filters.tenant_id}` : '/applications/new'}
              className="aprms-btn-gold"
            >
              + New Application
            </Link>
          )}
        </div>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <input
            className="aprms-input"
            placeholder="Search application no., tenant…"
            value={filters.search}
            onChange={(e) => setFilter({ search: e.target.value })}
          />
          <select
            className="aprms-input"
            value={filters.status}
            onChange={(e) => setFilter({ status: e.target.value })}
          >
            {STATUSES.map((s) => (
              <option key={s} value={s}>{s ? s.replace(/_/g, ' ') : 'All statuses'}</option>
            ))}
          </select>
        </div>

        <DataTable
          columns={[
            {
              key: 'application_number',
              label: 'Application',
              render: (r) => (
                <Link to={`/applications/${r.id}`} className="font-medium text-gold hover:text-gold-light">
                  {r.application_number}
                </Link>
              ),
            },
            { key: 'tenant', label: 'Tenant', render: (r) => r.tenant?.name || '—' },
            { key: 'property', label: 'Property / Unit', render: (r) => (
              <span className="text-slate-400">{r.property?.name || '—'}{r.unit ? ` · ${r.unit.unit_number}` : ''}</span>
            ) },
            { key: 'screening', label: 'Screening', render: (r) => <StatusBadge value={r.screening_status} /> },
            { key: 'status', label: 'Status', render: (r) => <StatusBadge value={r.status} /> },
            {
              key: 'actions',
              label: '',
              className: 'text-right',
              render: (r) => (
                <Link to={`/applications/${r.id}`} className="aprms-btn-ghost !px-3 !py-1.5 !text-xs">Review</Link>
              ),
            },
          ]}
          rows={rows}
          meta={meta}
          loading={loading}
          error={error}
          emptyTitle="No applications yet"
          emptyHint="Applications capture a tenant's request for a unit. Screening and approval happen on the review screen."
          emptyAction={canManage ? <Link to="/applications/new" className="aprms-btn-gold">+ New Application</Link> : undefined}
          onPage={(page) => setFilter({ page })}
        />
      </div>
    </PermissionGuard>
  );
}
