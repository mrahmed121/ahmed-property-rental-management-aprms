import api from '../../../services/api';

const unwrap = (res) => res.data.data;
const unwrapPage = (res) => ({ items: res.data.data, meta: res.data.meta });

export function statementErrorMessage(err, fallback = 'Something went wrong.') {
  const data = err?.response?.data;
  if (data?.message) return data.message;
  if (data?.errors) {
    const first = Object.values(data.errors).flat()[0];
    if (first) return first;
  }
  return fallback;
}

export const statementApi = {
  list: (params) => api.get('/owner-statements', { params }).then(unwrapPage),
  get: (id) => api.get(`/owner-statements/${id}`).then(unwrap),
  preview: (payload) => api.post('/owner-statements/preview', payload).then(unwrap),
  generate: (payload) => api.post('/owner-statements', payload).then(unwrap),
  transition: (id, to) => api.post(`/owner-statements/${id}/transition`, { to }).then(unwrap),
  adjust: (id, amount, reason) => api.post(`/owner-statements/${id}/adjust`, { amount, reason }).then(unwrap),
  pdfUrl: (id) => `/api/v1/owner-statements/${id}/pdf`,
};

export const statementPeriodApi = {
  list: (params) => api.get('/statement-periods', { params }).then(unwrapPage),
  create: (payload) => api.post('/statement-periods', payload).then(unwrap),
  lock: (id) => api.post(`/statement-periods/${id}/lock`).then(unwrap),
};

export const ownerReportApi = {
  portfolio: (ownerId) => api.get('/owner-reports/portfolio', { params: { owner_id: ownerId } }).then(unwrap),
  profitability: (ownerId, periodId) => api.get('/owner-reports/profitability', { params: { owner_id: ownerId, period_id: periodId } }).then(unwrap),
  trend: (ownerId) => api.get('/owner-reports/trend', { params: { owner_id: ownerId } }).then(unwrap),
};

export const STATEMENT_STATUSES = ['', 'draft', 'review', 'approved', 'finalized'];
