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
// P4 — Billing domain
import InvoicesPage from './modules/billing/pages/InvoicesPage';
import InvoiceDetailPage from './modules/billing/pages/InvoiceDetailPage';
import RentCyclePage from './modules/billing/pages/RentCyclePage';
import PaymentsPage from './modules/billing/pages/PaymentsPage';
import PaymentFormPage from './modules/billing/pages/PaymentFormPage';
import PaymentDetailPage from './modules/billing/pages/PaymentDetailPage';
import LedgerPage from './modules/billing/pages/LedgerPage';
import ArrearsPage from './modules/billing/pages/ArrearsPage';
import DunningPage from './modules/billing/pages/DunningPage';
import FinancialDashboardPage from './modules/billing/pages/FinancialDashboardPage';
// P5 — Deposits domain
import DepositsPage from './modules/deposits/pages/DepositsPage';
import DepositFormPage from './modules/deposits/pages/DepositFormPage';
import DepositDetailPage from './modules/deposits/pages/DepositDetailPage';
// P5 — Maintenance domain
import TicketsPage from './modules/maintenance/pages/TicketsPage';
import TicketFormPage from './modules/maintenance/pages/TicketFormPage';
import TicketDetailPage from './modules/maintenance/pages/TicketDetailPage';
import VendorsPage from './modules/maintenance/pages/VendorsPage';
import MaintenanceDashboardPage from './modules/maintenance/pages/MaintenanceDashboardPage';
import MetersPage from './modules/utilities/pages/MetersPage';
import MeterDetailPage from './modules/utilities/pages/MeterDetailPage';
import UtilityBillsPage from './modules/utilities/pages/UtilityBillsPage';
import UtilityBillDetailPage from './modules/utilities/pages/UtilityBillDetailPage';
import ExpensesPage from './modules/expenses/pages/ExpensesPage';
import ExpenseFormPage from './modules/expenses/pages/ExpenseFormPage';
import ExpenseDetailPage from './modules/expenses/pages/ExpenseDetailPage';
import ExpenseApprovalsPage from './modules/expenses/pages/ExpenseApprovalsPage';
import StatementsPage from './modules/statements/pages/StatementsPage';
import StatementDetailPage from './modules/statements/pages/StatementDetailPage';
import StatementGeneratePage from './modules/statements/pages/StatementGeneratePage';
import StatementReviewPage from './modules/statements/pages/StatementReviewPage';
import OwnerReportsPage from './modules/statements/pages/OwnerReportsPage';
import MeterFormPage from './modules/utilities/pages/MeterFormPage';

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
            {/* P4 — Billing domain */}
            <Route path="billing" element={<FinancialDashboardPage />} />
            <Route path="billing/invoices" element={<InvoicesPage />} />
            <Route path="billing/invoices/:id" element={<InvoiceDetailPage />} />
            <Route path="billing/rent-cycle" element={<RentCyclePage />} />
            <Route path="billing/payments" element={<PaymentsPage />} />
            <Route path="billing/payments/new" element={<PaymentFormPage />} />
            <Route path="billing/payments/:id" element={<PaymentDetailPage />} />
            <Route path="billing/ledger/:tenantId" element={<LedgerPage />} />
            <Route path="billing/arrears" element={<ArrearsPage />} />
            <Route path="billing/dunning" element={<DunningPage />} />
            {/* P5 — Deposits domain */}
            <Route path="deposits" element={<DepositsPage />} />
            <Route path="deposits/new" element={<DepositFormPage />} />
            <Route path="deposits/:id" element={<DepositDetailPage />} />
            {/* P5 — Maintenance domain */}
            <Route path="maintenance" element={<MaintenanceDashboardPage />} />
            <Route path="maintenance/dashboard" element={<MaintenanceDashboardPage />} />
            <Route path="maintenance/tickets" element={<TicketsPage />} />
            <Route path="maintenance/tickets/new" element={<TicketFormPage />} />
            <Route path="maintenance/tickets/:id" element={<TicketDetailPage />} />
            <Route path="maintenance/vendors" element={<VendorsPage />} />
            {/* P6 — Utilities domain */}
            <Route path="utilities/meters" element={<MetersPage />} />
            <Route path="utilities/meters/new" element={<MeterFormPage />} />
            <Route path="utilities/meters/:id" element={<MeterDetailPage />} />
            <Route path="utilities/bills" element={<UtilityBillsPage />} />
            <Route path="utilities/bills/:id" element={<UtilityBillDetailPage />} />
            {/* P6 — Expenses domain */}
            <Route path="expenses" element={<ExpensesPage />} />
            <Route path="expenses/new" element={<ExpenseFormPage />} />
            <Route path="expenses/approvals" element={<ExpenseApprovalsPage />} />
            <Route path="expenses/:id" element={<ExpenseDetailPage />} />
            {/* P7 — Owner statements domain */}
            <Route path="statements" element={<StatementsPage />} />
            <Route path="statements/generate" element={<StatementGeneratePage />} />
            <Route path="statements/review" element={<StatementReviewPage />} />
            <Route path="statements/reports" element={<OwnerReportsPage />} />
            <Route path="statements/:id" element={<StatementDetailPage />} />
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
