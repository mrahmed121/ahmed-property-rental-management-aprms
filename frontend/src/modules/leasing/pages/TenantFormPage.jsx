import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import Spinner from '../../../components/common/Spinner';
import { tenantApi, leasingErrorMessage } from '../services/leasingApi';

const KYC_OPTIONS = ['pending', 'verified', 'rejected'];

export default function TenantFormPage() {
  const { id } = useParams();
  const isEdit = !!id;
  const navigate = useNavigate();
  const { hasPermission } = useAuth();
  const [form, setForm] = useState({
    first_name: '', last_name: '', email: '', phone: '',
    city: '', address: '', national_id: '', kyc_status: 'pending', notes: '',
  });
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState({});
  const [error, setError] = useState('');

  useEffect(() => {
    if (!isEdit) return;
    tenantApi.get(id)
      .then((t) => setForm({
        first_name: t.first_name || '', last_name: t.last_name || '',
        email: t.email || '', phone: t.phone || '', city: t.city || '',
        address: t.address || '', national_id: '', kyc_status: t.kyc_status || 'pending',
        notes: t.notes || '',
      }))
      .catch((err) => setError(leasingErrorMessage(err, 'Could not load the tenant.')))
      .finally(() => setLoading(false));
  }, [id, isEdit]);

  const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));

  const submit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setErrors({});
    setError('');
    try {
      const payload = { ...form };
      if (!payload.national_id) delete payload.national_id; // never resend stored ID
      const saved = isEdit ? await tenantApi.update(id, payload) : await tenantApi.create(payload);
      navigate(`/tenants/${saved.id}`);
    } catch (err) {
      if (err?.response?.status === 422) setErrors(err.response.data.errors || {});
      else setError(leasingErrorMessage(err, 'Could not save the tenant.'));
    } finally {
      setSaving(false);
    }
  };

  const field = (key, label, props = {}) => (
    <div>
      <label className="mb-1 block text-xs font-medium uppercase tracking-wider text-slate-400">{label}</label>
      <input className="aprms-input" value={form[key]} onChange={set(key)} {...props} />
      {errors[key] && <p className="mt-1 text-xs text-red-400">{errors[key][0]}</p>}
    </div>
  );

  return (
    <PermissionGuard permission={isEdit ? 'tenants.manage' : 'tenants.manage'} showForbidden>
      <div className="mx-auto max-w-2xl space-y-5">
        <div>
          <Link to={isEdit ? `/tenants/${id}` : '/tenants'} className="text-sm text-gold hover:text-gold-light">← Back</Link>
          <h2 className="mt-1 text-xl font-bold text-slate-100">{isEdit ? 'Edit Tenant' : 'New Tenant'}</h2>
        </div>

        {error && (
          <div className="rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-300">{error}</div>
        )}

        {loading ? <Spinner /> : (
          <form onSubmit={submit} className="aprms-card space-y-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              {field('first_name', 'First name *', { required: true })}
              {field('last_name', 'Last name *', { required: true })}
            </div>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              {field('email', 'Email', { type: 'email' })}
              {field('phone', 'Phone')}
            </div>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              {field('city', 'City')}
              {!isEdit && field('national_id', 'National ID', { placeholder: 'Stored securely, shown masked' })}
            </div>
            <div>
              <label className="mb-1 block text-xs font-medium uppercase tracking-wider text-slate-400">Address</label>
              <textarea className="aprms-input" rows={2} value={form.address} onChange={set('address')} />
            </div>
            <div>
              <label className="mb-1 block text-xs font-medium uppercase tracking-wider text-slate-400">KYC status</label>
              <select className="aprms-input" value={form.kyc_status} onChange={set('kyc_status')}>
                {KYC_OPTIONS.map((o) => <option key={o} value={o}>{o}</option>)}
              </select>
              <p className="mt-1 text-xs text-slate-500">Only KYC-verified tenants can have applications approved.</p>
            </div>
            <div>
              <label className="mb-1 block text-xs font-medium uppercase tracking-wider text-slate-400">Notes</label>
              <textarea className="aprms-input" rows={2} value={form.notes} onChange={set('notes')} />
            </div>
            <div className="flex justify-end gap-2">
              <Link to={isEdit ? `/tenants/${id}` : '/tenants'} className="aprms-btn-ghost">Cancel</Link>
              <button type="submit" disabled={saving} className="aprms-btn-gold disabled:opacity-50">
                {saving ? 'Saving…' : isEdit ? 'Save Changes' : 'Create Tenant'}
              </button>
            </div>
          </form>
        )}
      </div>
    </PermissionGuard>
  );
}
