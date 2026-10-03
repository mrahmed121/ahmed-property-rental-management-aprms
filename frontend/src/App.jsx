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
