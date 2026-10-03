import api from '../../../services/api';

const unwrap = (res) => res.data.data;
const unwrapPage = (res) => ({ items: res.data.data, meta: res.data.meta });

export const tenantApi = {
  list: (params) => api.get('/tenants', { params }).then(unwrapPage),
  get: (id) => api.get(`/tenants/${id}`).then(unwrap),
  create: (payload) => api.post('/tenants', payload).then(unwrap),
  update: (id, payload) => api.put(`/tenants/${id}`, payload).then(unwrap),
  archive: (id) => api.delete(`/tenants/${id}`).then((r) => r.data),
};

export const applicationApi = {
  list: (params) => api.get('/applications', { params }).then(unwrapPage),
  get: (id) => api.get(`/applications/${id}`).then(unwrap),
  create: (payload) => api.post('/applications', payload).then(unwrap),
  update: (id, payload) => api.put(`/applications/${id}`, payload).then(unwrap),
  transition: (id, status, extra = {}) =>
    api.post(`/applications/${id}/transition`, { status, ...extra }).then(unwrap),
  startScreening: (id) => api.post(`/applications/${id}/screening/start`).then(unwrap),
  decideScreening: (id, payload) =>
    api.post(`/applications/${id}/screening/decide`, payload).then(unwrap),
};

export const leaseApi = {
  list: (params) => api.get('/leases', { params }).then(unwrapPage),
  get: (id) => api.get(`/leases/${id}`).then(unwrap),
  create: (payload) => api.post('/leases', payload).then(unwrap),
  update: (id, payload) => api.put(`/leases/${id}`, payload).then(unwrap),
  activate: (id) => api.post(`/leases/${id}/activate`).then(unwrap),
  renew: (id, payload) => api.post(`/leases/${id}/renew`, payload).then(unwrap),
  terminate: (id, payload) => api.post(`/leases/${id}/terminate`, payload).then(unwrap),
};

export const inspectionApi = {
  list: (params) => api.get('/inspections', { params }).then(unwrapPage),
  get: (id) => api.get(`/inspections/${id}`).then(unwrap),
  create: (payload) => api.post('/inspections', payload).then(unwrap),
  update: (id, payload) => api.put(`/inspections/${id}`, payload).then(unwrap),
  review: (id) => api.post(`/inspections/${id}/review`).then(unwrap),
};

/** Extract a human-readable message from an API error. */
export function leasingErrorMessage(err, fallback = 'Something went wrong.') {
  const data = err?.response?.data;
  if (data?.message) return data.message;
  if (data?.errors) {
    const first = Object.values(data.errors).flat()[0];
    if (first) return first;
  }
  return fallback;
}
