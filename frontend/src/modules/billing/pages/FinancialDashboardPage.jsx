import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import PermissionGuard from '../../../components/common/PermissionGuard';
import Spinner from '../../../components/common/Spinner';
import { financialApi, formatPKR, billingErrorMessage } from '../services/billingApi';

function MoneyCard({ label, value, sub }) {
  return (
    <div className="aprms-card">
      <p className="text-xs font-medium uppercase tracking-wider text-slate-500">{label}</p>
      <p className="mt-2 text-2xl font-bold text-slate-100">{value}</p>
      {sub && <p className="mt-1 text-xs text-slate-500">{sub}</p>}
    </div>
  );
}

export default function FinancialDashboardPage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    financialApi.dashboard()
      .then(setData)
      .catch((err) => setError(billingErrorMessage(err, 'Could not load financial metrics.')))
      .finally(() => setLoading(false));
  }, []);

  return (
    <PermissionGuard permission="billing.view" showForbidden>
      <div className="space-y-5">
        <div>
          <h2 className="text-xl font-bold text-slate-100">Financial Overview</h2>
          <p className="mt-1 text-sm text-slate-400">
            Live money numbers for {data?.period || '…'} — every figure from the database.
          </p>
        </div>

        {error && (
          <div className="rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-300">{error}</div>
        )}

        {loading ? <Spinner /> : data && (
          <>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <MoneyCard label="Billed this period" value={formatPKR(data.billed_this_period)} />
              <MoneyCard label="Collected this period" value={formatPKR(data.collected_this_period)} />
              <MoneyCard label="Outstanding" value={formatPKR(data.outstanding)} sub={`${data.invoices_due} invoices due`} />
              <MoneyCard label="Overdue" value={formatPKR(data.overdue)} sub={`${data.invoices_overdue} invoices overdue`} />
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <MoneyCard
                label="Collection rate"
                value={data.collection_rate === null ? '—' : `${data.collection_rate}%`}
                sub="collected ÷ billed this period"
              />
              <MoneyCard label="Payments today" value={formatPKR(data.payments_today)} />
              <div className="aprms-card">
                <p className="text-xs font-medium uppercase tracking-wider text-slate-500">Arrears aging</p>
                <div className="mt-2 space-y-1 text-sm">
                  {Object.entries({ '0_30': '0–30 days', '31_60': '31–60', '61_90': '61–90', '90_plus': '90+' }).map(([k, label]) => (
                    <div key={k} className="flex justify-between">
                      <span className="text-slate-400">{label}</span>
                      <span className="font-medium text-red-300">{formatPKR(data.arrears_aging[k] || 0)}</span>
                    </div>
                  ))}
                </div>
                <Link to="/billing/arrears" className="mt-2 inline-block text-xs text-gold hover:text-gold-light">
                  View arrears →
                </Link>
              </div>
            </div>

            <div className="flex flex-wrap gap-2">
              <Link to="/billing/invoices" className="aprms-btn-ghost">Invoices</Link>
              <Link to="/billing/payments" className="aprms-btn-ghost">Payments</Link>
              <Link to="/billing/dunning" className="aprms-btn-ghost">Dunning</Link>
              <Link to="/billing/rent-cycle" className="aprms-btn-ghost">Rent Cycle</Link>
            </div>
          </>
        )}
      </div>
    </PermissionGuard>
  );
}
