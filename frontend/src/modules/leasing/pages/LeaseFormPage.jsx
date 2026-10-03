import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import PermissionGuard from '../../../components/common/PermissionGuard';
import { tenantApi, leaseApi, applicationApi, leasingErrorMessage } from '../services/leasingApi';
import { propertyApi, unitApi } from '../../property/services/propertyApi';

export default function LeaseFormPage() {
  const navigate = useNavigate();
  const [searchParams] = useState(() => new URLSearchParams(window.location.search));
  const [tenants, setTenants] = useState([]);
  const [properties, setProperties] = useState([]);
  const [units, setUnits] = useState([]);
  const [form, setForm] = useState({
    tenant_id: '', property_id: '', unit_id: '',
    application_id: searchParams.get('application_id') || '',
    start_date: '', end_date: '', monthly_rent: '', deposit_amount: '', terms: '',
  });
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState({});
  const [error, setError] = useState('');

  useEffect(() => {
    tenantApi.list({ per_page: 100, status: 'prospective' }).then((r) => setTenants(r.items)).catch(() => {});
    tenantApi.list({ per_page: 100, status: 'active' }).then((r) => setTenants((t) => [...t, ...r.items])).catch(() => {});
    propertyApi.list({ per_page: 100, status: 'active' }).then((r) => setProperties(r.items)).catch(() => {});
  }, []);

  // Prefill from an approved application.
  useEffect(() => {
    const appId = searchParams.get('application_id');
    if (!appId) return;
    applicationApi.get(appId)
      .then((a) => setForm((f) => ({
        ...f,
        tenant_id: String(a.tenant_id || ''),
        property_id: String(a.property_id || ''),
        unit_id: String(a.unit_id || ''),
        application_id: String(a.id),
      })))
      .catch(() => {});
  }, [searchParams]);

  useEffect(() => {
    if (!form.property_id) { setUnits([]); return; }
    unitApi.list({ property_id: form.property_id, per_page: 200 })
      .then((r) => setUnits(r.items.filter((u) => ['vacant', 'reserved'].includes(u.status) && !u.trashed)))
      .catch(() => {});
  }, [form.property_id]);

  const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));
  const err = (k) => errors[k] && <p className="mt-1 text-xs text-red-400">{errors[k][0]}</p>;

  const submit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setErrors({});
    try {
      const payload = {
        tenant_id: parseInt(form.tenant_id, 10),
        unit_id: parseInt(form.unit_id, 10),
        start_date: form.start_date,
        end_date: form.end_date,
        monthly_rent: parseFloat(form.monthly_rent),
        deposit_amount: form.deposit_amount ? parseFloat(form.deposit_amount) : undefined,
        application_id: form.application_id ? parseInt(form.application_id, 10) : undefined,
        terms: form.terms || undefined,
      };
      const saved = await leaseApi.create(payload);
      navigate(`/leases/${saved.id}`);
    } catch (err2) {
      if (err2?.response?.status === 422) setErrors(err2.response.data.errors || {});
      else setError(leasingErrorMessage(err2, 'Could not create the lease.'));
    } finally {
      setSaving(false);
    }
  };

  const selectCls = 'aprms-input';
  const labelCls = 'mb-1 block text-xs font-medium uppercase tracking-wider text-slate-400';

  return (
    <PermissionGuard permission="leases.manage" showForbidden>
      <div className="mx-auto max-w-2xl space-y-5">
        <div>
          <Link to="/leases" className="text-sm text-gold hover:text-gold-light">← Leases</Link>
          <h2 className="mt-1 text-xl font-bold text-slate-100">New Lease</h2>
          <p className="mt-1 text-sm text-slate-400">Created as a draft. Activation happens on the lease screen.</p>
        </div>

        {error && <div className="rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-300">{error}</div>}

        <form onSubmit={submit} className="aprms-card space-y-4">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label className={labelCls}>Tenant *</label>
              <select className={selectCls} value={form.tenant_id} onChange={set('tenant_id')} required>
                <option value="">Select tenant…</option>
                {tenants.map((t) => <option key={t.id} value={t.id}>{t.name} ({t.kyc_status})</option>)}
              </select>
              {err('tenant_id')}
            </div>
            <div>
              <label className={labelCls}>Property *</label>
              <select className={selectCls} value={form.property_id} onChange={set('property_id')} required>
                <option value="">Select property…</option>
                {properties.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
              </select>
              {err('property_id')}
            </div>
          </div>
          <div>
            <label className={labelCls}>Unit *</label>
            <select className={selectCls} value={form.unit_id} onChange={set('unit_id')} required>
              <option value="">Select unit…</option>
              {units.map((u) => <option key={u.id} value={u.id}>{u.unit_number} — {u.unit_type} ({u.status})</option>)}
            </select>
            {err('unit_id')}
            <p className="mt-1 text-xs text-slate-500">Only vacant or reserved units can take a new lease.</p>
          </div>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label className={labelCls}>Start date *</label>
              <input type="date" className={selectCls} value={form.start_date} onChange={set('start_date')} required />
              {err('start_date')}
            </div>
            <div>
              <label className={labelCls}>End date *</label>
              <input type="date" className={selectCls} value={form.end_date} onChange={set('end_date')} required />
              {err('end_date')}
            </div>
          </div>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label className={labelCls}>Monthly rent *</label>
              <input type="number" min="0" step="0.01" className={selectCls} value={form.monthly_rent} onChange={set('monthly_rent')} required />
              {err('monthly_rent')}
            </div>
            <div>
              <label className={labelCls}>Deposit amount</label>
              <input type="number" min="0" step="0.01" className={selectCls} value={form.deposit_amount} onChange={set('deposit_amount')} />
              {err('deposit_amount')}
            </div>
          </div>
          <div>
            <label className={labelCls}>Terms</label>
            <textarea className={selectCls} rows={3} value={form.terms} onChange={set('terms')} placeholder="Payment terms, notice period, house rules…" />
          </div>
          <div className="flex justify-end gap-2">
            <Link to="/leases" className="aprms-btn-ghost">Cancel</Link>
            <button type="submit" disabled={saving} className="aprms-btn-gold disabled:opacity-50">
              {saving ? 'Creating…' : 'Create Draft Lease'}
            </button>
          </div>
        </form>
      </div>
    </PermissionGuard>
  );
}
