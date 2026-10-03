import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import StatusBadge from '../../property/components/StatusBadge';
import { utilityBillApi, utilityErrorMessage } from '../services/utilityApi';

const fmt = (n) => `₨${Number(n || 0).toLocaleString('en-PK', { minimumFractionDigits: 2 })}`;

export default function UtilityBillDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { hasPermission } = useAuth();
  const [bill, setBill] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const load = async () => {
    setLoading(true);
    setError('');
    try {
      setBill(await utilityBillApi.get(id));
    } catch (err) {
      setError(utilityErrorMessage(err, 'Could not load bill.'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, [id]);

  const doFinalize = async () => {
    if (!window.confirm('Finalize this bill? Tenant charges will post to the ledger.')) return;
    setBusy(true);
    try {
      await utilityBillApi.finalize(id);
      await load();
    } catch (err) {
      setError(utilityErrorMessage(err, 'Could not finalize bill.'));
    } finally {
      setBusy(false);
    }
  };

  const doReverse = async () => {
    const reason = window.prompt('Reason for reversal (required):');
    if (!reason || reason.trim().length < 5) return;
    setBusy(true);
    try {
      await utilityBillApi.reverse(id, reason.trim());
      await load();
    } catch (err) {
      setError(utilityErrorMessage(err, 'Could not reverse bill.'));
    } finally {
      setBusy(false);
    }
  };

  if (loading) return <div className="text-slate-400">Loading bill…</div>;
  if (error && !bill) return <div className="alert-error">{error}</div>;
  if (!bill) return null;

  return (
    <div className="space-y-6 max-w-3xl">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-semibold">{bill.bill_number}</h1>
          <p className="text-slate-400 text-sm">{bill.meter?.meter_number} · {bill.period_start} → {bill.period_end}</p>
        </div>
        <StatusBadge status={bill.status} />
      </div>

      {error && <div className="alert-error">{error}</div>}

      <div className="card space-y-2 text-sm">
        <div className="flex justify-between"><span className="text-slate-400">Previous reading</span><span>{Number(bill.previous_reading).toLocaleString()}</span></div>
        <div className="flex justify-between"><span className="text-slate-400">Current reading</span><span>{Number(bill.current_reading).toLocaleString()}</span></div>
        <div className="flex justify-between"><span className="text-slate-400">Consumption</span><span>{Number(bill.consumption).toLocaleString()}</span></div>
        <div className="flex justify-between"><span className="text-slate-400">Rate</span><span>{fmt(bill.rate)} / unit</span></div>
        <div className="flex justify-between"><span className="text-slate-400">Fixed charge</span><span>{fmt(bill.fixed_charge)}</span></div>
        <div className="flex justify-between"><span className="text-slate-400">Tax</span><span>{fmt(bill.tax_amount)}</span></div>
        <div className="flex justify-between font-semibold text-base border-t border-slate-800 pt-2"><span>Total</span><span>{fmt(bill.total)}</span></div>
      </div>

      <div className="card">
        <h2 className="font-semibold mb-2">Allocation</h2>
        {bill.allocations?.length === 0 ? (
          <div className="empty-state">No allocations.</div>
        ) : (
          <table className="w-full text-sm">
            <thead><tr className="text-left text-slate-400"><th className="py-1">Type</th><th>Unit</th><th>Tenant</th><th className="text-right">Amount</th></tr></thead>
            <tbody>
              {bill.allocations?.map((a) => (
                <tr key={a.id} className="border-t border-slate-800">
                  <td className="py-1 capitalize">{a.allocation_type.replace('_', ' ')}</td>
                  <td>{a.unit || '—'}</td>
                  <td>{a.tenant || (a.allocation_type === 'vacant_owner' ? <span className="text-yellow-300">Owner absorbed</span> : '—')}</td>
                  <td className="text-right">{fmt(a.amount)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      <div className="flex gap-2">
        <PermissionGuard permission="utilities.bill">
          {bill.status === 'draft' && (
            <button className="btn-primary" onClick={doFinalize} disabled={busy}>{busy ? 'Working…' : 'Finalize Bill'}</button>
          )}
        </PermissionGuard>
        <PermissionGuard permission="utilities.adjust">
          {bill.status === 'finalized' && (
            <button className="btn-danger" onClick={doReverse} disabled={busy}>{busy ? 'Working…' : 'Reverse Bill'}</button>
          )}
        </PermissionGuard>
      </div>
    </div>
  );
}
