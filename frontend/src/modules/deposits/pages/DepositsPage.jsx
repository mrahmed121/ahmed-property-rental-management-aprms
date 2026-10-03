import { useCallback, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import PermissionGuard from '../../../components/common/PermissionGuard';
import DataTable from '../../property/components/DataTable';
import StatusBadge from '../../property/components/StatusBadge';
import { depositApi, formatPKR, depositErrorMessage } from '../services/depositApi';
import { useAuth } from '../../../context/AuthContext';

const STATUSES = ['', 'required', 'held', 'partially_released', 'settled', 'closed'];

export default function DepositsPage() {
  const { hasPermission } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const filters = {
    search: searchParams.get('search') || '',
    status: searchParams.get('status') || '',
    page: parseInt(searchParams.get('page') || '1', 10),
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const { items, meta } = await depositApi.list({
        per_page: 12,
        search: filters.search || undefined,
        status: filters.status || undefined,
        page: filters.page,
      });
      setRows(items);
      setMeta(meta);
    } catch (err) {
      setError(depositErrorMessage(err, 'Could not load deposits.'));
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

  const canManage = hasPermission('deposits.manage');

  return (
    <PermissionGuard permission="deposits.view" showForbidden>
      <div className="space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-xl font-bold text-slate-100">Security Deposits</h2>
            <p className="mt-1 text-sm text-slate-400">Held deposits, deductions, and final settlements.</p>
          </div>
          {canManage && <Link to="/deposits/new" className="aprms-btn-gold">+ New Deposit</Link>}
        </div>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <input
            className="aprms-input"
            placeholder="Search tenant, reference…"
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
            { key: 'tenant', label: 'Tenant', render: (r) => (
              <Link to={`/deposits/${r.id}`} className="font-medium text-gold hover:text-gold-light">
                {r.tenant?.name || '—'}
              </Link>
            ) },
            { key: 'property', label: 'Property', render: (r) => (
              <span className="text-slate-400">{r.property?.name || '—'}{r.unit ? ` · ${r.unit.unit_number}` : ''}</span>
            ) },
            { key: 'deposit_amount', label: 'Agreed', render: (r) => <span className="text-slate-300">{formatPKR(r.deposit_amount)}</span> },
            { key: 'held_amount', label: 'Held', render: (r) => <span className="font-medium text-slate-100">{formatPKR(r.held_amount)}</span> },
            { key: 'status', label: 'Status', render: (r) => <StatusBadge value={r.status} /> },
            {
              key: 'actions',
              label: '',
              className: 'text-right',
              render: (r) => (
                <Link to={`/deposits/${r.id}`} className="aprms-btn-ghost !px-3 !py-1.5 !text-xs">Open</Link>
              ),
            },
          ]}
          rows={rows}
          meta={meta}
          loading={loading}
          error={error}
          emptyTitle="No deposits yet"
          emptyHint="Record a security deposit against an active lease. The cap is 3× monthly rent."
          emptyAction={canManage ? <Link to="/deposits/new" className="aprms-btn-gold">+ New Deposit</Link> : undefined}
          onPage={(page) => setFilter({ page })}
        />
      </div>
    </PermissionGuard>
  );
}
