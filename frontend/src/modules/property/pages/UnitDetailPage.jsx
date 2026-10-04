import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import Card from '../../../shared/Card';
import EmptyState from '../../../shared/EmptyState';
import ErrorAlert from '../../../shared/ErrorAlert';
import Spinner from '../../../shared/Spinner';
import StatusBadge from '../components/StatusBadge';
import { unitsApi, formatPKR } from '../services/propertyApi';

function DetailRow({ label, value }) {
  return (
    <div className="flex justify-between gap-4 border-b border-charcoal-700 py-2.5 text-sm last:border-0">
      <span className="text-gray-500">{label}</span>
      <span className="text-right font-medium text-gray-200">{value ?? '—'}</span>
    </div>
  );
}

export default function UnitDetailPage() {
  const { id } = useParams();
  const [unit, setUnit] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const load = async () => {
    setLoading(true);
    setError(null);
    try {
      setUnit(await unitsApi.get(id));
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load unit.');
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
  if (!unit) return <EmptyState title="Unit not found" message="It may have been archived or deleted." />;

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <Link to="/units" className="text-sm text-gold hover:underline">← Units</Link>
          <h1 className="mt-1 text-2xl font-bold text-gray-100">Unit {unit.unit_number}</h1>
          <p className="mt-1 text-sm text-gray-500">
            {unit.building && (
              <>in <Link to={`/buildings/${unit.building.id}`} className="text-gold hover:underline">{unit.building.name}</Link></>
            )}
            {unit.property && (
              <> · <Link to={`/properties/${unit.property.id}`} className="text-gold hover:underline">{unit.property.name}</Link></>
            )}
          </p>
        </div>
        <StatusBadge status={unit.status} />
      </div>

      <div className="grid gap-5 lg:grid-cols-2">
        <Card title="Unit details">
          <DetailRow label="Unit number" value={unit.unit_number} />
          <DetailRow label="Type" value={unit.unit_type?.replace('-', ' ')} />
          <DetailRow label="Floor" value={unit.floor} />
          <DetailRow label="Bedrooms" value={unit.bedrooms} />
          <DetailRow label="Bathrooms" value={unit.bathrooms} />
          <DetailRow label="Area" value={unit.area_sqft ? `${unit.area_sqft} sq ft` : null} />
          <DetailRow label="Monthly rent" value={formatPKR(unit.market_rent)} />
          {unit.notes && <p className="mt-3 text-sm text-gray-400">{unit.notes}</p>}
        </Card>

        <div className="space-y-5">
          <Card title="Current tenant">
            <EmptyState
              title="No tenant assigned"
              message="Tenant assignment arrives with leasing in a later phase."
            />
          </Card>
          <Card title="Active lease">
            <EmptyState
              title="No active lease"
              message="Lease agreements arrive with leasing in a later phase."
            />
          </Card>
        </div>
      </div>

      {unit.documents && unit.documents.length > 0 && (
        <Card title="Documents">
          <ul className="space-y-2 text-sm">
            {unit.documents.map((d) => (
              <li key={d.id} className="text-gray-300">{d.filename || d.name}</li>
            ))}
          </ul>
        </Card>
      )}
    </div>
  );
}
