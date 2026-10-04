import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useAuth } from '../../../auth/AuthContext';
import Button from '../../../shared/Button';
import Card from '../../../shared/Card';
import ErrorAlert from '../../../shared/ErrorAlert';
import Input from '../../../shared/Input';
import Modal from '../../../shared/Modal';
import Table from '../../../shared/Table';
import StatusBadge from '../components/StatusBadge';
import { usePaginatedList, errorMessage } from '../hooks/usePaginatedList';
import {
  unitsApi,
  buildingsApi,
  propertiesApi,
  formatPKR,
  UNIT_STATUSES,
  UNIT_TYPES,
} from '../services/propertyApi';

const emptyForm = {
  building_id: '',
  unit_number: '',
  unit_type: 'apartment',
  floor: '',
  bedrooms: '',
  bathrooms: '',
  area_sqft: '',
  market_rent: '',
};

function UnitFormModal({ open, initial, buildings, saving, serverError, onClose, onSave }) {
  const [form, setForm] = useState(emptyForm);
  const [errors, setErrors] = useState({});

  useEffect(() => {
    if (open) {
      setForm({
        building_id: initial?.building_id ? String(initial.building_id) : '',
        unit_number: initial?.unit_number || '',
        unit_type: initial?.unit_type || 'apartment',
        floor: initial?.floor ?? '',
        bedrooms: initial?.bedrooms ?? '',
        bathrooms: initial?.bathrooms ?? '',
        area_sqft: initial?.area_sqft ?? '',
        market_rent: initial?.market_rent ?? '',
      });
      setErrors({});
    }
  }, [open, initial]);

  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

  const num = (v) => (v === '' ? null : Number(v));

  const validate = () => {
    const e = {};
    if (!form.building_id) e.building_id = 'Building is required.';
    if (!form.unit_number.trim()) e.unit_number = 'Unit number is required.';
    if (form.market_rent !== '' && (Number.isNaN(Number(form.market_rent)) || Number(form.market_rent) < 0)) {
      e.market_rent = 'Rent must be a non-negative number.';
    }
    if (form.area_sqft !== '' && (Number.isNaN(Number(form.area_sqft)) || Number(form.area_sqft) < 0)) {
      e.area_sqft = 'Area must be a non-negative number.';
    }
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  const submit = () => {
    if (!validate()) return;
    onSave(
      {
        building_id: Number(form.building_id),
        unit_number: form.unit_number.trim(),
        unit_type: form.unit_type,
        floor: form.floor === '' ? null : num(form.floor),
        bedrooms: form.bedrooms === '' ? null : num(form.bedrooms),
        bathrooms: form.bathrooms === '' ? null : num(form.bathrooms),
        area_sqft: form.area_sqft === '' ? null : num(form.area_sqft),
        market_rent: form.market_rent === '' ? null : num(form.market_rent),
      },
      setErrors
    );
  };

  const numField = (key, label, min = 0) => (
    <Input
      label={label}
      name={key}
      type="number"
      min={min}
      step="any"
      value={form[key]}
      onChange={(e) => set(key, e.target.value)}
      error={errors[key]}
    />
  );

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={initial?.id ? 'Edit unit' : 'Add unit'}
      onConfirm={submit}
      confirmLabel={saving ? 'Saving…' : 'Save unit'}
      loading={saving}
    >
      <div className="space-y-4 text-left">
        {serverError && <ErrorAlert message={serverError} />}
        <div>
          <label htmlFor="u_building_id" className="mb-1.5 block text-sm font-medium text-gray-300">
            Building *
          </label>
          <select
            id="u_building_id"
            value={form.building_id}
            onChange={(e) => set('building_id', e.target.value)}
            className="focus-gold w-full rounded-lg border border-charcoal-600 bg-charcoal-800 px-3 py-2 text-sm text-gray-100"
          >
            <option value="">Select building…</option>
            {buildings.map((b) => (
              <option key={b.id} value={b.id}>
                {b.name}{b.property ? ` — ${b.property.name}` : ''}
              </option>
            ))}
          </select>
          {errors.building_id && <p className="mt-1 text-xs text-red-400">{errors.building_id}</p>}
        </div>
        <div className="grid grid-cols-2 gap-4">
          <Input
            label="Unit number *"
            name="unit_number"
            value={form.unit_number}
            onChange={(e) => set('unit_number', e.target.value)}
            error={errors.unit_number}
            placeholder="e.g. A-101"
          />
          <div>
            <label htmlFor="u_unit_type" className="mb-1.5 block text-sm font-medium text-gray-300">
              Unit type *
            </label>
            <select
              id="u_unit_type"
              value={form.unit_type}
              onChange={(e) => set('unit_type', e.target.value)}
              className="focus-gold w-full rounded-lg border border-charcoal-600 bg-charcoal-800 px-3 py-2 text-sm text-gray-100"
            >
              {UNIT_TYPES.map((t) => (
                <option key={t} value={t} className="capitalize">{t.replace('-', ' ')}</option>
              ))}
            </select>
          </div>
        </div>
        <div className="grid grid-cols-2 gap-4">
          {numField('floor', 'Floor')}
          {numField('market_rent', 'Monthly rent (PKR)')}
        </div>
        <div className="grid grid-cols-3 gap-4">
          {numField('bedrooms', 'Bedrooms')}
          {numField('bathrooms', 'Bathrooms')}
          {numField('area_sqft', 'Area (sq ft)')}
        </div>
      </div>
    </Modal>
  );
}

export default function UnitsPage() {
  const { hasPermission } = useAuth();
  const canManage = hasPermission('units.manage');
  const [searchParams] = useSearchParams();
  const presetBuilding = searchParams.get('building_id') || '';
  const presetStatus = searchParams.get('status') || '';

  const { items, meta, setFilter, setPage, loading, error, reload } =
    usePaginatedList(unitsApi.list, {
      per_page: 15,
      ...(presetBuilding ? { building_id: presetBuilding } : {}),
      ...(presetStatus ? { status: presetStatus } : {}),
    });

  const [properties, setProperties] = useState([]);
  const [buildings, setBuildings] = useState([]);
  const [search, setSearch] = useState('');
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [saving, setSaving] = useState(false);
  const [serverError, setServerError] = useState(null);
  const [archiveTarget, setArchiveTarget] = useState(null);
  const [archiving, setArchiving] = useState(false);
  const [toast, setToast] = useState(null);

  useEffect(() => {
    Promise.all([
      propertiesApi.list({ per_page: 100 }).catch(() => ({ items: [] })),
      buildingsApi.list({ per_page: 200 }).catch(() => ({ items: [] })),
    ]).then(([{ items: p }, { items: b }]) => {
      setProperties(p);
      setBuildings(b);
    });
  }, []);

  useEffect(() => {
    const t = setTimeout(() => setFilter('search', search), 400);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

  useEffect(() => {
    if (toast) {
      const t = setTimeout(() => setToast(null), 3500);
      return () => clearTimeout(t);
    }
  }, [toast]);

  const handleSave = async (payload, setFieldErrors) => {
    setSaving(true);
    setServerError(null);
    try {
      if (editing?.id) {
        await unitsApi.update(editing.id, payload);
        setToast('Unit updated.');
      } else {
        await unitsApi.create(payload);
        setToast('Unit created.');
      }
      setModalOpen(false);
      reload();
    } catch (err) {
      const fieldErrors = err.response?.data?.errors;
      if (fieldErrors) {
        const flat = {};
        for (const [k, v] of Object.entries(fieldErrors)) flat[k] = v[0];
        setFieldErrors(flat);
      } else {
        setServerError(errorMessage(err));
      }
    } finally {
      setSaving(false);
    }
  };

  const handleArchive = async () => {
    if (!archiveTarget) return;
    setArchiving(true);
    try {
      await unitsApi.archive(archiveTarget.id);
      setToast(`Unit "${archiveTarget.unit_number}" archived.`);
      setArchiveTarget(null);
      reload();
    } catch (err) {
      setArchiveTarget({ ...archiveTarget, error: errorMessage(err) });
    } finally {
      setArchiving(false);
    }
  };

  const totalPages = Math.max(1, Math.ceil(meta.total / meta.per_page));

  const columns = [
    {
      key: 'unit_number',
      label: 'Unit',
      render: (r) => (
        <Link to={`/units/${r.id}`} className="font-medium text-gold hover:underline">
          {r.unit_number}
        </Link>
      ),
    },
    {
      key: 'building',
      label: 'Building',
      render: (r) =>
        r.building ? (
          <Link to={`/buildings/${r.building.id}`} className="text-gray-300 hover:text-gold hover:underline">
            {r.building.name}
          </Link>
        ) : '—',
    },
    {
      key: 'property',
      label: 'Property',
      render: (r) =>
        r.property ? (
          <Link to={`/properties/${r.property.id}`} className="text-gray-300 hover:text-gold hover:underline">
            {r.property.name}
          </Link>
        ) : '—',
    },
    { key: 'unit_type', label: 'Type', render: (r) => r.unit_type?.replace('-', ' ') || '—' },
    { key: 'market_rent', label: 'Rent', align: 'right', render: (r) => formatPKR(r.market_rent) },
    { key: 'status', label: 'Status', render: (r) => <StatusBadge status={r.status} /> },
  ];

  if (canManage) {
    columns.push({
      key: 'actions',
      label: 'Actions',
      render: (r) => (
        <div className="flex gap-2">
          <Button size="sm" variant="secondary" onClick={() => { setEditing(r); setServerError(null); setModalOpen(true); }}>
            Edit
          </Button>
          <Button size="sm" variant="danger" onClick={() => setArchiveTarget(r)}>
            Archive
          </Button>
        </div>
      ),
    });
  }

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-gray-100">Units</h1>
          <p className="mt-1 text-sm text-gray-500">
            {meta.total} {meta.total === 1 ? 'unit' : 'units'}
          </p>
        </div>
        {canManage && (
          <Button onClick={() => { setEditing(null); setServerError(null); setModalOpen(true); }}>
            Add unit
          </Button>
        )}
      </div>

      {toast && (
        <div className="rounded-lg border border-emerald-800 bg-emerald-950/40 px-4 py-3 text-sm text-emerald-300">
          {toast}
        </div>
      )}

      <Card>
        <div className="mb-4 flex flex-wrap gap-3">
          <div className="min-w-52 flex-1">
            <Input
              name="search"
              placeholder="Search by unit number…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
          <select
            aria-label="Filter by property"
            onChange={(e) => setFilter('property_id', e.target.value)}
            className="focus-gold rounded-lg border border-charcoal-600 bg-charcoal-800 px-3 py-2 text-sm text-gray-100"
          >
            <option value="">All properties</option>
            {properties.map((p) => (
              <option key={p.id} value={p.id}>{p.name}</option>
            ))}
          </select>
          <select
            aria-label="Filter by building"
            defaultValue={presetBuilding}
            onChange={(e) => setFilter('building_id', e.target.value)}
            className="focus-gold rounded-lg border border-charcoal-600 bg-charcoal-800 px-3 py-2 text-sm text-gray-100"
          >
            <option value="">All buildings</option>
            {buildings.map((b) => (
              <option key={b.id} value={b.id}>{b.name}</option>
            ))}
          </select>
          <select
            aria-label="Filter by status"
            defaultValue={presetStatus}
            onChange={(e) => setFilter('status', e.target.value)}
            className="focus-gold rounded-lg border border-charcoal-600 bg-charcoal-800 px-3 py-2 text-sm text-gray-100"
          >
            <option value="">All statuses</option>
            {UNIT_STATUSES.map((s) => (
              <option key={s} value={s} className="capitalize">{s}</option>
            ))}
          </select>
        </div>

        <ErrorAlert message={error} onRetry={reload} />

        {!error && (
          <Table
            columns={columns}
            rows={items}
            loading={loading}
            emptyTitle="No units yet"
            emptyMessage="Add the first unit to a building."
          />
        )}

        {!loading && totalPages > 1 && (
          <div className="mt-4 flex items-center justify-between text-sm text-gray-400">
            <span>Page {meta.current_page} of {totalPages}</span>
            <div className="flex gap-2">
              <Button size="sm" variant="secondary" disabled={meta.current_page <= 1} onClick={() => setPage(meta.current_page - 1)}>
                Previous
              </Button>
              <Button size="sm" variant="secondary" disabled={meta.current_page >= totalPages} onClick={() => setPage(meta.current_page + 1)}>
                Next
              </Button>
            </div>
          </div>
        )}
      </Card>

      <UnitFormModal
        open={modalOpen}
        initial={editing}
        buildings={buildings}
        saving={saving}
        serverError={serverError}
        onClose={() => setModalOpen(false)}
        onSave={handleSave}
      />

      <Modal
        open={!!archiveTarget}
        onClose={() => setArchiveTarget(null)}
        title="Archive unit"
        onConfirm={handleArchive}
        confirmLabel={archiving ? 'Archiving…' : 'Archive'}
        confirmVariant="danger"
        loading={archiving}
      >
        <p>
          Archive unit <strong className="text-gray-100">“{archiveTarget?.unit_number}”</strong>?
          This can be undone.
        </p>
        {archiveTarget?.error && <p className="mt-3 text-red-400">{archiveTarget.error}</p>}
      </Modal>
    </div>
  );
}
