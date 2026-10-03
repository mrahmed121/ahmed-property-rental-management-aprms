import api from '../../../services/api';

const unwrap = (res) => res.data.data;
const unwrapPage = (res) => ({ items: res.data.data, meta: res.data.meta });

export function expenseErrorMessage(err, fallback = 'Something went wrong.') {
  const data = err?.response?.data;
  if (data?.message) return data.message;
  if (data?.errors) {
    const first = Object.values(data.errors).flat()[0];
    if (first) return first;
  }
  return fallback;
}

export const expenseApi = {
  list: (params) => api.get('/expenses', { params }).then(unwrapPage),
  get: (id) => api.get(`/expenses/${id}`).then(unwrap),
  create: (payload) => api.post('/expenses', payload).then(unwrap),
  transition: (id, to) => api.post(`/expenses/${id}/transition`, { to }).then(unwrap),
  reverse: (id, reason) => api.post(`/expenses/${id}/reverse`, { reason }).then(unwrap),
  summary: (params) => api.get('/expenses/summary', { params }).then(unwrap),
};

export const EXPENSE_CATEGORIES = [
  '', 'maintenance', 'utilities', 'repairs', 'cleaning', 'security',
  'tax_fee', 'insurance', 'management', 'supplies', 'other',
];
export const EXPENSE_STATUSES = [
  '', 'draft', 'submitted', 'approved', 'rejected', 'posted', 'reversed',
];
