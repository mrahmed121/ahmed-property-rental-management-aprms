import { useCallback, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import DataTable from '../components/DataTable';
import StatusBadge from '../components/StatusBadge';
import ConfirmDialog from '../components/ConfirmDialog';
import { propertyApi, apiErrorMessage } from '../services/propertyApi';

const TYPES = ['', 'residential', 'commercial', 'mixed-use'];
const STATUSES = ['', 'active', 'inactive'];

export default function PropertiesPage() {
  const { hasPermission } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [confirmId, setConfirmId] = useState(null);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState('');

  const filters = {
    search: searchParams.get('search') || '',
    property_type: searchParams.get('property_type') || '',
    status: searchParams.get('status') || '',
    sort_by: searchParams.get('sort_by') || 'name',
    sort_dir: searchParams.get('sort_dir') || 'asc',
    page: parseInt(searchParams.get('page') || '1', 10),
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const { items, meta } = await propertyApi.list({
        ...filters,
        per_page: 12,
        search: filters.search || undefined,
        property_type: filters.property_type || undefined,
        status: filters.status || undefined,
      });
      setRows(items);
      setMeta(meta);
    } catch (err) {
      setError(apiErrorMessage(err, 'Could not load properties.'));
    } finally {
      setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams.toString()]);

  useEffect(() => {
    load();
  }, [load]);

  const setFilter = (patch) => {
    const next = new URLSearchParams(searchParams);
    Object.entries(patch).forEach(([k, v]) => {
      if (v) next.set(k, v);
      else next.delete(k);
    });
    if (!patch.page) next.delete('page');
    setSearchParams(next);
  };

  const doArchive = async () => {
    setBusy(true);
    try {
      await propertyApi.archive(confirmId);
      setConfirmId(null);
      setNotice('Property archived. Its buildings and units were archived with it.');
      load();
    } catch (err) {
      setNotice(apiErrorMessage(err, 'Could not archive the property.'));
    } finally {
      setBusy(false);
    }
  };

  const canManage = hasPermission('properties.manage');

  return (
    <PermissionGuard permission="properties.view" showForbidden>
      <div className="space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-xl font-bold text-slate-100">Properties</h2>
            <p className="mt-1 text-sm text-slate-400">
              Your agency's property portfolio.
              {meta?.total != null && (
                <span className="ml-2 text-slate-500">
                  {meta.total} {meta.total === 1 ? 'property' : 'properties'} in portfolio
                </span>
              )}
            </p>
          </div>
          {canManage && (
            <Link to="/properties/new" className="aprms-btn-gold">
              + New Property
            </Link>
          )}
        </div>

        {notice && (
          <div className="rounded-lg border border-gold/30 bg-gold/10 px-4 py-3 text-sm text-gold-light">
            {notice}
          </div>
        )}

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <input
            className="aprms-input"
            placeholder="Search name, city, address…"
            value={filters.search}
            onChange={(e) => setFilter({ search: e.target.value })}
          />
          <select
            className="aprms-input"
            value={filters.property_type}
            onChange={(e) => setFilter({ property_type: e.target.value })}
          >
            {TYPES.map((t) => (
              <option key={t} value={t}>{t ? t.replace('-', ' ') : 'All types'}</option>
            ))}
          </select>
          <select
            className="aprms-input"
            value={filters.status}
            onChange={(e) => setFilter({ status: e.target.value })}
          >
            {STATUSES.map((s) => (
              <option key={s} value={s}>{s || 'All statuses'}</option>
            ))}
          </select>
          <select
            className="aprms-input"
            value={`${filters.sort_by}:${filters.sort_dir}`}
            onChange={(e) => {
              const [sort_by, sort_dir] = e.target.value.split(':');
              setFilter({ sort_by, sort_dir });
            }}
          >
            <option value="name:asc">Name A–Z</option>
            <option value="name:desc">Name Z–A</option>
            <option value="city:asc">City A–Z</option>
            <option value="created_at:desc">Newest first</option>
          </select>
        </div>

        <DataTable
          columns={[
            {
              key: 'name',
              label: 'Property',
              render: (r) => (
                <Link to={`/properties/${r.id}`} className="font-medium text-gold hover:text-gold-light">
                  {r.name}
                </Link>
              ),
            },
            { key: 'property_type', label: 'Type', render: (r) => <StatusBadge value={r.property_type} /> },
            { key: 'city', label: 'City' },
            {
              key: 'owner',
              label: 'Owner',
              render: (r) => (
                <span className="text-slate-300">{r.owner?.name || <span className="text-slate-500">—</span>}</span>
              ),
            },
            {
              key: 'portfolio',
              label: 'Portfolio',
              render: (r) => (
                <span className="text-slate-400">
                  {r.buildings_count ?? 0} buildings · {r.units_count ?? 0} units
                </span>
              ),
            },
            { key: 'status', label: 'Status', render: (r) => <StatusBadge value={r.status} /> },
            {
              key: 'actions',
              label: '',
              className: 'text-right',
              render: (r) => (
                <div className="flex justify-end gap-2">
                  <Link to={`/properties/${r.id}`} className="aprms-btn-ghost !px-3 !py-1.5 !text-xs">
                    Open
                  </Link>
                  {canManage && (
                    <>
                      <Link to={`/properties/${r.id}/edit`} className="aprms-btn-ghost !px-3 !py-1.5 !text-xs">
                        Edit
                      </Link>
                      <button
                        className="inline-flex items-center rounded-lg border border-red-900/60 bg-red-950/40 px-3 py-1.5 text-xs font-medium text-red-300 transition hover:border-red-500/60"
                        onClick={() => setConfirmId(r.id)}
                      >
                        Archive
                      </button>
                    </>
                  )}
                </div>
              ),
            },
          ]}
          rows={rows}
          meta={meta}
          loading={loading}
          error={error}
          emptyTitle="No properties yet"
          emptyHint="Add your first property to start building the portfolio. Buildings and units live inside each property."
          emptyAction={
            canManage ? (
              <Link to="/properties/new" className="aprms-btn-gold">+ New Property</Link>
            ) : undefined
          }
          onPage={(page) => setFilter({ page })}
          onRetry={load}
        />

        <ConfirmDialog
          open={!!confirmId}
          title="Archive property?"
          message="The property, its buildings and its units will be archived (soft-deleted). Documents are preserved. You can restore it later."
          confirmLabel="Archive"
          busy={busy}
          onConfirm={doArchive}
          onCancel={() => setConfirmId(null)}
        />
      </div>
    </PermissionGuard>
  );
}
