import { useCallback, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import PermissionGuard from '../../../components/common/PermissionGuard';
import DataTable from '../../property/components/DataTable';
import { financialApi, formatPKR, billingErrorMessage } from '../services/billingApi';

const AGING_LABELS = {
  '0_30': '0–30 days',
  '31_60': '31–60 days',
  '61_90': '61–90 days',
  '90_plus': '90+ days',
};

export default function ArrearsPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState(null);
  const [aging, setAging] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const page = parseInt(searchParams.get('page') || '1', 10);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [{ items, meta }, dash] = await Promise.all([
        financialApi.arrears({ per_page: 12, page }),
        financialApi.dashboard(),
      ]);
      setRows(items);
      setMeta(meta);
      setAging(dash.arrears_aging);
    } catch (err) {
      setError(billingErrorMessage(err, 'Could not load arrears.'));
    } finally {
      setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams.toString()]);

  useEffect(() => { load(); }, [load]);

  return (
    <PermissionGuard permission="billing.view" showForbidden>
      <div className="space-y-5">
        <div>
          <h2 className="text-xl font-bold text-slate-100">Arrears</h2>
          <p className="mt-1 text-sm text-slate-400">Unpaid invoices grouped by how overdue they are.</p>
        </div>

        {aging && (
          <div className="grid grid-cols-2 gap-4 xl:grid-cols-4">
            {Object.entries(AGING_LABELS).map(([key, label]) => (
              <div key={key} className="aprms-card">
                <p className="text-xs uppercase tracking-wider text-slate-500">{label}</p>
                <p className="mt-2 text-2xl font-bold text-red-300">{formatPKR(aging[key] || 0)}</p>
              </div>
            ))}
          </div>
        )}

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
            { key: 'tenant', label: 'Tenant', render: (r) => r.tenant || '—' },
            { key: 'property', label: 'Property', render: (r) => (
              <span className="text-slate-400">{r.property || '—'}{r.unit ? ` · ${r.unit}` : ''}</span>
            ) },
            { key: 'due_date', label: 'Due', render: (r) => <span className="text-slate-400">{r.due_date}</span> },
            { key: 'days_overdue', label: 'Days overdue', render: (r) => (
              <span className="font-medium text-red-300">{r.days_overdue}</span>
            ) },
            { key: 'outstanding', label: 'Outstanding', render: (r) => (
              <span className="font-medium text-slate-100">{formatPKR(r.outstanding)}</span>
            ) },
            {
              key: 'actions',
              label: '',
              className: 'text-right',
              render: (r) => (
                <Link to={`/billing/ledger/${r.tenant_id}`} className="aprms-btn-ghost !px-3 !py-1.5 !text-xs">
                  Ledger
                </Link>
              ),
            },
          ]}
          rows={rows}
          meta={meta}
          loading={loading}
          error={error}
          emptyTitle="No arrears"
          emptyHint="Every invoice is paid up. Arrears appear here the moment a due date passes unpaid."
          onPage={(p) => {
            const next = new URLSearchParams(searchParams);
            next.set('page', p);
            setSearchParams(next);
          }}
        />
      </div>
    </PermissionGuard>
  );
}
