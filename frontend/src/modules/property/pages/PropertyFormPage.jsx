import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import Spinner from '../../../components/common/Spinner';
import { propertyApi, apiErrorMessage } from '../services/propertyApi';

const TYPES = ['residential', 'commercial', 'mixed-use'];
const STATUSES = ['active', 'inactive'];

const emptyForm = {
  name: '',
  property_type: 'residential',
  address: '',
  city: '',
  postal_code: '',
  description: '',
  status: 'active',
  notes: '',
};

export default function PropertyFormPage() {
  const { id } = useParams();
  const isEdit = !!id;
  const navigate = useNavigate();
  const { hasPermission } = useAuth();

  const [form, setForm] = useState(emptyForm);
  const [fieldErrors, setFieldErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!isEdit) return;
    propertyApi
      .get(id)
      .then((p) =>
        setForm({
          name: p.name || '',
          property_type: p.property_type || 'residential',
          address: p.address || '',
          city: p.city || '',
          postal_code: p.postal_code || '',
          description: p.description || '',
          status: p.status || 'active',
          notes: p.notes || '',
        })
      )
      .catch((err) => setFormError(apiErrorMessage(err, 'Could not load the property.')))
      .finally(() => setLoading(false));
  }, [id, isEdit]);

  const set = (key) => (e) => {
    setForm((f) => ({ ...f, [key]: e.target.value }));
    setFieldErrors((fe) => ({ ...fe, [key]: undefined }));
  };

  const submit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setFormError('');
    setFieldErrors({});
    try {
      const saved = isEdit
        ? await propertyApi.update(id, form)
        : await propertyApi.create(form);
      navigate(`/properties/${saved.id}`);
    } catch (err) {
      const errors = err?.response?.data?.errors;
      if (errors) {
        const mapped = {};
        Object.entries(errors).forEach(([k, v]) => {
          mapped[k] = Array.isArray(v) ? v[0] : v;
        });
        setFieldErrors(mapped);
      } else {
        setFormError(apiErrorMessage(err, 'Could not save the property.'));
      }
    } finally {
      setSaving(false);
    }
  };

  const field = (key, label, input) => (
    <div>
      <label className="aprms-label" htmlFor={key}>{label}</label>
      {input}
      {fieldErrors[key] && <p className="aprms-error">{fieldErrors[key]}</p>}
    </div>
  );

  return (
    <PermissionGuard permission={isEdit ? 'properties.manage' : 'properties.manage'} showForbidden>
      <div className="mx-auto max-w-3xl space-y-5">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-xl font-bold text-slate-100">
              {isEdit ? 'Edit Property' : 'New Property'}
            </h2>
            <p className="mt-1 text-sm text-slate-400">
              {isEdit ? 'Update the property record.' : 'Register a new property in your agency.'}
            </p>
          </div>
          <Link to={isEdit ? `/properties/${id}` : '/properties'} className="aprms-btn-ghost">
            Cancel
          </Link>
        </div>

        {loading ? (
          <div className="aprms-card flex justify-center py-16"><Spinner /></div>
        ) : (
          <form onSubmit={submit} className="aprms-card space-y-4">
            {formError && (
              <div className="rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-300">
                {formError}
              </div>
            )}

            {field('name', 'Property name *', (
              <input id="name" className="aprms-input" value={form.name} onChange={set('name')} placeholder="e.g. Gulshan Residency" />
            ))}

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              {field('property_type', 'Property type *', (
                <select id="property_type" className="aprms-input" value={form.property_type} onChange={set('property_type')}>
                  {TYPES.map((t) => <option key={t} value={t}>{t.replace('-', ' ')}</option>)}
                </select>
              ))}
              {field('status', 'Status', (
                <select id="status" className="aprms-input" value={form.status} onChange={set('status')}>
                  {STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
                </select>
              ))}
            </div>

            {field('address', 'Street address *', (
              <input id="address" className="aprms-input" value={form.address} onChange={set('address')} placeholder="Plot / street / area" />
            ))}

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              {field('city', 'City *', (
                <input id="city" className="aprms-input" value={form.city} onChange={set('city')} placeholder="Karachi" />
              ))}
              {field('postal_code', 'Postal code', (
                <input id="postal_code" className="aprms-input" value={form.postal_code} onChange={set('postal_code')} placeholder="75300" />
              ))}
            </div>

            {field('description', 'Description', (
              <textarea id="description" className="aprms-input" rows={3} value={form.description} onChange={set('description')} placeholder="Short description of the property" />
            ))}

            {field('notes', 'Internal notes', (
              <textarea id="notes" className="aprms-input" rows={2} value={form.notes} onChange={set('notes')} placeholder="Notes visible only to your agency team" />
            ))}

            <div className="flex justify-end gap-3 pt-2">
              <Link to={isEdit ? `/properties/${id}` : '/properties'} className="aprms-btn-ghost">
                Cancel
              </Link>
              <button type="submit" className="aprms-btn-gold" disabled={saving}>
                {saving ? 'Saving…' : isEdit ? 'Save Changes' : 'Create Property'}
              </button>
            </div>
          </form>
        )}
      </div>
    </PermissionGuard>
  );
}
