import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import Spinner from '../shared/Spinner';

/**
 * Guards a route: requires authentication, and optionally a permission.
 * - No session  -> /login (preserves intended destination)
 * - Missing permission -> /403
 */
export default function ProtectedRoute({ children, permission }) {
  const { isAuthenticated, loading, initialized, hasPermission } = useAuth();
  const location = useLocation();

  if (!initialized || loading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-charcoal-900">
        <Spinner size="lg" />
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location.pathname }} replace />;
  }

  if (permission && !hasPermission(permission)) {
    return <Navigate to="/403" replace />;
  }

  return children;
}
