import { useEffect, useRef, useState } from 'react';
import { useAuth } from '../../../context/AuthContext';
import Spinner from '../../../components/common/Spinner';
import EmptyState from '../../../components/common/EmptyState';
import ConfirmDialog from './ConfirmDialog';
import { documentApi, buildingApi, unitApi, apiErrorMessage } from '../services/propertyApi';

const DOC_TYPES = ['deed', 'noc', 'floor_plan', 'photo', 'agreement', 'other'];

/**
 * DocumentManager — property-side documents: upload, download, delete.
 * Parent can be the property itself, one of its buildings, or one of its units.
 */
export default function DocumentManager({ propertyId }) {
  const { hasPermission } = useAuth();
  const canManage = hasPermission('documents.manage');
  const fileRef = useRef(null);

  const [docs, setDocs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [targets, setTargets] = useState([{ key: 'property', id: propertyId, label: 'This property' }]);
  const [parentKey, setParentKey] = useState('property');
  const [docType, setDocType] = useState('other');
  const [description, setDescription] = useState('');
  const [uploading, setUploading] = useState(false);
  const [confirmId, setConfirmId] = useState(null);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState('');

  const loadAll = async () => {
    setLoading(true);
    setError('');
    try {
      const [buildings, units] = await Promise.all([
        buildingApi.list({ property_id: propertyId, per_page: 100 }).catch(() => ({ items: [] })),
        unitApi.list({ property_id: propertyId, per_page: 100 }).catch(() => ({ items: [] })),
      ]);
      const t = [{ key: 'property', id: propertyId, label: 'This property' }];
      buildings.items.forEach((x) => t.push({ key: 'building', id: x.id, label: `Building · ${x.name}` }));
      units.items.forEach((x) => t.push({ key: 'unit', id: x.id, label: `Unit · ${x.unit_number}` }));
      setTargets(t);

      const lists = await Promise.all(
        t.map((x) => documentApi.list({ parent_type: x.key, parent_id: x.id, per_page: 100 }).catch(() => ({ items: [] })))
      );
      setDocs(lists.flatMap((l, i) => l.items.map((d) => ({ ...d, _target: t[i].label }))));
    } catch (err) {
      setError(apiErrorMessage(err, 'Could not load documents.'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadAll();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [propertyId]);

  const upload = async (e) => {
    e.preventDefault();
    const file = fileRef.current?.files?.[0];
    if (!file) {
      setNotice('Choose a file to upload.');
      return;
    }
    const target = targets.find((t) => t.key === parentKey);
    if (!target) return;

    setUploading(true);
    setNotice('');
    try {
      const fd = new FormData();
      fd.append('parent_type', target.key);
      fd.append('parent_id', target.id);
      fd.append('file', file);
      fd.append('document_type', docType);
      if (description) fd.append('description', description);
      await documentApi.upload(fd);
      setNotice('Document uploaded.');
      setDescription('');
      if (fileRef.current) fileRef.current.value = '';
      loadAll();
    } catch (err) {
      setNotice(apiErrorMessage(err, 'Upload failed.'));
    } finally {
      setUploading(false);
    }
  };

  const doDelete = async () => {
    setBusy(true);
    try {
      await documentApi.remove(confirmId);
      setConfirmId(null);
      setNotice('Document deleted.');
      loadAll();
    } catch (err) {
      setNotice(apiErrorMessage(err, 'Could not delete the document.'));
    } finally {
      setBusy(false);
    }
  };

  // Authenticated download: fetch with the JWT in the header (never in the URL),
  // then save the blob locally.
  const download = async (d) => {
    setNotice('');
    try {
      const token = localStorage.getItem('aprms_token');
      const res = await fetch(documentApi.downloadUrl(d.id), {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (!res.ok) throw new Error('download failed');
      const blob = await res.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = d.name || 'document';
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.URL.revokeObjectURL(url);
    } catch {
      setNotice('Download failed. You may not have access to this file.');
    }
  };

  if (loading) {
    return <div className="flex justify-center py-10"><Spinner /></div>;
  }

  return (
    <div className="space-y-4">
      {notice && (
        <div className="rounded-lg border border-gold/30 bg-gold/10 px-4 py-2.5 text-sm text-gold-light">{notice}</div>
      )}
      {error && (
        <div className="rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-2.5 text-sm text-red-300">{error}</div>
      )}

      {canManage && (
        <form onSubmit={upload} className="aprms-card space-y-3">
          <h4 className="font-semibold text-slate-100">Upload document</h4>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label className="aprms-label">Attach to</label>
              <select className="aprms-input" value={parentKey} onChange={(e) => setParentKey(e.target.value)}>
                {targets.map((t) => <option key={`${t.key}-${t.id}`} value={t.key}>{t.label}</option>)}
              </select>
            </div>
            <div>
              <label className="aprms-label">Document type</label>
              <select className="aprms-input" value={docType} onChange={(e) => setDocType(e.target.value)}>
                {DOC_TYPES.map((t) => <option key={t} value={t}>{t.replace('_', ' ')}</option>)}
              </select>
            </div>
          </div>
          <div>
            <label className="aprms-label">File (PDF, JPG, PNG, DOC, DOCX, TXT · max 10 MB)</label>
            <input ref={fileRef} type="file" className="aprms-input" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.txt" />
          </div>
          <div>
            <label className="aprms-label">Description</label>
            <input className="aprms-input" value={description} onChange={(e) => setDescription(e.target.value)} placeholder="What is this document?" />
          </div>
          <div className="flex justify-end">
            <button type="submit" className="aprms-btn-gold" disabled={uploading}>
              {uploading ? 'Uploading…' : 'Upload'}
            </button>
          </div>
        </form>
      )}

      {docs.length === 0 ? (
        <EmptyState
          icon="📄"
          title="No documents yet"
          hint="Upload deeds, NOCs, floor plans or photos for this property, its buildings or its units."
        />
      ) : (
        <div className="overflow-hidden rounded-xl border border-charcoal-700/60 bg-charcoal-900/80">
          <div className="overflow-x-auto">
            <table className="w-full min-w-[560px] text-left text-sm">
              <thead>
                <tr className="border-b border-charcoal-700/60 bg-charcoal-950/60">
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Document</th>
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Attached to</th>
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Type</th>
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Size</th>
                  <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-400">Actions</th>
                </tr>
              </thead>
              <tbody>
                {docs.map((d) => (
                  <tr key={d.id} className="border-b border-charcoal-800/60 last:border-0 hover:bg-charcoal-800/40">
                    <td className="px-4 py-3">
                      <p className="font-medium text-slate-100">{d.name}</p>
                      {d.description && <p className="text-xs text-slate-500">{d.description}</p>}
                    </td>
                    <td className="px-4 py-3 text-slate-400">{d._target || d.parent_type}</td>
                    <td className="px-4 py-3 capitalize text-slate-400">{(d.document_type || '').replace('_', ' ')}</td>
                    <td className="px-4 py-3 text-slate-400">
                      {d.file_size != null ? `${(d.file_size / 1024).toFixed(1)} KB` : '—'}
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex justify-end gap-2">
                        <button className="aprms-btn-ghost !px-3 !py-1.5 !text-xs" onClick={() => download(d)}>
                          Download
                        </button>
                        {canManage && (
                          <button
                            className="inline-flex items-center rounded-lg border border-red-900/60 bg-red-950/40 px-3 py-1.5 text-xs font-medium text-red-300 hover:border-red-500/60"
                            onClick={() => setConfirmId(d.id)}
                          >
                            Delete
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      <ConfirmDialog
        open={!!confirmId}
        title="Delete document?"
        message="The document record and its file will be permanently deleted."
        confirmLabel="Delete"
        busy={busy}
        onConfirm={doDelete}
        onCancel={() => setConfirmId(null)}
      />
    </div>
  );
}
