import { useCallback, useEffect, useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import PermissionGuard from '../../../components/common/PermissionGuard';
import Spinner from '../../../components/common/Spinner';
import DataTable from '../../property/components/DataTable';
import StatusBadge from '../../property/components/StatusBadge';
import { tenantApi } from '../../leasing/services/leasingApi';
import { ledgerApi, formatPKR, billingErrorMessage } from '../services/billingApi';

export default function LedgerPage() {
  const { tenantId } = useParams();
  const [searchParams] = useSearchParams();
  const [tenant, setTenant] = useState(null);
  const [statement, setStatement] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const tid = tenantId || searchParams.get('tenant_id');

  const load = useCallback(async () => {
    if (!tid) { setLoading(false); return; }
    setLoading(true);
    try {
      const [t, s] = await Promise.all([
        tenantApi.get(tid).catch(() => null),
        ledgerApi.statement(tid),
      ]);
      setTenant(t);
      setStatement(s);
    } catch (err) {
      setError(billingErrorMessage(err, 'Could not load the ledger.'));
    } finally {
      setLoading(false);
    }
  }, [tid]);

  useEffect(() => { load(); }, [load]);

  return (
    <PermissionGuard permission="ledger.view" showForbidden>
      <div className="space-y-5">
        <div>
          <Link to="/billing/arrears" className="text-sm text-gold hover:text-gold-light">← Arrears</Link>
          <h2 className="mt-1 text-xl font-bold text-slate-100">
            Tenant Ledger {tenant ? `— ${tenant.name}` : ''}
          </h2>
          <p className="mt-1 text-sm text-slate-400">
            Every charge and payment, derived running balance. Nothing is hand-edited.
          </p>
        </div>

        {loading ? <Spinner /> : error ? (
          <div className="rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-300">{error}</div>
        ) : !tid ? (
          <div className="aprms-card">
            <p className="text-sm text-slate-400">Select a tenant to view their ledger.</p>
            <Link to="/tenants" className="mt-2 inline-block text-sm text-gold hover:text-gold-light">Browse tenants →</Link>
          </div>
        ) : statement && (
          <>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <div className="aprms-card">
                <p className="text-xs uppercase tracking-wider text-slate-500">Opening balance</p>
                <p className="mt-2 text-2xl font-bold text-slate-100">{formatPKR(statement.opening_balance)}</p>
              </div>
              <div className="aprms-card">
                <p className="text-xs uppercase tracking-wider text-slate-500">Charges − Payments</p>
                <p className="mt-2 text-sm text-slate-300">
                  <span className="text-red-300">{formatPKR(statement.total_debit)}</span>
                  {' '}−{' '}
                  <span className="text-green-300">{formatPKR(statement.total_credit)}</span>
                </p>
              </div>
              <div className="aprms-card">
                <p className="text-xs uppercase tracking-wider text-slate-500">Closing balance (owed)</p>
                <p className="mt-2 text-2xl font-bold text-gold">{formatPKR(statement.closing_balance)}</p>
              </div>
            </div>

            <DataTable
              columns={[
                { key: 'entry_date', label: 'Date', render: (r) => <span className="text-slate-400">{r.entry_date}</span> },
                { key: 'entry_type', label: 'Type', render: (r) => <StatusBadge value={r.entry_type} /> },
                { key: 'description', label: 'Description', render: (r) => <span className="text-slate-300">{r.description}</span> },
                { key: 'debit', label: 'Debit', render: (r) => <span className="text-red-300">{r.debit > 0 ? formatPKR(r.debit) : '—'}</span> },
                { key: 'credit', label: 'Credit', render: (r) => <span className="text-green-300">{r.credit > 0 ? formatPKR(r.credit) : '—'}</span> },
                { key: 'balance_after', label: 'Balance', render: (r) => <span className="font-medium text-slate-100">{formatPKR(r.balance_after)}</span> },
              ]}
              rows={statement.entries}
              meta={null}
              loading={false}
              error=""
              emptyTitle="No ledger entries"
              emptyHint="Charges and payments will appear here as they are posted."
            />
          </>
        )}
      </div>
    </PermissionGuard>
  );
}
