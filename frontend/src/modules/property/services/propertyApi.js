import api from '../../../services/api';

const unwrap = (res) => res.data.data;
const unwrapPage = (res) => ({ items: res.data.data, meta: res.data.meta });

export const propertyApi = {
  list: (params) => api.get('/properties', { params }).then(unwrapPage),
  get: (id) => api.get(`/properties/${id}`).then(unwrap),
  create: (payload) => api.post('/properties', payload).then(unwrap),
  update: (id, payload) => api.put(`/properties/${id}`, payload).then(unwrap),
  archive: (id) => api.delete(`/properties/${id}`).then((r) => r.data),
  restore: (id) => api.post(`/properties/${id}/restore`).then(unwrap),
};

export const buildingApi = {
  list: (params) => api.get('/buildings', { params }).then(unwrapPage),
  get: (id) => api.get(`/buildings/${id}`).then(unwrap),
  create: (payload) => api.post('/buildings', payload).then(unwrap),
  update: (id, payload) => api.put(`/buildings/${id}`, payload).then(unwrap),
  archive: (id) => api.delete(`/buildings/${id}`).then((r) => r.data),
  restore: (id) => api.post(`/buildings/${id}/restore`).then(unwrap),
};

export const unitApi = {
  list: (params) => api.get('/units', { params }).then(unwrapPage),
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
