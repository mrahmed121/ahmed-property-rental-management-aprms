import { useEffect } from 'react';
import Button from './Button';

export default function Modal({
  open,
  onClose,
  title,
  children,
  onConfirm,
  confirmLabel = 'Confirm',
  confirmVariant = 'primary',
  loading = false,
}) {
  useEffect(() => {
    if (!open) return;
    const handler = (e) => {
      if (e.key === 'Escape') onClose?.();
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
      onClick={onClose}
      role="dialog"
      aria-modal="true"
    >
      <div
        className="glass w-full max-w-md rounded-xl p-6 shadow-2xl"
        onClick={(e) => e.stopPropagation()}
      >
        {title && (
          <h3 className="mb-4 text-lg font-semibold text-gray-100">{title}</h3>
        )}
        <div className="mb-6 text-sm text-gray-300">{children}</div>
        <div className="flex justify-end gap-3">
          <Button variant="secondary" onClick={onClose} disabled={loading}>
            Cancel
          </Button>
          {onConfirm && (
            <Button
              variant={confirmVariant}
              onClick={onConfirm}
              disabled={loading}
            >
              {loading ? 'Please wait…' : confirmLabel}
            </Button>
          )}
        </div>
      </div>
    </div>
  );
}
