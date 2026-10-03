import api from '../../../services/api';

const unwrap = (res) => res.data.data;
const unwrapPage = (res) => ({ items: res.data.data, meta: res.data.meta });

export function utilityErrorMessage(err, fallback = 'Something went wrong.') {
  const data = err?.response?.data;
  if (data?.message) return data.message;
  if (data?.errors) {
    const first = Object.values(data.errors).flat()[0];
    if (first) return first;
  }
  return fallback;
}

export const meterApi = {
  list: (params) => api.get('/utility/meters', { params }).then(unwrapPage),
  get: (id) => api.get(`/utility/meters/${id}`).then(unwrap),
  create: (payload) => api.post('/utility/meters', payload).then(unwrap),
  readings: (id, params) => api.get(`/utility/meters/${id}/readings`, { params }).then(unwrapPage),
  recordReading: (id, payload) => api.post(`/utility/meters/${id}/readings`, payload).then(unwrap),
  consumption: (id, payload) => api.post(`/utility/meters/${id}/consumption`, payload).then(unwrap),
};

export const utilityBillApi = {
  list: (params) => api.get('/utility/bills', { params }).then(unwrapPage),
  get: (id) => api.get(`/utility/bills/${id}`).then(unwrap),
  preview: (meterId, payload) => api.post(`/utility/meters/${meterId}/bills/preview`, payload).then(unwrap),
  generate: (meterId, payload) => api.post(`/utility/meters/${meterId}/bills`, payload).then(unwrap),
  allocate: (id, lines) => api.post(`/utility/bills/${id}/allocate`, { lines }).then(unwrap),
  finalize: (id) => api.post(`/utility/bills/${id}/finalize`).then(unwrap),
  reverse: (id, reason) => api.post(`/utility/bills/${id}/reverse`, { reason }).then(unwrap),
};

export const UTILITY_TYPES = ['', 'electricity', 'gas', 'water', 'other'];
export const METER_STATUSES = ['', 'active', 'inactive'];
export const BILL_STATUSES = ['', 'draft', 'finalized', 'reversed'];
export const ALLOCATION_METHODS = ['metered', 'equal_split', 'area_based', 'custom'];
