import { useCallback, useEffect, useState } from 'react';

/**
 * Shared paginated-list state for property domain list pages.
 * api.list must resolve { items, meta: { current_page, per_page, total } }.
 */
export function usePaginatedList(listFn, initialFilters = {}) {
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState({ current_page: 1, per_page: 15, total: 0 });
  const [filters, setFilters] = useState(initialFilters);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const load = useCallback(
    async (override = {}) => {
      setLoading(true);
      setError(null);
      try {
        const merged = { ...filters, ...override };
        const { items: rows, meta: m } = await listFn(merged);
        setItems(rows);
        setMeta({
          current_page: m.current_page ?? 1,
          per_page: m.per_page ?? 15,
          total: m.total ?? rows.length,
        });
      } catch (err) {
        setError(
          err.response?.data?.message || 'Failed to load records. Please try again.'
        );
      } finally {
        setLoading(false);
      }
    },
    [listFn, filters]
  );

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [JSON.stringify(filters)]);

  const setFilter = (key, value) =>
    setFilters((f) => ({ ...f, [key]: value, page: 1 }));

  const setPage = (page) => {
    setFilters((f) => ({ ...f, page }));
    load({ page });
  };

  return { items, meta, filters, setFilter, setPage, loading, error, reload: () => load() };
}

export function errorMessage(err, fallback = 'Something went wrong. Please try again.') {
  const data = err.response?.data;
  if (data?.errors) {
    const first = Object.values(data.errors).flat()[0];
    if (first) return first;
  }
  return data?.message || fallback;
}
