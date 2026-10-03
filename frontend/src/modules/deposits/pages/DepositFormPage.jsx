import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import PermissionGuard from '../../../components/common/PermissionGuard';
import { leaseApi } from '../../leasing/services/leasingApi';
import { depositApi, formatPKR, depositErrorMessage } from '../services/depositApi';

export default function DepositFormPage() {
  const navigate = useNavigate();
  const [leases, setLeases] = useState([]);
  const [form, setForm] = useState({ lease_id: '', deposit_amount: '', reference: '', notes: '' });
  const [posting, setPosting] = useState(false);
  const [errors, setErrors] = useState({});
  const [error, setError] = useState('');

  useEffect(() => {
    leaseApi.list({ per_page: 100, status: 'active' })
      .then((r) => setLeases(r.items))
      .catch(() => {});
  }, []);

  const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));

  const submit = async (e) => {
    e.preventDefault();
    setPosting(true);
    setErrors({});
    setError('');
    try {
      const saved = await depositApi.create({
        lease_id: parseInt(form.lease_id, 10),
        deposit_amount: parseFloat(form.deposit_amount),
        reference: form.reference || undefined,
        notes: form.notes || undefined,
      });
      navigate(`/deposits/${saved.id}`);
    } catch (err) {
      if (err?.response?.status === 422 && err.response.data.errors) {
        setErrors(err.response.data.errors);
      } else {
        setError(depositErrorMessage(err, 'Could not create the deposit.'));
      }
    } finally {
      setPosting(false);
    }
  };

  const labelCls = 'mb-1 block text-xs font-medium uppercase tracking-wider text-slate-400';
  const err = (k) => errors[k] && <p className="mt-1 text-xs text-red-400">{errors[k][0]}</p>;

  const selectedLease = leases.find((l) => String(l.id) === String(form.lease_id));
  const cap = selectedLease ? Math.round(selectedLease.monthly_rent * 3 * 100) / 100 : null;

  return (
    <PermissionGuard permission="deposits.manage" showForbidden>
      <div className="mx-auto max-w-2xl space-y-5">
        <div>
          <Link to="/deposits" className="text-sm text-gold hover:text-gold-light">← Deposits</Link>
          <h2 className="mt-1 text-xl font-bold text-slate-100">New Security Deposit</h2>
          <p className="mt-1 text-sm text-slate-400">
            Approved cap: deposit ≤ 3× monthly rent. Enforced server-side.
          </p>
        </div>

        {error && (
          <div className="rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-300">{error}</div>
        )}

        <form onSubmit={submit} className="aprms-card space-y-4">
          <div>
            <label className={labelCls} htmlFor="dep-lease">Lease *</label>
            <select id="dep-lease" className="aprms-input" value={form.lease_id} onChange={set('lease_id')} required>
              <option value="">Select active lease…</option>
              {leases.map((l) => (
                <option key={l.id} value={l.id}>
                  {l.lease_number} — {l.tenant?.name} ({formatPKR(l.monthly_rent)}/mo)
                </option>
              ))}
            </select>
            {err('lease_id')}
            {cap && (
              <p className="mt-1 text-xs text-slate-500">Maximum allowed: {formatPKR(cap)} (3× rent)</p>
            )}
          </div>

          <div>
            <label className={labelCls} htmlFor="dep-amount">Deposit amount (PKR) *</label>
            <input
              id="dep-amount" type="number" min="0.01" step="0.01"
              className="aprms-input" value={form.deposit_amount}
              onChange={set('deposit_amount')} required placeholder="0.00"
            />
            {err('deposit_amount')}
          </div>

          <div>
            <label className={labelCls} htmlFor="dep-ref">Reference</label>
            <input id="dep-ref" className="aprms-input" value={form.reference} onChange={set('reference')} placeholder="Receipt no., bank ref…" />
          </div>

          <div>
            <label className={labelCls} htmlFor="dep-notes">Notes</label>
            <textarea id="dep-notes" className="aprms-input" rows={2} value={form.notes} onChange={set('notes')} />
          </div>

          <div className="flex justify-end gap-2 border-t border-charcoal-700/60 pt-4">
            <Link to="/deposits" className="aprms-btn-ghost">Cancel</Link>
            <button type="submit" disabled={posting} className="aprms-btn-gold disabled:opacity-50">
              {posting ? 'Saving…' : 'Create Deposit'}
            </button>
          </div>
        </form>
      </div>
    </PermissionGuard>
  );
}
