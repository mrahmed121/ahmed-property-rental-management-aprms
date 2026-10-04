import Card from '../shared/Card';
import EmptyState from '../shared/EmptyState';

/**
 * P1 placeholder. Full user management ships in a later phase.
 * Route is permission-gated (users.view) so only authorized roles see it.
 */
export default function Users() {
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-100">Users</h1>
        <p className="mt-1 text-sm text-gray-500">
          Manage team members and role assignments.
        </p>
      </div>
      <Card>
        <EmptyState
          title="User management is coming soon"
          message="Team member invitations and role assignment will be available here in a later phase."
        />
      </Card>
    </div>
  );
}
