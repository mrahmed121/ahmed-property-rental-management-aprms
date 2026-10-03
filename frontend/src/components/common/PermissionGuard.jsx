import { useAuth } from '../../context/AuthContext';

/**
 * Renders children only when the user holds the required permission.
 * Otherwise renders nothing (nav) or a forbidden notice (pages).
 */
export default function PermissionGuard({ permission, children, showForbidden = false }) {
  const { hasPermission, loading } = useAuth();
  if (loading) return null; // don't flash "restricted" before /me resolves
  if (hasPermission(permission)) return children;
  if (!showForbidden) return null;
  return (
    <div className="aprms-card text-center">
      <p className="text-lg font-semibold text-slate-100">Access restricted</p>
      <p className="mt-1 text-sm text-slate-400">
        Your role does not include the <span className="text-gold">{permission}</span> permission.
      </p>
    </div>
  );
}
