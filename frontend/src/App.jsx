import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import ProtectedRoute from './components/common/ProtectedRoute';
import AppShell from './components/common/AppShell';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import EmptyState from './components/common/EmptyState';
import PropertiesPage from './modules/property/pages/PropertiesPage';
import PropertyFormPage from './modules/property/pages/PropertyFormPage';
import PropertyDetailPage from './modules/property/pages/PropertyDetailPage';
// P3 — Leasing domain
import TenantsPage from './modules/leasing/pages/TenantsPage';
import TenantFormPage from './modules/leasing/pages/TenantFormPage';
import TenantDetailPage from './modules/leasing/pages/TenantDetailPage';
import ApplicationsPage from './modules/leasing/pages/ApplicationsPage';
import ApplicationDetailPage from './modules/leasing/pages/ApplicationDetailPage';
import LeasesPage from './modules/leasing/pages/LeasesPage';
import LeaseFormPage from './modules/leasing/pages/LeaseFormPage';
import LeaseDetailPage from './modules/leasing/pages/LeaseDetailPage';
import InspectionsPage from './modules/leasing/pages/InspectionsPage';

function Placeholder({ title, hint }) {
  return (
    <EmptyState icon="🚧" title={title} hint={hint} />
  );
}

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route
            element={
              <ProtectedRoute>
                <AppShell />
              </ProtectedRoute>
            }
          >
            <Route index element={<Dashboard />} />
            {/* P2 — Property domain */}
            <Route path="properties" element={<PropertiesPage />} />
            <Route path="properties/new" element={<PropertyFormPage />} />
            <Route path="properties/:id" element={<PropertyDetailPage />} />
            <Route path="properties/:id/edit" element={<PropertyFormPage />} />
            {/* P3 — Leasing domain */}
            <Route path="tenants" element={<TenantsPage />} />
            <Route path="tenants/new" element={<TenantFormPage />} />
            <Route path="tenants/:id" element={<TenantDetailPage />} />
            <Route path="tenants/:id/edit" element={<TenantFormPage />} />
            <Route path="applications" element={<ApplicationsPage />} />
            <Route path="applications/new" element={<ApplicationDetailPage />} />
            <Route path="applications/:id" element={<ApplicationDetailPage />} />
            <Route path="leases" element={<LeasesPage />} />
            <Route path="leases/new" element={<LeaseFormPage />} />
            <Route path="leases/:id" element={<LeaseDetailPage />} />
            <Route path="inspections" element={<InspectionsPage />} />
            {/* P1 admin placeholders — remaining modules land in P3+ */}
            <Route
              path="users"
              element={<Placeholder title="Users & Roles" hint="User administration UI arrives with the P2 build. API is live at /api/v1/users." />}
            />
            <Route
              path="audit-logs"
              element={<Placeholder title="Audit Logs" hint="The audit trail viewer ships in P2. Entries are already being recorded by the API." />}
            />
            <Route
              path="settings"
              element={<Placeholder title="Settings" hint="Agency settings UI ships in P2. The settings API is live at /api/v1/settings." />}
            />
          </Route>
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
