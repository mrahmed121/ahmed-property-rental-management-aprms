import { useEffect, useState } from 'react';
import { useAuth } from '../../../context/AuthContext';
import Spinner from '../../../components/common/Spinner';
import EmptyState from '../../../components/common/EmptyState';
import DataTable from './DataTable';
import StatusBadge from './StatusBadge';
import ConfirmDialog from './ConfirmDialog';
import { unitApi, buildingApi, apiErrorMessage } from '../services/propertyApi';

const STATUSES = ['vacant', 'occupied', 'reserved', 'maintenance', 'inactive'];
const TYPES = ['apartment', 'office', 'shop', 'room', 'studio', 'warehouse', 'other'];

const emptyForm = {
  building_id: '',
  unit_number: '',
  floor: '',
  unit_type: 'apartment',
  area_sqft: '',
  bedrooms: '',
  bathrooms: '',
  status: 'vacant',
  market_rent: '',
  notes: '',
};

/**
 * UnitManager — units of one property: filterable table, add/edit/archive.
 * Rendered as a tab inside the property detail page.
 */
export default function UnitManager({ propertyId }) {
  const { hasPermission } = useAuth();
  const canManage = hasPermission('units.manage');

  const [buildings, setBuildings] = useState([]);
  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [buildingFilter, setBuildingFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [page, setPage] = useState(1);

  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [fieldErrors, setFieldErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const [confirmId, setConfirmId] = useState(null);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState('');

  const load = async () => {
    setLoading(true);
    setError('');
    try {
      const { items, meta } = await unitApi.list({
        property_id: propertyId,
        building_id: buildingFilter || undefined,
        status: statusFilter || undefined,
        per_page: 12,
        page,
      });
      setRows(items);
      setMeta(meta);
    } catch (err) {
      setError(apiErrorMessage(err, 'Could not load units.'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    buildingApi
      .list({ property_id: propertyId, per_page: 100 })
      .then(({ items }) => setBuildings(items))
      .catch(() => {});
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [propertyId]);

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [propertyId, buildingFilter, statusFilter, page]);

  const openNew = () => {
    setEditing('new');
    setForm({ ...emptyForm, building_id: buildingFilter || (buildings[0]?.id || '') });
    setFieldErrors({});
  };

  const openEdit = (u) => {
    setEditing(u);
    setForm({
      building_id: u.building_id,
      unit_number: u.unit_number || '',
      floor: u.floor ?? '',
      unit_type: u.unit_type || 'apartment',
      area_sqft: u.area_sqft ?? '',
      bedrooms: u.bedrooms ?? '',
      bathrooms: u.bathrooms ?? '',
      status: u.status || 'vacant',
      market_rent: u.market_rent ?? '',
      notes: u.notes || '',
    });
    setFieldErrors({});
  };

  const num = (v) => (v === '' || v == null ? null : Number(v));

  const submit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setFieldErrors({});
    try {
      const payload = {
        building_id: parseInt(form.building_id, 10),
        unit_number: form.unit_number,
        floor: num(form.floor),
        unit_type: form.unit_type,
        area_sqft: num(form.area_sqft),
        bedrooms: num(form.bedrooms),
        bathrooms: num(form.bathrooms),
        status: form.status,
        market_rent: num(form.market_rent),
        notes: form.notes || null,
      };
      if (editing === 'new') await unitApi.create(payload);
      else await unitApi.update(editing.id, payload);
      setEditing(null);
      setNotice(editing === 'new' ? 'Unit added.' : 'Unit updated.');
      setPage(1);
      load();
    } catch (err) {
      const errors = err?.response?.data?.errors;
      if (errors) {
        const mapped = {};
        Object.entries(errors).forEach(([k, v]) => { mapped[k] = Array.isArray(v) ? v[0] : v; });
        setFieldErrors(mapped);
      } else {
        setNotice(apiErrorMessage(err, 'Could not save the unit.'));
      }
    } finally {
      setSaving(false);
    }
  };

  const doArchive = async () => {
    setBusy(true);
    try {
      await unitApi.archive(confirmId);
      setConfirmId(null);
      setNotice('Unit archived.');
      load();
    } catch (err) {
      setNotice(apiErrorMessage(err, 'Could not archive the unit.'));
    } finally {
      setBusy(false);
    }
  };

  const f = (key, label, input) => (
    <div>
      <label className="aprms-label">{label}</label>
      {input}
      {fieldErrors[key] && <p className="aprms-error">{fieldErrors[key]}</p>}
    </div>
  );

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-3">
        <select className="aprms-input !w-auto" value={buildingFilter} onChange={(e) => { setBuildingFilter(e.target.value); setPage(1); }}>
          <option value="">All buildings</option>
          {buildings.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
        </select>
        <select className="aprms-input !w-auto" value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}>
          <option value="">All statuses</option>
          {STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
        </select>
        <div className="ml-auto">
          {canManage && (
            <button className="aprms-btn-gold !py-2" onClick={openNew} disabled={buildings.length === 0}>
              + Add Unit
            </button>
          )}
        </div>
      </div>

      {notice && (
        <div className="rounded-lg border border-gold/30 bg-gold/10 px-4 py-2.5 text-sm text-gold-light">{notice}</div>
      )}

      {editing && (
        <form onSubmit={submit} className="aprms-card space-y-3">
          <h4 className="font-semibold text-slate-100">{editing === 'new' ? 'Add Unit' : `Edit Unit ${editing.unit_number}`}</h4>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            {f('building_id', 'Building *', (
              <select className="aprms-input" value={form.building_id} onChange={(e) => setForm({ ...form, building_id: e.target.value })} disabled={editing !== 'new'}>
                {buildings.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
              </select>
            ))}
            {f('unit_number', 'Unit number *', (
              <input className="aprms-input" value={form.unit_number} onChange={(e) => setForm({ ...form, unit_number: e.target.value })} placeholder="A-101" />
            ))}
            {f('unit_type', 'Unit type *', (
              <select className="aprms-input" value={form.unit_type} onChange={(e) => setForm({ ...form, unit_type: e.target.value })}>
                {TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
              </select>
            ))}
            {f('floor', 'Floor', (
              <input type="number" className="aprms-input" value={form.floor} onChange={(e) => setForm({ ...form, floor: e.target.value })} />
            ))}
            {f('area_sqft', 'Area (sq ft)', (
              <input type="number" min="0" step="0.01" className="aprms-input" value={form.area_sqft} onChange={(e) => setForm({ ...form, area_sqft: e.target.value })} />
            ))}
            {f('market_rent', 'Market rent', (
              <input type="number" min="0" step="0.01" className="aprms-input" value={form.market_rent} onChange={(e) => setForm({ ...form, market_rent: e.target.value })} />
            ))}
            {f('bedrooms', 'Bedrooms', (
              <input type="number" min="0" max="50" className="aprms-input" value={form.bedrooms} onChange={(e) => setForm({ ...form, bedrooms: e.target.value })} />
            ))}
            {f('bathrooms', 'Bathrooms', (
              <input type="number" min="0" max="50" className="aprms-input" value={form.bathrooms} onChange={(e) => setForm({ ...form, bathrooms: e.target.value })} />
            ))}
            {f('status', 'Status', (
              <select className="aprms-input" value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                {STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
              </select>
            ))}
          </div>
          {f('notes', 'Notes', (
            <textarea className="aprms-input" rows={2} value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} />
          ))}
          <div className="flex justify-end gap-2">
            <button type="button" className="aprms-btn-ghost" onClick={() => setEditing(null)}>Cancel</button>
            <button type="submit" className="aprms-btn-gold" disabled={saving}>{saving ? 'Saving…' : 'Save'}</button>
          </div>
        </form>
      )}

      <DataTable
        columns={[
          { key: 'unit_number', label: 'Unit', render: (r) => <span className="font-medium text-slate-100">{r.unit_number}</span> },
          { key: 'building', label: 'Building', render: (r) => r.building?.name || '—' },
          { key: 'unit_type', label: 'Type', render: (r) => <span className="capitalize">{r.unit_type}</span> },
          { key: 'bed', label: 'Bed/Bath', render: (r) => `${r.bedrooms ?? '—'} / ${r.bathrooms ?? '—'}` },
          { key: 'market_rent', label: 'Market rent', render: (r) => (r.market_rent != null ? Number(r.market_rent).toLocaleString() : '—') },
          { key: 'status', label: 'Status', render: (r) => <StatusBadge value={r.status} /> },
          {
            key: 'actions',
            label: '',
            className: 'text-right',
            render: (r) => canManage && (
              <div className="flex justify-end gap-2">
                <button className="aprms-btn-ghost !px-3 !py-1.5 !text-xs" onClick={() => openEdit(r)}>Edit</button>
                <button
                  className="inline-flex items-center rounded-lg border border-red-900/60 bg-red-950/40 px-3 py-1.5 text-xs font-medium text-red-300 hover:border-red-500/60"
                  onClick={() => setConfirmId(r.id)}
                >
                  Archive
                </button>
              </div>
            ),
          },
        ]}
        rows={rows}
        meta={meta}
        loading={loading}
        error={error}
        emptyTitle="No units yet"
        emptyHint={buildings.length === 0
          ? 'Add a building first — units live inside buildings.'
          : 'No units match these filters. Add the first unit of this property.'}
        emptyAction={canManage && buildings.length > 0
          ? <button className="aprms-btn-gold" onClick={openNew}>+ Add Unit</button>
          : undefined}
        onPage={setPage}
      />

      <ConfirmDialog
        open={!!confirmId}
        title="Archive unit?"
        message="The unit will be archived (soft-deleted). Its documents are preserved. You can restore it later."
        confirmLabel="Archive"
        busy={busy}
        onConfirm={doArchive}
        onCancel={() => setConfirmId(null)}
      />
    </div>
  );
}
