import api from '../../../services/api';

const unwrap = (res) => res.data.data;
const unwrapPage = (res) => ({ items: res.data.data, meta: res.data.meta });

/** Remove empty filter values (null, undefined, '') from params. */
const cleanParams = (params = {}) => {
  const cleaned = {};
  for (const [k, v] of Object.entries(params)) {
    if (v !== null && v !== undefined && v !== '') cleaned[k] = v;
  }
  return cleaned;
};

export const propertyApi = {
  list: (params) => api.get('/properties', { params: cleanParams(params) }).then(unwrapPage),
  get: (id) => api.get(`/properties/${id}`).then(unwrap),
  create: (payload) => api.post('/properties', payload).then(unwrap),
  update: (id, payload) => api.put(`/properties/${id}`, payload).then(unwrap),
  archive: (id) => api.delete(`/properties/${id}`).then((r) => r.data),
  restore: (id) => api.post(`/properties/${id}/restore`).then(unwrap),
};

export const buildingApi = {
  list: (params) => api.get('/buildings', { params: cleanParams(params) }).then(unwrapPage),
  get: (id) => api.get(`/buildings/${id}`).then(unwrap),
  create: (payload) => api.post('/buildings', payload).then(unwrap),
  update: (id, payload) => api.put(`/buildings/${id}`, payload).then(unwrap),
  archive: (id) => api.delete(`/buildings/${id}`).then((r) => r.data),
  restore: (id) => api.post(`/buildings/${id}/restore`).then(unwrap),
};

export const unitApi = {
  list: (params) => api.get('/units', { params: cleanParams(params) }).then(unwrapPage),
  get: (id) => api.get(`/units/${id}`).then(unwrap),
  create: (payload) => api.post('/units', payload).then(unwrap),
  update: (id, payload) => api.put(`/units/${id}`, payload).then(unwrap),
  archive: (id) => api.delete(`/units/${id}`).then((r) => r.data),
  restore: (id) => api.post(`/units/${id}/restore`).then(unwrap),
};

export const documentApi = {
  list: (params) => api.get('/documents', { params }).then(unwrapPage),
  upload: (formData) =>
    api.post('/documents', formData, { headers: { 'Content-Type': 'multipart/form-data' } }).then(unwrap),
  remove: (id) => api.delete(`/documents/${id}`).then((r) => r.data),
  downloadUrl: (id) => `/api/v1/documents/${id}/download`,
};

export const dashboardApi = {
  stats: () => api.get('/dashboard/stats').then(unwrap),
};

/** Extract a human-readable message from an API error. */
export function apiErrorMessage(err, fallback = 'Something went wrong.') {
  const data = err?.response?.data;
  if (data?.message) return data.message;
  if (data?.errors) {
    const first = Object.values(data.errors).flat()[0];
    if (first) return first;
  }
  return fallback;
}

// Plural aliases for the P2 page components (same underlying clients).
export const propertiesApi = propertyApi;
export const buildingsApi = buildingApi;
export const unitsApi = unitApi;

// Status/type constants (mirror backend Unit::STATUSES / Unit::TYPES).
export const UNIT_STATUSES = ['vacant', 'occupied', 'reserved', 'maintenance', 'inactive'];
export const UNIT_TYPES = ['apartment', 'office', 'shop', 'room', 'studio', 'warehouse', 'other'];
export const PROPERTY_STATUSES = ['active', 'inactive'];
export const PROPERTY_TYPES = ['residential', 'commercial', 'mixed-use'];

/** Format a number as PKR currency. */
export function formatPKR(amount) {
  if (amount == null || amount === '' || Number.isNaN(Number(amount))) return '—';
  return 'PKR ' + Number(amount).toLocaleString('en-PK');
}
