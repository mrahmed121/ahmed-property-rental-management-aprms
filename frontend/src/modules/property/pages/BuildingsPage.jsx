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
import { buildingsApi, propertiesApi } from '../services/propertyApi';

const emptyForm = { name: '', property_id: '', floors: '', description: '' };

function BuildingFormModal({ open, initial, properties, saving, serverError, onClose, onSave }) {
  const [form, setForm] = useState(emptyForm);
  const [errors, setErrors] = useState({});

  useEffect(() => {
    if (open) {
      setForm({
        name: initial?.name || '',
        property_id: initial?.property_id ? String(initial.property_id) : '',
        floors: initial?.floors ?? '',
        description: initial?.description || '',
      });
      setErrors({});
    }
  }, [open, initial]);

  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

  const validate = () => {
    const e = {};
    if (!form.name.trim()) e.name = 'Name is required.';
    if (!form.property_id) e.property_id = 'Property is required.';
    if (form.floors !== '' && (Number.isNaN(Number(form.floors)) || Number(form.floors) < 0)) {
      e.floors = 'Floors must be a non-negative number.';
    }
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  const submit = () => {
    if (!validate()) return;
    onSave(
      {
        name: form.name.trim(),
        property_id: Number(form.property_id),
        floors: form.floors === '' ? null : Number(form.floors),
        description: form.description.trim() || null,
      },
      setErrors
    );
  };

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={initial?.id ? 'Edit building' : 'Add building'}
      onConfirm={submit}
      confirmLabel={saving ? 'Saving…' : 'Save building'}
      loading={saving}
    >
      <div className="space-y-4 text-left">
        {serverError && <ErrorAlert message={serverError} />}
        <div>
          <label htmlFor="b_property_id" className="mb-1.5 block text-sm font-medium text-gray-300">
            Property *
          </label>
          <select
            id="b_property_id"
            value={form.property_id}
            onChange={(e) => set('property_id', e.target.value)}
            className="focus-gold w-full rounded-lg border border-charcoal-600 bg-charcoal-800 px-3 py-2 text-sm text-gray-100"
          >
            <option value="">Select property…</option>
            {properties.map((p) => (
              <option key={p.id} value={p.id}>{p.name}</option>
            ))}
          </select>
          {errors.property_id && <p className="mt-1 text-xs text-red-400">{errors.property_id}</p>}
        </div>
        <Input
          label="Building name *"
          name="name"
          value={form.name}
          onChange={(e) => set('name', e.target.value)}
          error={errors.name}
          placeholder="e.g. Block A"
        />
        <Input
          label="Floors"
          name="floors"
          type="number"
          min="0"
          value={form.floors}
          onChange={(e) => set('floors', e.target.value)}
          error={errors.floors}
        />
        <div>
          <label htmlFor="b_description" className="mb-1.5 block text-sm font-medium text-gray-300">
            Description
          </label>
          <textarea
            id="b_description"
            rows={2}
            value={form.description}
            onChange={(e) => set('description', e.target.value)}
            className="focus-gold w-full rounded-lg border border-charcoal-600 bg-charcoal-800 px-3 py-2 text-sm text-gray-100"
          />
        </div>
      </div>
    </Modal>
  );
}

export default function BuildingsPage() {
  const { hasPermission } = useAuth();
  const canManage = hasPermission('buildings.manage');
  const [searchParams] = useSearchParams();
  const presetProperty = searchParams.get('property_id') || '';

  const { items, meta, setFilter, setPage, loading, error, reload } =
    usePaginatedList(buildingsApi.list, {
      per_page: 15,
      ...(presetProperty ? { property_id: presetProperty } : {}),
    });

  const [properties, setProperties] = useState([]);
  const [search, setSearch] = useState('');
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [saving, setSaving] = useState(false);
  const [serverError, setServerError] = useState(null);
  const [archiveTarget, setArchiveTarget] = useState(null);
  const [archiving, setArchiving] = useState(false);
  const [toast, setToast] = useState(null);

  useEffect(() => {
    propertiesApi.list({ per_page: 100 }).then(({ items: p }) => setProperties(p)).catch(() => {});
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
        await buildingsApi.update(editing.id, payload);
        setToast('Building updated.');
      } else {
        await buildingsApi.create(payload);
        setToast('Building created.');
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
      await buildingsApi.archive(archiveTarget.id);
      setToast(`Building "${archiveTarget.name}" archived.`);
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
      key: 'name',
      label: 'Building',
      render: (r) => (
        <Link to={`/buildings/${r.id}`} className="font-medium text-gold hover:underline">
          {r.name}
        </Link>
      ),
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
    { key: 'floors', label: 'Floors', align: 'right', render: (r) => r.floors ?? '—' },
    { key: 'units_count', label: 'Units', align: 'right', render: (r) => r.units_count ?? '—' },
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
          <h1 className="text-2xl font-bold text-gray-100">Buildings</h1>
          <p className="mt-1 text-sm text-gray-500">
            {meta.total} {meta.total === 1 ? 'building' : 'buildings'}
          </p>
        </div>
        {canManage && (
          <Button onClick={() => { setEditing(null); setServerError(null); setModalOpen(true); }}>
            Add building
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
              placeholder="Search buildings…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
          <select
            aria-label="Filter by property"
            defaultValue={presetProperty}
            onChange={(e) => setFilter('property_id', e.target.value)}
            className="focus-gold rounded-lg border border-charcoal-600 bg-charcoal-800 px-3 py-2 text-sm text-gray-100"
          >
            <option value="">All properties</option>
            {properties.map((p) => (
              <option key={p.id} value={p.id}>{p.name}</option>
            ))}
          </select>
        </div>

        <ErrorAlert message={error} onRetry={reload} />

        {!error && (
          <Table
            columns={columns}
            rows={items}
            loading={loading}
            emptyTitle="No buildings yet"
            emptyMessage="Add the first building to a property."
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

      <BuildingFormModal
        open={modalOpen}
        initial={editing}
        properties={properties}
        saving={saving}
        serverError={serverError}
        onClose={() => setModalOpen(false)}
        onSave={handleSave}
      />

      <Modal
        open={!!archiveTarget}
        onClose={() => setArchiveTarget(null)}
        title="Archive building"
        onConfirm={handleArchive}
        confirmLabel={archiving ? 'Archiving…' : 'Archive'}
        confirmVariant="danger"
        loading={archiving}
      >
        <p>
          Archive <strong className="text-gray-100">“{archiveTarget?.name}”</strong>?
          Its units will be archived too. This can be undone.
        </p>
        {archiveTarget?.error && <p className="mt-3 text-red-400">{archiveTarget.error}</p>}
      </Modal>
    </div>
  );
}
