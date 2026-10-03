import { useCallback, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import DataTable from '../../property/components/DataTable';
import StatusBadge from '../../property/components/StatusBadge';
import { paymentApi, formatPKR, billingErrorMessage } from '../services/billingApi';

const METHODS = ['', 'cash', 'bank_transfer', 'online', 'card', 'other'];

export default function PaymentsPage() {
  const { hasPermission } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const filters = {
    search: searchParams.get('search') || '',
    method: searchParams.get('method') || '',
    page: parseInt(searchParams.get('page') || '1', 10),
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const { items, meta } = await paymentApi.list({
        per_page: 12,
        search: filters.search || undefined,
        method: filters.method || undefined,
        page: filters.page,
      });
      setRows(items);
      setMeta(meta);
    } catch (err) {
      setError(billingErrorMessage(err, 'Could not load payments.'));
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

  const canRecord = hasPermission('payments.record');

  return (
    <PermissionGuard permission="payments.view" showForbidden>
      <div className="space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-xl font-bold text-slate-100">Payments</h2>
            <p className="mt-1 text-sm text-slate-400">Recorded collections with waterfall allocation.</p>
          </div>
          {canRecord && <Link to="/billing/payments/new" className="aprms-btn-gold">+ Record Payment</Link>}
        </div>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <input
            className="aprms-input"
            placeholder="Search receipt no., reference…"
            value={filters.search}
            onChange={(e) => setFilter({ search: e.target.value })}
          />
          <select
            className="aprms-input"
            value={filters.method}
            onChange={(e) => setFilter({ method: e.target.value })}
          >
            {METHODS.map((m) => (
              <option key={m} value={m}>{m ? m.replace('_', ' ') : 'All methods'}</option>
            ))}
          </select>
        </div>

        <DataTable
          columns={[
            {
              key: 'receipt_number',
              label: 'Receipt',
              render: (r) => (
                <Link to={`/billing/payments/${r.id}`} className="font-medium text-gold hover:text-gold-light">
                  {r.receipt_number}
                </Link>
              ),
            },
            { key: 'tenant', label: 'Tenant', render: (r) => r.tenant?.name || '—' },
            { key: 'payment_date', label: 'Date', render: (r) => <span className="text-slate-400">{r.payment_date}</span> },
            { key: 'method', label: 'Method', render: (r) => <span className="text-slate-400">{r.method.replace('_', ' ')}</span> },
            { key: 'amount', label: 'Amount', render: (r) => <span className="font-medium text-slate-100">{formatPKR(r.amount)}</span> },
            { key: 'status', label: 'Status', render: (r) => <StatusBadge value={r.status} /> },
            {
              key: 'actions',
              label: '',
              className: 'text-right',
              render: (r) => (
                <Link to={`/billing/payments/${r.id}`} className="aprms-btn-ghost !px-3 !py-1.5 !text-xs">Open</Link>
              ),
            },
          ]}
          rows={rows}
          meta={meta}
          loading={loading}
          error={error}
          emptyTitle="No payments yet"
          emptyHint="Record a payment against a tenant. The system allocates it automatically: late fees → utilities → current rent → oldest arrears."
          emptyAction={canRecord ? <Link to="/billing/payments/new" className="aprms-btn-gold">+ Record Payment</Link> : undefined}
          onPage={(page) => setFilter({ page })}
        />
      </div>
    </PermissionGuard>
  );
}
