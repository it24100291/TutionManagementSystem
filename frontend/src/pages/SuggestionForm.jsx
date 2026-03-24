import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from '../api/axios';

const SuggestionForm = () => {
  const [formData, setFormData] = useState({ 
    type: 'SUGGESTION', 
    title: '', 
    description: '' 
  });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      await axios.post('/suggestions', formData);
      navigate('/suggestions/mine');
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to submit');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="container">
      <div className="form-box dashboard-shell">
        <div className="dashboard-header">
          <div>
            <h2>New Suggestion/Complaint</h2>
            <p className="dashboard-subtitle">Share a clear title and enough detail so it can be reviewed quickly.</p>
          </div>
          <button type="button" className="secondary-button" onClick={() => navigate('/profile')}>Back to Profile</button>
        </div>
        {error && <div className="error">{error}</div>}
        
        <form onSubmit={handleSubmit} className="profile-form">
          <div className="form-group">
            <label htmlFor="suggestion_type">Type</label>
            <select 
              id="suggestion_type"
              name="type"
              value={formData.type} 
              onChange={(e) => setFormData({...formData, type: e.target.value})}
              required
            >
              <option value="SUGGESTION">Suggestion</option>
              <option value="COMPLAINT">Complaint</option>
            </select>
          </div>

          <div className="form-group">
            <label htmlFor="suggestion_title">Title (max 150 characters)</label>
            <input 
              id="suggestion_title"
              name="title"
              type="text" 
              value={formData.title} 
              onChange={(e) => setFormData({...formData, title: e.target.value})} 
              maxLength={150}
              required 
            />
          </div>

          <div className="form-group">
            <label htmlFor="suggestion_description">Description</label>
            <textarea 
              id="suggestion_description"
              name="description"
              value={formData.description} 
              onChange={(e) => setFormData({...formData, description: e.target.value})} 
              rows={5}
              required 
            />
          </div>

          <div className="profile-footer">
            <button type="submit" disabled={loading}>
              {loading ? 'Submitting...' : 'Submit'}
            </button>
            <button 
              type="button"
              className="secondary-button"
              onClick={() => navigate('/profile')}
              disabled={loading}
            >
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default SuggestionForm;
