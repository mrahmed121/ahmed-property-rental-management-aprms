import { useEffect, useState } from 'react';
import { useAuth } from '../../../context/AuthContext';
import { ownerReportApi, statementErrorMessage } from '../services/statementApi';

const fmt = (n) => `₨${Number(n || 0).toLocaleString('en-PK', { minimumFractionDigits: 2 })}`;

export default function OwnerReportsPage() {
  const { user } = useAuth();
  const [portfolio, setPortfolio] = useState(null);
  const [profit, setProfit] = useState(null);
  const [trend, setTrend] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const ownerId = user?.roles?.includes('owner') ? undefined : undefined;
    // Owners: own reports (no owner_id needed). Staff: must pick — simplified to first owner via portfolio call.
    const load = async () => {
      setLoading(true);
      setError('');
      try {
        // For owner role, backend resolves from auth user.
        const p = await ownerReportApi.portfolio(undefined).catch(() => null);
        if (p) {
          setPortfolio(p);
          const [pr, t] = await Promise.all([
            ownerReportApi.profitability(p.owner?.id).catch(() => null),
            ownerReportApi.trend(p.owner?.id).catch(() => null),
          ]);
          setProfit(pr);
          setTrend(t);
        }
      } catch (err) {
        setError(statementErrorMessage(err, 'Could not load reports.'));
      } finally {
        setLoading(false);
      }
    };
    load();
  }, []);

  if (loading) return <div className="text-slate-400">Loading reports…</div>;
  if (error) return <div className="alert-error">{error}</div>;
  if (!portfolio) return <div className="empty-state">No report data available.</div>;

  return (
    <div className="space-y-6">
      <h1 className="text-xl font-semibold">Owner Reports — {portfolio.owner?.name}</h1>

      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div className="card"><div className="text-slate-400 text-xs">Properties</div><div className="text-lg font-semibold">{portfolio.property_count}</div></div>
        <div className="card"><div className="text-slate-400 text-xs">Finalized Statements</div><div className="text-lg font-semibold">{portfolio.statements.finalized}</div></div>
        <div className="card"><div className="text-slate-400 text-xs">Pending</div><div className="text-lg font-semibold">{portfolio.statements.pending}</div></div>
        <div className="card"><div className="text-slate-400 text-xs">Total Net (finalized)</div><div className="text-lg font-semibold">{fmt(portfolio.statements.total_net)}</div></div>
      </div>

      <div className="card">
        <h2 className="font-semibold mb-3">Portfolio — Occupancy</h2>
        <table className="w-full text-sm">
          <thead><tr className="text-left text-slate-400"><th className="py-1">Property</th><th>Units</th><th>Active Leases</th><th>Occupancy</th></tr></thead>
          <tbody>
            {portfolio.properties.map((p) => (
              <tr key={p.id} className="border-t border-slate-800">
                <td className="py-1">{p.name}</td><td>{p.units}</td><td>{p.active_leases}</td><td>{p.occupancy_rate}%</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {profit && profit.properties.length > 0 && (
        <div className="card">
          <h2 className="font-semibold mb-1">Property Profitability</h2>
          <p className="text-xs text-slate-500 mb-3">Operational owner-level view (not audited accounting profit).</p>
          <table className="w-full text-sm">
            <thead><tr className="text-left text-slate-400"><th className="py-1">Property</th><th className="text-right">Income</th><th className="text-right">Fees</th><th className="text-right">Expenses</th><th className="text-right">Maint.</th><th className="text-right">Utility</th><th className="text-right">Net</th></tr></thead>
            <tbody>
              {profit.properties.map((p) => (
                <tr key={p.property_id} className="border-t border-slate-800">
                  <td className="py-1">{p.property_name}</td>
                  <td className="text-right">{fmt(p.gross_income)}</td>
                  <td className="text-right">({fmt(p.management_fee)})</td>
                  <td className="text-right">({fmt(p.owner_expenses)})</td>
                  <td className="text-right">({fmt(p.owner_maintenance)})</td>
                  <td className="text-right">({fmt(p.owner_utility_absorption)})</td>
                  <td className="text-right font-semibold">{fmt(p.net)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {trend && trend.trend.length > 0 && (
        <div className="card">
          <h2 className="font-semibold mb-3">Monthly Trend</h2>
          <table className="w-full text-sm">
            <thead><tr className="text-left text-slate-400"><th className="py-1">Month</th><th className="text-right">Income</th><th className="text-right">Net</th></tr></thead>
            <tbody>
              {trend.trend.map((t) => (
                <tr key={t.month} className="border-t border-slate-800">
                  <td className="py-1">{t.month}</td>
                  <td className="text-right">{fmt(t.income)}</td>
                  <td className="text-right font-semibold">{fmt(t.net)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
