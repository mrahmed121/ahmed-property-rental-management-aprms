import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './auth/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import Layout from './layout/Layout';
import Dashboard from './pages/Dashboard';
import Forbidden from './pages/Forbidden';
import Login from './pages/Login';
import NotFound from './pages/NotFound';
import Users from './pages/Users';
import PropertiesPage from './modules/property/pages/PropertiesPage';
import PropertyDetailPage from './modules/property/pages/PropertyDetailPage';
import PropertyFormPage from './modules/property/pages/PropertyFormPage';
import BuildingsPage from './modules/property/pages/BuildingsPage';
import BuildingDetailPage from './modules/property/pages/BuildingDetailPage';
import UnitsPage from './modules/property/pages/UnitsPage';
import UnitDetailPage from './modules/property/pages/UnitDetailPage';
import TenantsPage from './modules/leasing/pages/TenantsPage';
import TenantDetailPage from './modules/leasing/pages/TenantDetailPage';
import TenantFormPage from './modules/leasing/pages/TenantFormPage';
import LeasesPage from './modules/leasing/pages/LeasesPage';
import LeaseDetailPage from './modules/leasing/pages/LeaseDetailPage';
import LeaseFormPage from './modules/leasing/pages/LeaseFormPage';
import FinancialDashboardPage from './modules/billing/pages/FinancialDashboardPage';
import InvoicesPage from './modules/billing/pages/InvoicesPage';
import InvoiceDetailPage from './modules/billing/pages/InvoiceDetailPage';
import PaymentsPage from './modules/billing/pages/PaymentsPage';
import PaymentFormPage from './modules/billing/pages/PaymentFormPage';
import PaymentDetailPage from './modules/billing/pages/PaymentDetailPage';
import LedgerPage from './modules/billing/pages/LedgerPage';
import ArrearsPage from './modules/billing/pages/ArrearsPage';
import DunningPage from './modules/billing/pages/DunningPage';
import RentCyclePage from './modules/billing/pages/RentCyclePage';
import DepositsPage from './modules/deposits/pages/DepositsPage';
import DepositDetailPage from './modules/deposits/pages/DepositDetailPage';
import DepositFormPage from './modules/deposits/pages/DepositFormPage';
import MaintenanceDashboardPage from './modules/maintenance/pages/MaintenanceDashboardPage';
import TicketsPage from './modules/maintenance/pages/TicketsPage';
import TicketDetailPage from './modules/maintenance/pages/TicketDetailPage';
import TicketFormPage from './modules/maintenance/pages/TicketFormPage';
import VendorsPage from './modules/maintenance/pages/VendorsPage';
import MetersPage from './modules/utilities/pages/MetersPage';
import MeterDetailPage from './modules/utilities/pages/MeterDetailPage';
import MeterFormPage from './modules/utilities/pages/MeterFormPage';
import UtilityBillsPage from './modules/utilities/pages/UtilityBillsPage';
import UtilityBillDetailPage from './modules/utilities/pages/UtilityBillDetailPage';
import ExpensesPage from './modules/expenses/pages/ExpensesPage';
import ExpenseDetailPage from './modules/expenses/pages/ExpenseDetailPage';
import ExpenseFormPage from './modules/expenses/pages/ExpenseFormPage';
import ExpenseApprovalsPage from './modules/expenses/pages/ExpenseApprovalsPage';
import StatementsPage from './modules/statements/pages/StatementsPage';
import StatementDetailPage from './modules/statements/pages/StatementDetailPage';
import StatementGeneratePage from './modules/statements/pages/StatementGeneratePage';
import StatementReviewPage from './modules/statements/pages/StatementReviewPage';
import OwnerReportsPage from './modules/statements/pages/OwnerReportsPage';

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route path="/403" element={<Forbidden />} />

          <Route
            element={
              <ProtectedRoute>
                <Layout />
              </ProtectedRoute>
            }
          >
            <Route index element={<Dashboard />} />
            <Route
              path="/users"
              element={
                <ProtectedRoute permission="users.view">
                  <Users />
                </ProtectedRoute>
              }
            />
            <Route
              path="/properties"
              element={
                <ProtectedRoute permission="properties.view">
                  <PropertiesPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/properties/new"
              element={
                <ProtectedRoute permission="properties.manage">
                  <PropertyFormPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/properties/:id/edit"
              element={
                <ProtectedRoute permission="properties.manage">
                  <PropertyFormPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/properties/:id"
              element={
                <ProtectedRoute permission="properties.view">
                  <PropertyDetailPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/buildings"
              element={
                <ProtectedRoute permission="buildings.view">
                  <BuildingsPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/buildings/:id"
              element={
                <ProtectedRoute permission="buildings.view">
                  <BuildingDetailPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/units"
              element={
                <ProtectedRoute permission="units.view">
                  <UnitsPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/units/:id"
              element={
                <ProtectedRoute permission="units.view">
                  <UnitDetailPage />
                </ProtectedRoute>
              }
            />
            {/* P3 — Tenants & Leasing */}
            <Route
              path="/tenants"
              element={
                <ProtectedRoute permission="tenants.view">
                  <TenantsPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/tenants/new"
              element={
                <ProtectedRoute permission="tenants.manage">
                  <TenantFormPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/tenants/:id/edit"
              element={
                <ProtectedRoute permission="tenants.manage">
                  <TenantFormPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/tenants/:id"
              element={
                <ProtectedRoute permission="tenants.view">
                  <TenantDetailPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/leases"
              element={
                <ProtectedRoute permission="leases.view">
                  <LeasesPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/leases/new"
              element={
                <ProtectedRoute permission="leases.manage">
                  <LeaseFormPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/leases/:id/edit"
              element={
                <ProtectedRoute permission="leases.manage">
                  <LeaseFormPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/leases/:id"
              element={
                <ProtectedRoute permission="leases.view">
                  <LeaseDetailPage />
                </ProtectedRoute>
              }
            />
            {/* P4 — Rent, Payments & Collections */}
            <Route
              path="/billing"
              element={
                <ProtectedRoute permission="billing.view">
                  <FinancialDashboardPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/billing/invoices"
              element={
                <ProtectedRoute permission="invoices.view">
                  <InvoicesPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/billing/invoices/:id"
              element={
                <ProtectedRoute permission="invoices.view">
                  <InvoiceDetailPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/billing/payments"
              element={
                <ProtectedRoute permission="payments.view">
                  <PaymentsPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/billing/payments/new"
              element={
                <ProtectedRoute permission="payments.record">
                  <PaymentFormPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/billing/payments/:id"
              element={
                <ProtectedRoute permission="payments.view">
                  <PaymentDetailPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/billing/ledger"
              element={
                <ProtectedRoute permission="ledger.view">
                  <LedgerPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/billing/arrears"
              element={
                <ProtectedRoute permission="billing.view">
                  <ArrearsPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/billing/dunning"
              element={
                <ProtectedRoute permission="dunning.view">
                  <DunningPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/billing/rent-cycle"
              element={
                <ProtectedRoute permission="invoices.generate">
                  <RentCyclePage />
                </ProtectedRoute>
              }
            />
            {/* P5 — Deposits */}
            <Route
              path="/deposits"
              element={
                <ProtectedRoute permission="deposits.view">
                  <DepositsPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/deposits/new"
              element={
                <ProtectedRoute permission="deposits.manage">
                  <DepositFormPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/deposits/:id"
              element={
                <ProtectedRoute permission="deposits.view">
                  <DepositDetailPage />
                </ProtectedRoute>
              }
            />
            {/* P5 — Maintenance */}
            <Route
              path="/maintenance"
              element={
                <ProtectedRoute permission="maintenance.view">
                  <MaintenanceDashboardPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/maintenance/tickets"
              element={
                <ProtectedRoute permission="maintenance.view">
                  <TicketsPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/maintenance/tickets/new"
              element={
                <ProtectedRoute permission="maintenance.report">
                  <TicketFormPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/maintenance/tickets/:id"
              element={
                <ProtectedRoute permission="maintenance.view">
                  <TicketDetailPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/maintenance/vendors"
              element={
                <ProtectedRoute permission="maintenance.view">
                  <VendorsPage />
                </ProtectedRoute>
              }
            />
            {/* P6 — Utilities */}
            <Route
              path="/utilities/meters"
              element={
                <ProtectedRoute permission="utilities.view">
                  <MetersPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/utilities/meters/new"
              element={
                <ProtectedRoute permission="utilities.manage">
                  <MeterFormPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/utilities/meters/:id"
              element={
                <ProtectedRoute permission="utilities.view">
                  <MeterDetailPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/utilities/bills"
              element={
                <ProtectedRoute permission="utilities.view">
                  <UtilityBillsPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/utilities/bills/:id"
              element={
                <ProtectedRoute permission="utilities.view">
                  <UtilityBillDetailPage />
                </ProtectedRoute>
              }
            />
            {/* P6 — Expenses */}
            <Route
              path="/expenses"
              element={
                <ProtectedRoute permission="expenses.view">
                  <ExpensesPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/expenses/new"
              element={
                <ProtectedRoute permission="expenses.manage">
                  <ExpenseFormPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/expenses/approvals"
              element={
                <ProtectedRoute permission="expenses.approve">
                  <ExpenseApprovalsPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/expenses/:id"
              element={
                <ProtectedRoute permission="expenses.view">
                  <ExpenseDetailPage />
                </ProtectedRoute>
              }
            />
            {/* P7 — Owner Statements & Reports */}
            <Route
              path="/statements"
              element={
                <ProtectedRoute permission="statements.view">
                  <StatementsPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/statements/generate"
              element={
                <ProtectedRoute permission="statements.generate">
                  <StatementGeneratePage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/statements/review"
              element={
                <ProtectedRoute permission="statements.generate">
                  <StatementReviewPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/statements/reports"
              element={
                <ProtectedRoute permission="statements.view">
                  <OwnerReportsPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/statements/:id"
              element={
                <ProtectedRoute permission="statements.view">
                  <StatementDetailPage />
                </ProtectedRoute>
              }
            />
          </Route>

          <Route path="/404" element={<NotFound />} />
          <Route path="*" element={<Navigate to="/404" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
