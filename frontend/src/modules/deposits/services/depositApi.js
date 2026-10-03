import api from '../../../services/api';

const unwrap = (res) => res.data.data;
const unwrapPage = (res) => ({ items: res.data.data, meta: res.data.meta });

export function formatPKR(amount) {
  const n = Number(amount || 0);
  return '₨' + n.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export function depositErrorMessage(err, fallback = 'Something went wrong.') {
  const data = err?.response?.data;
  if (data?.message) return data.message;
  if (data?.errors) {
    const first = Object.values(data.errors).flat()[0];
    if (first) return first;
  }
  return fallback;
}

export const depositApi = {
  list: (params) => api.get('/deposits', { params }).then(unwrapPage),
  get: (id) => api.get(`/deposits/${id}`).then(unwrap),
  create: (payload) => api.post('/deposits', payload).then(unwrap),
  receive: (id, payload) => api.post(`/deposits/${id}/receive`, payload).then(unwrap),
  adjust: (id, payload) => api.post(`/deposits/${id}/adjust`, payload).then(unwrap),
  proposeDeduction: (id, payload) => api.post(`/deposits/${id}/deductions`, payload).then(unwrap),
  reviewDeduction: (deductionId, decision) =>
    api.post(`/deposits/deductions/${deductionId}/review`, { decision }).then(unwrap),
  draftSettlement: (id, inspectionId) =>
    api.post(`/deposits/${id}/settlement/draft`, { inspection_id: inspectionId }).then(unwrap),
  previewSettlement: (id, applyToBalance) =>
    api.post(`/deposits/${id}/settlement/preview`, { apply_to_balance: applyToBalance }).then(unwrap),
  finalizeSettlement: (id, payload) =>
    api.post(`/deposits/${id}/settlement/finalize`, payload).then(unwrap),
  reverseSettlement: (id, reason) =>
    api.post(`/deposits/${id}/settlement/reverse`, { reason }).then(unwrap),
};
