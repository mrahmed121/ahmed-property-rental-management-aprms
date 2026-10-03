import { useEffect, useState } from 'react';
import { useAuth } from '../../../context/AuthContext';
import Spinner from '../../../components/common/Spinner';
import EmptyState from '../../../components/common/EmptyState';
import StatusBadge from './StatusBadge';
import ConfirmDialog from './ConfirmDialog';
import { buildingApi, apiErrorMessage } from '../services/propertyApi';

/**
 * BuildingManager — buildings of one property: list, add, edit, archive.
 * Rendered as a tab inside the property detail page.
 */
export default function BuildingManager({ propertyId }) {
  const { hasPermission } = useAuth();
  const canManage = hasPermission('buildings.manage');

  const [buildings, setBuildings] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [editing, setEditing] = useState(null); // building object or 'new'
  const [form, setForm] = useState({ name: '', floors: '', description: '', status: 'active', notes: '' });
  const [fieldErrors, setFieldErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const [confirmId, setConfirmId] = useState(null);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState('');

  const load = async () => {
    setLoading(true);
    setError('');
    try {
      const { items } = await buildingApi.list({ property_id: propertyId, per_page: 100 });
      setBuildings(items);
    } catch (err) {
      setError(apiErrorMessage(err, 'Could not load buildings.'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [propertyId]);

  const openNew = () => {
    setEditing('new');
    setForm({ name: '', floors: '', description: '', status: 'active', notes: '' });
    setFieldErrors({});
  };

  const openEdit = (b) => {
    setEditing(b);
    setForm({
      name: b.name || '',
      floors: b.floors ?? '',
      description: b.description || '',
      status: b.status || 'active',
      notes: b.notes || '',
    });
    setFieldErrors({});
  };

  const submit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setFieldErrors({});
    try {
      const payload = {
        ...form,
        floors: form.floors === '' ? null : parseInt(form.floors, 10),
        property_id: propertyId,
      };
      if (editing === 'new') await buildingApi.create(payload);
      else await buildingApi.update(editing.id, payload);
      setEditing(null);
      setNotice(editing === 'new' ? 'Building added.' : 'Building updated.');
      load();
    } catch (err) {
      const errors = err?.response?.data?.errors;
      if (errors) {
        const mapped = {};
        Object.entries(errors).forEach(([k, v]) => { mapped[k] = Array.isArray(v) ? v[0] : v; });
        setFieldErrors(mapped);
      } else {
        setNotice(apiErrorMessage(err, 'Could not save the building.'));
      }
    } finally {
      setSaving(false);
    }
  };

  const doArchive = async () => {
    setBusy(true);
    try {
      await buildingApi.archive(confirmId);
      setConfirmId(null);
      setNotice('Building archived. Its units were archived with it.');
      load();
    } catch (err) {
      setNotice(apiErrorMessage(err, 'Could not archive the building.'));
    } finally {
      setBusy(false);
    }
  };

  if (loading) {
    return <div className="flex justify-center py-10"><Spinner /></div>;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <p className="text-sm text-slate-400">{buildings.length} building{buildings.length === 1 ? '' : 's'}</p>
        {canManage && (
          <button className="aprms-btn-gold !py-2" onClick={openNew}>+ Add Building</button>
        )}
      </div>

      {notice && (
        <div className="rounded-lg border border-gold/30 bg-gold/10 px-4 py-2.5 text-sm text-gold-light">{notice}</div>
      )}
      {error && (
        <div className="rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-2.5 text-sm text-red-300">{error}</div>
      )}

      {editing && (
        <form onSubmit={submit} className="aprms-card space-y-3">
          <h4 className="font-semibold text-slate-100">{editing === 'new' ? 'Add Building' : 'Edit Building'}</h4>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label className="aprms-label">Name *</label>
              <input className="aprms-input" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Block A" />
              {fieldErrors.name && <p className="aprms-error">{fieldErrors.name}</p>}
            </div>
            <div>
              <label className="aprms-label">Floors</label>
              <input type="number" min="0" max="200" className="aprms-input" value={form.floors} onChange={(e) => setForm({ ...form, floors: e.target.value })} placeholder="5" />
              {fieldErrors.floors && <p className="aprms-error">{fieldErrors.floors}</p>}
            </div>
          </div>
          <div>
            <label className="aprms-label">Description</label>
            <textarea className="aprms-input" rows={2} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
          </div>
          <div className="flex justify-end gap-2">
            <button type="button" className="aprms-btn-ghost" onClick={() => setEditing(null)}>Cancel</button>
            <button type="submit" className="aprms-btn-gold" disabled={saving}>{saving ? 'Saving…' : 'Save'}</button>
          </div>
        </form>
      )}

      {buildings.length === 0 && !editing ? (
        <EmptyState
          icon="🏢"
          title="No buildings yet"
          hint="Add the first building of this property — units live inside buildings."
          action={canManage ? <button className="aprms-btn-gold" onClick={openNew}>+ Add Building</button> : undefined}
        />
      ) : (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
          {buildings.map((b) => (
            <div key={b.id} className="aprms-card">
              <div className="flex items-start justify-between gap-2">
                <div>
                  <p className="font-semibold text-slate-100">{b.name}</p>
                  <p className="mt-0.5 text-xs text-slate-500">
                    {b.floors != null ? `${b.floors} floors · ` : ''}{b.units_count ?? 0} units
                  </p>
                </div>
                <StatusBadge value={b.status} />
              </div>
              {canManage && (
                <div className="mt-3 flex gap-2">
                  <button className="aprms-btn-ghost !px-3 !py-1.5 !text-xs" onClick={() => openEdit(b)}>Edit</button>
                  <button
                    className="inline-flex items-center rounded-lg border border-red-900/60 bg-red-950/40 px-3 py-1.5 text-xs font-medium text-red-300 hover:border-red-500/60"
                    onClick={() => setConfirmId(b.id)}
                  >
                    Archive
                  </button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      <ConfirmDialog
        open={!!confirmId}
        title="Archive building?"
        message="The building and its units will be archived. Documents are preserved. You can restore it later."
        confirmLabel="Archive"
        busy={busy}
        onConfirm={doArchive}
        onCancel={() => setConfirmId(null)}
      />
    </div>
  );
}
