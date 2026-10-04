import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import Card from '../../../shared/Card';
import EmptyState from '../../../shared/EmptyState';
import ErrorAlert from '../../../shared/ErrorAlert';
import Spinner from '../../../shared/Spinner';
import Table from '../../../shared/Table';
import StatusBadge from '../components/StatusBadge';
import { buildingsApi, formatPKR } from '../services/propertyApi';

export default function BuildingDetailPage() {
  const { id } = useParams();
  const [building, setBuilding] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const load = async () => {
    setLoading(true);
    setError(null);
    try {
      setBuilding(await buildingsApi.get(id));
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load building.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  if (loading) return <div className="flex justify-center py-16"><Spinner /></div>;
  if (error) return <ErrorAlert message={error} onRetry={load} />;
  if (!building) return <EmptyState title="Building not found" message="It may have been archived or deleted." />;

  const units = building.units || [];
  const byStatus = units.reduce((acc, u) => {
    acc[u.status] = (acc[u.status] || 0) + 1;
    return acc;
  }, {});

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <Link to="/buildings" className="text-sm text-gold hover:underline">← Buildings</Link>
          <h1 className="mt-1 text-2xl font-bold text-gray-100">{building.name}</h1>
          <p className="mt-1 text-sm text-gray-500">
            {building.property ? (
              <>in <Link to={`/properties/${building.property.id}`} className="text-gold hover:underline">{building.property.name}</Link></>
            ) : '—'}
          </p>
        </div>
        <StatusBadge status={building.status} />
      </div>

      <div className="grid gap-5 lg:grid-cols-2">
        <Card title="Building information">
          <div className="space-y-2.5 text-sm">
            <div className="flex justify-between border-b border-charcoal-700 py-2">
              <span className="text-gray-500">Floors</span>
              <span className="font-medium text-gray-200">{building.floors ?? '—'}</span>
            </div>
            <div className="flex justify-between border-b border-charcoal-700 py-2">
              <span className="text-gray-500">Units</span>
              <span className="font-medium text-gray-200">{building.units_count ?? units.length}</span>
            </div>
            <div className="flex justify-between py-2">
              <span className="text-gray-500">Status</span>
              <StatusBadge status={building.status} />
            </div>
          </div>
          {building.description && <p className="mt-3 text-sm text-gray-400">{building.description}</p>}
        </Card>

        <Card title="Unit status summary">
          {units.length === 0 ? (
            <p className="text-sm text-gray-500">No units in this building yet.</p>
          ) : (
            <div className="flex flex-wrap gap-2">
              {Object.entries(byStatus).map(([s, n]) => (
                <span key={s} className="flex items-center gap-2 rounded-lg bg-charcoal-700/60 px-3 py-1.5 text-sm">
                  <StatusBadge status={s} />
                  <span className="font-semibold text-gray-200">{n}</span>
                </span>
              ))}
            </div>
          )}
        </Card>
      </div>

      <Card title="Units">
        <Table
          columns={[
            {
              key: 'unit_number',
              label: 'Unit',
              render: (r) => (
                <Link to={`/units/${r.id}`} className="font-medium text-gold hover:underline">
                  {r.unit_number}
                </Link>
              ),
            },
            { key: 'floor', label: 'Floor', render: (r) => r.floor ?? '—' },
            { key: 'unit_type', label: 'Type', render: (r) => r.unit_type?.replace('-', ' ') || '—' },
            { key: 'market_rent', label: 'Rent', align: 'right', render: (r) => formatPKR(r.market_rent) },
            { key: 'status', label: 'Status', render: (r) => <StatusBadge status={r.status} /> },
          ]}
          rows={units}
          emptyTitle="No units yet"
          emptyMessage="Add units to this building from the Units page."
        />
      </Card>
    </div>
  );
}
