import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import Spinner from '../../../components/common/Spinner';
import StatusBadge from '../components/StatusBadge';
import ConfirmDialog from '../components/ConfirmDialog';
import BuildingManager from '../components/BuildingManager';
import UnitManager from '../components/UnitManager';
import DocumentManager from '../components/DocumentManager';
import { propertyApi, apiErrorMessage } from '../services/propertyApi';

const TABS = [
  { key: 'buildings', label: 'Buildings' },
  { key: 'units', label: 'Units' },
  { key: 'documents', label: 'Documents' },
];

export default function PropertyDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { hasPermission } = useAuth();
  const canManage = hasPermission('properties.manage');

  const [property, setProperty] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [tab, setTab] = useState('buildings');
  const [confirmArchive, setConfirmArchive] = useState(false);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState('');

  useEffect(() => {
    propertyApi
      .get(id)
      .then(setProperty)
      .catch((err) => setError(apiErrorMessage(err, 'Could not load the property.')))
      .finally(() => setLoading(false));
  }, [id]);

  const doArchive = async () => {
    setBusy(true);
    try {
      await propertyApi.archive(id);
      navigate('/properties');
    } catch (err) {
      setNotice(apiErrorMessage(err, 'Could not archive the property.'));
      setConfirmArchive(false);
    } finally {
      setBusy(false);
    }
  };

  return (
    <PermissionGuard permission="properties.view" showForbidden>
      {loading ? (
        <div className="aprms-card flex justify-center py-16"><Spinner /></div>
      ) : error || !property ? (
        <div className="aprms-card py-12 text-center">
          <p className="text-base font-semibold text-red-300">Could not load property</p>
          <p className="mt-1 text-sm text-slate-400">{error}</p>
          <Link to="/properties" className="aprms-btn-ghost mt-4">← Back to properties</Link>
        </div>
      ) : (
        <div className="space-y-5">
          <Link to="/properties" className="text-sm text-slate-400 hover:text-gold">← All properties</Link>

          <div className="aprms-card">
            <div className="flex flex-wrap items-start justify-between gap-4">
              <div>
                <div className="flex flex-wrap items-center gap-3">
                  <h2 className="text-xl font-bold text-slate-100">{property.name}</h2>
                  <StatusBadge value={property.property_type} />
                  <StatusBadge value={property.status} />
                </div>
                <p className="mt-2 text-sm text-slate-400">
                  {property.address} · {property.city}
                  {property.postal_code ? ` ${property.postal_code}` : ''}
                </p>
                {property.owner && (
                  <p className="mt-1 text-sm text-slate-500">Owner: <span className="text-slate-300">{property.owner.name}</span></p>
                )}
                {property.description && (
                  <p className="mt-3 text-sm text-slate-300">{property.description}</p>
                )}
                {property.notes && (
                  <p className="mt-2 text-xs text-slate-500">Notes: {property.notes}</p>
                )}
              </div>
              {canManage && (
                <div className="flex gap-2">
                  <Link to={`/properties/${property.id}/edit`} className="aprms-btn-ghost !py-2">Edit</Link>
                  <button
                    className="inline-flex items-center rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-2 text-sm font-medium text-red-300 hover:border-red-500/60"
                    onClick={() => setConfirmArchive(true)}
                  >
                    Archive
                  </button>
                </div>
              )}
            </div>
          </div>

          {notice && (
            <div className="rounded-lg border border-gold/30 bg-gold/10 px-4 py-3 text-sm text-gold-light">{notice}</div>
          )}

          <div className="flex gap-1 border-b border-charcoal-700/60">
            {TABS.map((t) => (
              <button
                key={t.key}
                onClick={() => setTab(t.key)}
                className={`px-4 py-2.5 text-sm font-medium transition ${
                  tab === t.key
                    ? 'border-b-2 border-gold text-gold'
                    : 'text-slate-400 hover:text-slate-200'
                }`}
              >
                {t.label}
              </button>
            ))}
          </div>

          <div>
            {tab === 'buildings' && <BuildingManager propertyId={property.id} />}
            {tab === 'units' && <UnitManager propertyId={property.id} />}
            {tab === 'documents' && <DocumentManager propertyId={property.id} />}
          </div>

          <ConfirmDialog
            open={confirmArchive}
            title="Archive property?"
            message="The property, its buildings and its units will be archived (soft-deleted). Documents are preserved. You can restore it later."
            confirmLabel="Archive"
            busy={busy}
            onConfirm={doArchive}
            onCancel={() => setConfirmArchive(false)}
          />
        </div>
      )}
    </PermissionGuard>
  );
}
