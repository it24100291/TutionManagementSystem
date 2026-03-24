import { useState, useEffect } from 'react';
import axios from '../api/axios';

const AdminSuggestions = () => {
  const [suggestions, setSuggestions] = useState([]);
  const [pagination, setPagination] = useState({});
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState({ status: '', type: '' });
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [editingId, setEditingId] = useState(null);
  const [editData, setEditData] = useState({ status: '', admin_note: '' });
  const [replyingId, setReplyingId] = useState(null);
  const [replyMessage, setReplyMessage] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchSuggestions();
  }, [page, filters]);

  const fetchSuggestions = async () => {
    setLoading(true);
    setError('');
    try {
      const params = new URLSearchParams({ page, limit: 10 });
      if (filters.status) params.append('status', filters.status);
      if (filters.type) params.append('type', filters.type);
      
      const res = await axios.get(`/admin/suggestions?${params}`);
      setSuggestions(res.data.data.items);
      setPagination(res.data.data.pagination);
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to load suggestions');
    } finally {
      setLoading(false);
    }
  };

  const handleEdit = (item) => {
    setEditingId(item.id);
    setEditData({ 
      status: item.status, 
      admin_note: item.admin_note || '' 
    });
    setSuccess('');
    setError('');
  };

  const handleSave = async (id) => {
    setError('');
    setSuccess('');
    
    try {
      await axios.put(`/admin/suggestions/${id}`, editData);
      setEditingId(null);
      setSuccess('Suggestion updated successfully');
      fetchSuggestions();
    } catch (err) {
      setError(err.response?.data?.error || 'Update failed');
    }
  };

  const handleCancel = () => {
    setEditingId(null);
    setEditData({ status: '', admin_note: '' });
    setError('');
  };

  const handleReplyStart = (item) => {
    setReplyingId(item.id);
    setReplyMessage(item.reply_message || '');
    setEditingId(null);
    setError('');
    setSuccess('');
  };

  const handleReplySave = async (id) => {
    setError('');
    setSuccess('');

    try {
      await axios.put(`/admin/suggestions/${id}`, { reply_message: replyMessage });
      setReplyingId(null);
      setReplyMessage('');
      setSuccess('Reply sent successfully');
      fetchSuggestions();
    } catch (err) {
      setError(err.response?.data?.error || 'Reply failed');
    }
  };

  const handleReplyCancel = () => {
    setReplyingId(null);
    setReplyMessage('');
    setError('');
  };

  const handleFilterChange = (field, value) => {
    setFilters({ ...filters, [field]: value });
    setPage(1); // Reset to first page when filters change
  };

  return (
    <div className="container">
      <div className="form-box dashboard-shell dashboard-xwide">
        <div className="dashboard-header">
          <div>
            <h2>All Suggestions & Complaints</h2>
            <p className="dashboard-subtitle">Track incoming feedback, update statuses, and keep a clear response trail.</p>
          </div>
        </div>

        {error && <div className="error">{error}</div>}
        {success && <div className="success">{success}</div>}

        <div className="filter-card">
          <div className="filter-group">
            <label htmlFor="status-filter">Status</label>
            <select 
              id="status-filter"
              name="status"
              value={filters.status} 
              onChange={(e) => handleFilterChange('status', e.target.value)}
            >
              <option value="">All</option>
              <option value="OPEN">Open</option>
              <option value="IN_PROGRESS">In Progress</option>
              <option value="RESOLVED">Resolved</option>
            </select>
          </div>
          
          <div className="filter-group">
            <label htmlFor="type-filter">Type</label>
            <select 
              id="type-filter"
              name="type"
              value={filters.type} 
              onChange={(e) => handleFilterChange('type', e.target.value)}
            >
              <option value="">All</option>
              <option value="SUGGESTION">Suggestion</option>
              <option value="COMPLAINT">Complaint</option>
            </select>
          </div>
        </div>

        {loading ? (
          <div className="dashboard-empty">Loading suggestions...</div>
        ) : suggestions.length === 0 ? (
          <div className="dashboard-empty">No suggestions found.</div>
        ) : (
          <>
            <div className="table-card">
              <table className="dashboard-table">
                <thead>
                  <tr>
                    <th>Type</th>
                    <th>Title</th>
                    <th>Created By</th>
                    <th>Status</th>
                    <th>Admin Note</th>
                    <th>Reply</th>
                    <th>Created</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {suggestions.map(item => (
                    <tr key={item.id}>
                      <td><span className="role-pill">{item.type}</span></td>
                      <td className="truncate-cell">{item.title}</td>
                      <td>{item.creator_name}</td>
                      <td>
                        {editingId === item.id ? (
                          <select
                            value={editData.status}
                            onChange={(e) => setEditData({...editData, status: e.target.value})}
                            className="inline-input"
                          >
                            <option value="OPEN">Open</option>
                            <option value="IN_PROGRESS">In Progress</option>
                            <option value="RESOLVED">Resolved</option>
                          </select>
                        ) : (
                          <span className={`profile-badge ${item.status?.toLowerCase()}`}>{item.status.replace('_', ' ')}</span>
                        )}
                      </td>
                      <td>
                        {editingId === item.id ? (
                          <input
                            type="text"
                            value={editData.admin_note}
                            onChange={(e) => setEditData({...editData, admin_note: e.target.value})}
                            placeholder="Add note..."
                            className="inline-input"
                          />
                        ) : (
                          item.admin_note || '-'
                        )}
                      </td>
                      <td>
                        {replyingId === item.id ? (
                          <input
                            type="text"
                            value={replyMessage}
                            onChange={(e) => setReplyMessage(e.target.value)}
                            placeholder="Type reply message..."
                            className="inline-input"
                          />
                        ) : (
                          item.reply_message || '-'
                        )}
                      </td>
                      <td>{new Date(item.created_at).toLocaleDateString()}</td>
                      <td className="action-row">
                        {editingId === item.id ? (
                          <>
                            <button
                              type="button"
                              className="success-button"
                              onClick={() => handleSave(item.id)}
                            >
                              Save
                            </button>
                            <button
                              type="button"
                              className="secondary-button"
                              onClick={handleCancel}
                            >
                              Cancel
                            </button>
                          </>
                        ) : replyingId === item.id ? (
                          <>
                            <button
                              type="button"
                              className="success-button"
                              onClick={() => handleReplySave(item.id)}
                            >
                              Send Reply
                            </button>
                            <button
                              type="button"
                              className="secondary-button"
                              onClick={handleReplyCancel}
                            >
                              Cancel
                            </button>
                          </>
                        ) : (
                          <>
                            <button type="button" onClick={() => handleEdit(item)}>Edit</button>
                            <button type="button" className="secondary-button" onClick={() => handleReplyStart(item)}>Reply</button>
                          </>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="pagination-bar">
              <button 
                type="button"
                onClick={() => setPage(p => Math.max(1, p - 1))} 
                disabled={page === 1}
              >
                Previous
              </button>
              <span className="pagination-summary">Page {pagination.page} of {pagination.total_pages} ({pagination.total} total)</span>
              <button 
                type="button"
                onClick={() => setPage(p => p + 1)} 
                disabled={page >= pagination.total_pages}
              >
                Next
              </button>
            </div>
          </>
        )}
      </div>
    </div>
  );
};

export default AdminSuggestions;
