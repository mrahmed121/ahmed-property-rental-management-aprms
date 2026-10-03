import { useCallback, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import DataTable from '../../property/components/DataTable';
import StatusBadge from '../../property/components/StatusBadge';
import { invoiceApi, formatPKR, billingErrorMessage } from '../services/billingApi';

const STATUSES = ['', 'issued', 'partially_paid', 'paid', 'overdue', 'void'];

export default function InvoicesPage() {
  const { hasPermission } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const filters = {
    search: searchParams.get('search') || '',
    status: searchParams.get('status') || '',
    overdue: searchParams.get('overdue') || '',
    page: parseInt(searchParams.get('page') || '1', 10),
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const { items, meta } = await invoiceApi.list({
        per_page: 12,
        search: filters.search || undefined,
        status: filters.status || undefined,
        overdue: filters.overdue || undefined,
        page: filters.page,
      });
      setRows(items);
      setMeta(meta);
    } catch (err) {
      setError(billingErrorMessage(err, 'Could not load invoices.'));
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

  const canGenerate = hasPermission('invoices.generate');

  return (
    <PermissionGuard permission="invoices.view" showForbidden>
      <div className="space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-xl font-bold text-slate-100">Rent Invoices</h2>
            <p className="mt-1 text-sm text-slate-400">Every rupee billed, tracked to the paisa.</p>
          </div>
          <div className="flex gap-2">
            {canGenerate && (
              <Link to="/billing/rent-cycle" className="aprms-btn-ghost">Rent Cycle</Link>
            )}
          </div>
        </div>

        {notice && (
          <div className="rounded-lg border border-gold/30 bg-gold/10 px-4 py-3 text-sm text-gold-light">{notice}</div>
        )}

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <input
            className="aprms-input"
            placeholder="Search invoice no., tenant…"
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
          <label className="flex items-center gap-2 text-sm text-slate-300">
            <input
              type="checkbox"
              checked={!!filters.overdue}
              onChange={(e) => setFilter({ overdue: e.target.checked ? '1' : '' })}
              className="h-4 w-4 accent-yellow-600"
            />
            Overdue only
          </label>
        </div>

        <DataTable
          columns={[
            {
              key: 'invoice_number',
              label: 'Invoice',
              render: (r) => (
                <Link to={`/billing/invoices/${r.id}`} className="font-medium text-gold hover:text-gold-light">
                  {r.invoice_number}
                </Link>
              ),
            },
            { key: 'tenant', label: 'Tenant', render: (r) => r.tenant?.name || '—' },
            { key: 'period', label: 'Period', render: (r) => (
              <span className="text-slate-400">{r.period_start} → {r.period_end}</span>
            ) },
            { key: 'due', label: 'Due', render: (r) => <span className="text-slate-400">{r.due_date}</span> },
            { key: 'total', label: 'Total', render: (r) => <span className="font-medium text-slate-100">{formatPKR(r.total)}</span> },
            { key: 'outstanding', label: 'Outstanding', render: (r) => (
              <span className={r.outstanding > 0 ? 'font-medium text-red-300' : 'text-slate-500'}>
                {formatPKR(r.outstanding)}
              </span>
            ) },
            { key: 'status', label: 'Status', render: (r) => <StatusBadge value={r.status} /> },
            {
              key: 'actions',
              label: '',
              className: 'text-right',
              render: (r) => (
                <Link to={`/billing/invoices/${r.id}`} className="aprms-btn-ghost !px-3 !py-1.5 !text-xs">Open</Link>
              ),
            },
          ]}
          rows={rows}
          meta={meta}
          loading={loading}
          error={error}
          emptyTitle="No invoices yet"
          emptyHint="Run the rent cycle to generate invoices for active leases, or create one manually."
          onPage={(page) => setFilter({ page })}
        />
      </div>
    </PermissionGuard>
  );
}
