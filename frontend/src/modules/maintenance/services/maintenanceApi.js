import api from '../../../services/api';

const unwrap = (res) => res.data.data;
const unwrapPage = (res) => ({ items: res.data.data, meta: res.data.meta });

export function maintenanceErrorMessage(err, fallback = 'Something went wrong.') {
  const data = err?.response?.data;
  if (data?.message) return data.message;
  if (data?.errors) {
    const first = Object.values(data.errors).flat()[0];
    if (first) return first;
  }
  return fallback;
}

export const ticketApi = {
  list: (params) => api.get('/maintenance/tickets', { params }).then(unwrapPage),
  get: (id) => api.get(`/maintenance/tickets/${id}`).then(unwrap),
  create: (payload) => api.post('/maintenance/tickets', payload).then(unwrap),
  transition: (id, to, notes) =>
    api.post(`/maintenance/tickets/${id}/transition`, { to, notes }).then(unwrap),
  assign: (id, technicianId) =>
    api.post(`/maintenance/tickets/${id}/assign`, { technician_id: technicianId }).then(unwrap),
  createQuote: (id, payload) => api.post(`/maintenance/tickets/${id}/quotes`, payload).then(unwrap),
  decideQuote: (quoteId, decision) =>
    api.post(`/maintenance/quotes/${quoteId}/decide`, { decision }).then(unwrap),
  logWork: (id, payload) => api.post(`/maintenance/tickets/${id}/work-logs`, payload).then(unwrap),
  verify: (id, payload) => api.post(`/maintenance/tickets/${id}/verify`, payload).then(unwrap),
};

export const vendorApi = {
  list: (params) => api.get('/maintenance/vendors', { params }).then(unwrapPage),
  create: (payload) => api.post('/maintenance/vendors', payload).then(unwrap),
};

export const maintenanceDashboardApi = {
  metrics: () => api.get('/maintenance/dashboard').then(unwrap),
};

export const SLA_LABELS = {
  low: '7 days',
  normal: '3 days',
  high: '24 hours',
  urgent: '4 hours',
};
