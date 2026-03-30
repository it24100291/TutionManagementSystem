import { useMemo, useState, useEffect } from 'react';
import axios from '../api/axios';

const PendingUsersTable = ({ title, users, onApprove, onReject }) => (
  <div className="table-card" style={{ marginBottom: '24px' }}>
    <div className="dashboard-header" style={{ marginBottom: '12px' }}>
      <div>
        <h3 style={{ margin: 0 }}>{title}</h3>
        <p className="dashboard-subtitle" style={{ marginTop: '6px' }}>
          {users.length === 0 ? `No pending ${title.toLowerCase()} at this time.` : `${users.length} pending ${title.toLowerCase()}.`}
        </p>
      </div>
    </div>

    {users.length === 0 ? (
      <div className="dashboard-empty">No pending {title.toLowerCase()} at this time.</div>
    ) : (
      <table className="dashboard-table">
        <thead>
          <tr>
            <th>Full Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Created At</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          {users.map((user) => (
            <tr key={user.id}>
              <td>{user.full_name}</td>
              <td>{user.email}</td>
              <td><span className="role-pill">{user.role}</span></td>
              <td>{new Date(user.created_at).toLocaleDateString()}</td>
              <td className="action-row">
                <button
                  type="button"
                  className="success-button"
                  onClick={() => onApprove(user.id, user.full_name)}
                >
                  Approve
                </button>
                <button
                  type="button"
                  className="danger-button"
                  onClick={() => onReject(user.id, user.full_name)}
                >
                  Reject
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    )}
  </div>
);

const AdminPendingUsers = () => {
  const [users, setUsers] = useState([]);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchUsers();
  }, []);

  const studentUsers = useMemo(
    () => users.filter((user) => String(user.role || '').toUpperCase() === 'STUDENT'),
    [users]
  );

  const tutorUsers = useMemo(
    () => users.filter((user) => String(user.role || '').toUpperCase() === 'TUTOR'),
    [users]
  );

  const fetchUsers = async () => {
    setLoading(true);
    setError('');
    try {
      const res = await axios.get('/admin/pending-users');
      setUsers(res.data.data);
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to load pending users');
    } finally {
      setLoading(false);
    }
  };

  const handleApprove = async (userId, userName) => {
    if (!confirm(`Approve user: ${userName}?`)) return;

    setError('');
    setSuccess('');

    try {
      const res = await axios.post(`/admin/approve-user/${userId}`, {
        decision: 'APPROVE'
      });

      setUsers(users.filter((u) => u.id !== userId));
      setSuccess(res.data.data?.message || 'User approved successfully');
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to approve user');
    }
  };

  const handleReject = async (userId, userName) => {
    const reason = prompt(`Reject user: ${userName}\n\nReason (optional):`);

    if (reason === null) return;

    setError('');
    setSuccess('');

    try {
      const res = await axios.post(`/admin/approve-user/${userId}`, {
        decision: 'REJECT',
        reason: reason.trim() || undefined
      });

      setUsers(users.filter((u) => u.id !== userId));
      setSuccess(res.data.data?.message || 'User rejected successfully');
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to reject user');
    }
  };

  if (loading) {
    return (
      <div className="container">
        <div className="form-box dashboard-shell">
          <div className="dashboard-empty">Loading pending users...</div>
        </div>
      </div>
    );
  }

  return (
    <div className="container">
      <div className="form-box dashboard-shell dashboard-wide">
        <div className="dashboard-header">
          <div>
            <h2>Pending Users</h2>
            <p className="dashboard-subtitle">Review new registrations and approve the right accounts quickly.</p>
          </div>
        </div>

        {error && <div className="error">{error}</div>}
        {success && <div className="success">{success}</div>}

        {users.length === 0 ? (
          <div className="dashboard-empty">No pending users at this time.</div>
        ) : (
          <>
            <PendingUsersTable
              title="Students"
              users={studentUsers}
              onApprove={handleApprove}
              onReject={handleReject}
            />
            <PendingUsersTable
              title="Tutors"
              users={tutorUsers}
              onApprove={handleApprove}
              onReject={handleReject}
            />
          </>
        )}
      </div>
    </div>
  );
};

export default AdminPendingUsers;
