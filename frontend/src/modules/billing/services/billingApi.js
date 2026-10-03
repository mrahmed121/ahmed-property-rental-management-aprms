import api from '../../../services/api';

const unwrap = (res) => res.data.data;
const unwrapPage = (res) => ({ items: res.data.data, meta: res.data.meta });

/** Format PKR amounts: ₨1,234.56 */
export function formatPKR(amount) {
  const n = Number(amount || 0);
  return '₨' + n.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export const invoiceApi = {
  list: (params) => api.get('/invoices', { params }).then(unwrapPage),
  get: (id) => api.get(`/invoices/${id}`).then(unwrap),
  create: (payload) => api.post('/invoices', payload).then(unwrap),
  void: (id) => api.post(`/invoices/${id}/void`).then(unwrap),
  generateCycle: (period, dryRun) =>
    api.post('/rent-cycle/generate', { period, dry_run: dryRun }).then(unwrap),
  accrueLateFees: () => api.post('/late-fees/accrue').then((r) => r.data),
  waiveLateFee: (id) => api.post(`/late-fees/${id}/waive`).then((r) => r.data),
};

export const paymentApi = {
  list: (params) => api.get('/payments', { params }).then(unwrapPage),
  get: (id) => api.get(`/payments/${id}`).then(unwrap),
  preview: (tenantId, amount) =>
    api.post('/payments/preview', { tenant_id: tenantId, amount }).then(unwrap),
  record: (payload) => api.post('/payments', payload).then(unwrap),
  reverse: (id, reason) => api.post(`/payments/${id}/reverse`, { reason }).then((r) => r.data),
};

export const ledgerApi = {
  statement: (tenantId, params) => api.get(`/tenants/${tenantId}/ledger`, { params }).then(unwrap),
  balance: (tenantId) => api.get(`/tenants/${tenantId}/ledger/balance`).then(unwrap),
};

export const dunningApi = {
  list: (params) => api.get('/dunning', { params }).then(unwrapPage),
  process: () => api.post('/dunning/process').then((r) => r.data),
  markSent: (id) => api.post(`/dunning/${id}/sent`).then((r) => r.data),
};

export const receiptApi = {
  get: (paymentId) => api.get(`/payments/${paymentId}/receipt`).then(unwrap),
  pdfUrl: (paymentId) => `/api/v1/payments/${paymentId}/receipt/pdf`,
};

export const financialApi = {
  dashboard: () => api.get('/financial/dashboard').then(unwrap),
  arrears: (params) => api.get('/financial/arrears', { params }).then(unwrapPage),
  periods: () => api.get('/financial/periods').then(unwrapPage),
  lockPeriod: (period) => api.post('/financial/periods/lock', { period }).then((r) => r.data),
  unlockPeriod: (period) => api.post('/financial/periods/unlock', { period }).then((r) => r.data),
};

export function billingErrorMessage(err, fallback = 'Something went wrong.') {
  const data = err?.response?.data;
  if (data?.message) return data.message;
  if (data?.errors) {
    const first = Object.values(data.errors).flat()[0];
    if (first) return first;
  }
  return fallback;
}
