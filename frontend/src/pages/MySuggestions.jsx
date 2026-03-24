import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from '../api/axios';

const MySuggestions = () => {
  const [suggestions, setSuggestions] = useState([]);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();

  useEffect(() => {
    fetchSuggestions();
  }, []);

  const fetchSuggestions = async () => {
    setLoading(true);
    setError('');
    try {
      const res = await axios.get('/suggestions/mine');
      setSuggestions(res.data.data);
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to load suggestions');
    } finally {
      setLoading(false);
    }
  };

  const getStatusBadge = (status) => {
    return (
      <span className={`profile-badge ${status?.toLowerCase()}`}>
        {status.replace('_', ' ')}
      </span>
    );
  };

  if (loading) {
    return (
      <div className="container">
        <div className="form-box dashboard-shell">
          <div className="dashboard-empty">Loading suggestions...</div>
        </div>
      </div>
    );
  }

  return (
    <div className="container">
      <div className="form-box dashboard-shell dashboard-wide">
        <div className="dashboard-header">
          <div>
            <h2>My Suggestions & Complaints</h2>
            <p className="dashboard-subtitle">Review everything you have submitted and track progress at a glance.</p>
          </div>
          <div className="profile-utilities">
            <button type="button" onClick={() => navigate('/suggestions/new')}>New Suggestion</button>
            <button type="button" className="secondary-button" onClick={() => navigate('/profile')}>Back to Profile</button>
          </div>
        </div>

        {error && <div className="error">{error}</div>}

        {suggestions.length === 0 ? (
          <div className="dashboard-empty">You haven't submitted any suggestions or complaints yet.</div>
        ) : (
          <div className="table-card">
            <table className="dashboard-table">
              <thead>
                <tr>
                  <th>Type</th>
                  <th>Title</th>
                  <th>Description</th>
                  <th>Status</th>
                  <th>Admin Note</th>
                  <th>Reply</th>
                  <th>Created</th>
                </tr>
              </thead>
              <tbody>
                {suggestions.map(item => (
                  <tr key={item.id}>
                    <td><span className="role-pill">{item.type}</span></td>
                    <td>{item.title}</td>
                    <td className="truncate-cell">
                      {item.description}
                    </td>
                    <td>{getStatusBadge(item.status)}</td>
                    <td>{item.admin_note || '-'}</td>
                    <td>{item.reply_message || '-'}</td>
                    <td>{new Date(item.created_at).toLocaleDateString()}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
};

export default MySuggestions;
