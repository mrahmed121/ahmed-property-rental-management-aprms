import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import Spinner from '../../../components/common/Spinner';
import { invoiceApi, formatPKR, billingErrorMessage } from '../services/billingApi';

export default function RentCyclePage() {
  const { hasPermission } = useAuth();
  const [period, setPeriod] = useState(new Date().toISOString().slice(0, 7));
  const [running, setRunning] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState('');

  const run = async (dryRun) => {
    setRunning(true);
    setError('');
    setResult(null);
    try {
      const r = await invoiceApi.generateCycle(period, dryRun);
      setResult(r);
    } catch (err) {
      setError(billingErrorMessage(err, 'Rent cycle failed.'));
    } finally {
      setRunning(false);
    }
  };

  const accrue = async () => {
    setRunning(true);
    setError('');
    try {
      const r = await invoiceApi.accrueLateFees();
      setResult({ lateFees: r });
    } catch (err) {
      setError(billingErrorMessage(err, 'Late-fee accrual failed.'));
    } finally {
      setRunning(false);
    }
  };

  const canGenerate = hasPermission('invoices.generate');
  const canAdjust = hasPermission('billing.adjust');

  return (
    <PermissionGuard permission="invoices.generate" showForbidden>
      <div className="mx-auto max-w-3xl space-y-5">
        <div>
          <Link to="/billing/invoices" className="text-sm text-gold hover:text-gold-light">← Invoices</Link>
          <h2 className="mt-1 text-xl font-bold text-slate-100">Monthly Rent Cycle</h2>
          <p className="mt-1 text-sm text-slate-400">
            Generates invoices for all eligible active leases. Idempotent — re-running never duplicates.
          </p>
        </div>

        {error && (
          <div className="rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-300">{error}</div>
        )}

        <div className="aprms-card space-y-4">
          <div>
            <label className="mb-1 block text-xs font-medium uppercase tracking-wider text-slate-400">Period (YYYY-MM)</label>
            <input
              type="month"
              className="aprms-input"
              value={period}
              onChange={(e) => setPeriod(e.target.value)}
            />
          </div>
          <div className="flex flex-wrap gap-2">
            <button
              className="aprms-btn-ghost"
              disabled={running || !canGenerate}
              onClick={() => run(true)}
            >
              {running ? 'Running…' : 'Dry Run (preview)'}
            </button>
            <button
              className="aprms-btn-gold"
              disabled={running || !canGenerate}
              onClick={() => run(false)}
            >
              {running ? 'Running…' : 'Generate Invoices'}
            </button>
            {canAdjust && (
              <button
                className="aprms-btn-ghost"
                disabled={running}
                onClick={accrue}
                title="Accrue late fees for overdue invoices past their grace period"
              >
                Accrue Late Fees
              </button>
            )}
          </div>
          <p className="text-xs text-slate-500">
            Proration: monthly rent × (billable days ÷ days in month), rounded half-up to the paisa.
            Mid-month starts and ends are prorated automatically.
          </p>
        </div>

        {running && <Spinner />}

        {result && result.lateFees && (
          <div className="rounded-lg border border-gold/30 bg-gold/10 px-4 py-3 text-sm text-gold-light">
            {result.lateFees.message}
          </div>
        )}

        {result && !result.lateFees && (
          <div className="aprms-card space-y-3">
            <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">
              {result.dry_run ? 'Dry run — would generate' : 'Generated'} for {result.period}
            </h3>

            {(result.dry_run ? result.would_create : result.created).length === 0 && (
              <p className="text-sm text-slate-500">Nothing to generate — all eligible leases already invoiced.</p>
            )}

            <div className="space-y-2">
              {(result.dry_run ? result.would_create : result.created).map((c, i) => (
                <div key={i} className="flex items-center justify-between rounded-lg bg-charcoal-800 px-3 py-2 ring-1 ring-charcoal-700">
                  <span className="text-sm text-slate-200">
                    {c.tenant} · Unit {c.unit}
                    <span className="ml-2 text-xs text-slate-500">
                      {c.billable_days}/{c.days_in_month} days
                    </span>
                  </span>
                  <span className="text-sm font-medium text-gold">{formatPKR(c.base_rent)}</span>
                </div>
              ))}
            </div>

            {result.skipped.length > 0 && (
              <div className="border-t border-charcoal-700/60 pt-3">
                <p className="text-xs uppercase tracking-wider text-slate-500">Skipped ({result.skipped.length})</p>
                <div className="mt-2 space-y-1">
                  {result.skipped.map((s, i) => (
                    <p key={i} className="text-xs text-slate-500">Lease #{s.lease_id} — {s.reason}</p>
                  ))}
                </div>
              </div>
            )}
          </div>
        )}
      </div>
    </PermissionGuard>
  );
}
