import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import PermissionGuard from '../../../components/common/PermissionGuard';
import { tenantApi } from '../../leasing/services/leasingApi';
import { paymentApi, formatPKR, billingErrorMessage } from '../services/billingApi';

const METHODS = ['cash', 'bank_transfer', 'online', 'card', 'other'];

export default function PaymentFormPage() {
  const navigate = useNavigate();
  const [searchParams] = useState(() => new URLSearchParams(window.location.search));
  const [tenants, setTenants] = useState([]);
  const [form, setForm] = useState({
    tenant_id: searchParams.get('tenant_id') || '',
    payment_date: new Date().toISOString().slice(0, 10),
    amount: '', method: 'cash', reference: '', notes: '',
  });
  const [preview, setPreview] = useState(null);
  const [previewing, setPreviewing] = useState(false);
  const [posting, setPosting] = useState(false);
  const [errors, setErrors] = useState({});
  const [error, setError] = useState('');

  useEffect(() => {
    tenantApi.list({ per_page: 100 }).then((r) => setTenants(r.items)).catch(() => {});
  }, []);

  const set = (k) => (e) => {
    setForm((f) => ({ ...f, [k]: e.target.value }));
    setPreview(null); // amount/tenant changed → preview stale
  };

  const doPreview = async () => {
    if (!form.tenant_id || !form.amount) return;
    setPreviewing(true);
    setError('');
    try {
      const p = await paymentApi.preview(parseInt(form.tenant_id, 10), parseFloat(form.amount));
      setPreview(p);
    } catch (err) {
      setError(billingErrorMessage(err, 'Could not preview allocation.'));
    } finally {
      setPreviewing(false);
    }
  };

  const submit = async (e) => {
    e.preventDefault();
    if (!preview) {
      setError('Preview the allocation first, then confirm.');
      return;
    }
    setPosting(true);
    setErrors({});
    try {
      const saved = await paymentApi.record({
        tenant_id: parseInt(form.tenant_id, 10),
        payment_date: form.payment_date,
        amount: parseFloat(form.amount),
        method: form.method,
        reference: form.reference || undefined,
        notes: form.notes || undefined,
        idempotency_key: `ui-${form.tenant_id}-${form.amount}-${form.payment_date}-${Date.now()}`,
      });
      navigate(`/billing/payments/${saved.id}`);
    } catch (err) {
      if (err?.response?.status === 422) setErrors(err.response.data.errors || {});
      else setError(billingErrorMessage(err, 'Could not record the payment.'));
    } finally {
      setPosting(false);
    }
  };

  const labelCls = 'mb-1 block text-xs font-medium uppercase tracking-wider text-slate-400';
  const err = (k) => errors[k] && <p className="mt-1 text-xs text-red-400">{errors[k][0]}</p>;

  return (
    <PermissionGuard permission="payments.record" showForbidden>
      <div className="mx-auto max-w-2xl space-y-5">
        <div>
          <Link to="/billing/payments" className="text-sm text-gold hover:text-gold-light">← Payments</Link>
          <h2 className="mt-1 text-xl font-bold text-slate-100">Record Payment</h2>
          <p className="mt-1 text-sm text-slate-400">
            Manually recorded payment — no external confirmation is implied.
          </p>
        </div>

        {error && (
          <div className="rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-300">{error}</div>
        )}

        <form onSubmit={submit} className="aprms-card space-y-4">
          <div>
            <label className={labelCls} htmlFor="pay-tenant">Tenant *</label>
            <select id="pay-tenant" className="aprms-input" value={form.tenant_id} onChange={set('tenant_id')} required>
              <option value="">Select tenant…</option>
              {tenants.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
            </select>
            {err('tenant_id')}
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label className={labelCls}>Payment date *</label>
              <input type="date" className="aprms-input" value={form.payment_date} onChange={set('payment_date')} required max={new Date().toISOString().slice(0, 10)} />
              {err('payment_date')}
            </div>
            <div>
              <label className={labelCls} htmlFor="pay-amount">Amount (PKR) *</label>
              <input id="pay-amount" type="number" min="0.01" step="0.01" className="aprms-input" value={form.amount} onChange={set('amount')} required placeholder="0.00" />
              {err('amount')}
            </div>
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label className={labelCls}>Method *</label>
              <select className="aprms-input" value={form.method} onChange={set('method')}>
                {METHODS.map((m) => <option key={m} value={m}>{m.replace('_', ' ')}</option>)}
              </select>
              {err('method')}
            </div>
            <div>
              <label className={labelCls}>Reference</label>
              <input className="aprms-input" value={form.reference} onChange={set('reference')} placeholder="Bank ref, slip no…" />
            </div>
          </div>

          <div>
            <label className={labelCls}>Notes</label>
            <textarea className="aprms-input" rows={2} value={form.notes} onChange={set('notes')} />
          </div>

          <div className="flex justify-end">
            <button
              type="button"
              className="aprms-btn-ghost"
              disabled={previewing || !form.tenant_id || !form.amount}
              onClick={doPreview}
            >
              {previewing ? 'Calculating…' : 'Preview Allocation'}
            </button>
          </div>

          {preview && (
            <div className="rounded-lg bg-charcoal-800 p-4 ring-1 ring-charcoal-700">
              <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">
                Where {formatPKR(preview.amount)} will go
              </h3>
              <div className="mt-3 space-y-2">
                {preview.lines.length === 0 && (
                  <p className="text-sm text-slate-500">No outstanding charges — held as tenant credit.</p>
                )}
                {preview.lines.map((l, i) => (
                  <div key={i} className="flex items-center justify-between text-sm">
                    <span className="text-slate-300">
                      {l.label}
                      <span className="ml-2 text-xs text-slate-500">{l.bucket.replace('_', ' ')}</span>
                    </span>
                    <span className="font-medium text-gold">{formatPKR(l.amount)}</span>
                  </div>
                ))}
              </div>
              <div className="mt-3 flex justify-between border-t border-charcoal-700 pt-3 text-sm">
                <span className="text-slate-400">Unallocated credit</span>
                <span className="font-medium text-slate-200">{formatPKR(preview.unallocated)}</span>
              </div>
              <p className="mt-2 text-xs text-slate-500">
                Waterfall: late fees → utilities → current rent → oldest arrears. Fixed order, not configurable per payment.
              </p>
            </div>
          )}

          <div className="flex justify-end gap-2 border-t border-charcoal-700/60 pt-4">
            <Link to="/billing/payments" className="aprms-btn-ghost">Cancel</Link>
            <button
              type="submit"
              disabled={posting || !preview}
              className="aprms-btn-gold disabled:opacity-50"
              title={!preview ? 'Preview the allocation first' : ''}
            >
              {posting ? 'Posting…' : `Confirm & Post ${preview ? formatPKR(preview.amount) : ''}`}
            </button>
          </div>
        </form>
      </div>
    </PermissionGuard>
  );
}
