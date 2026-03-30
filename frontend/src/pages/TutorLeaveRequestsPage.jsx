import { useEffect, useState } from 'react';
import axios from '../api/axios';
import { useAuth } from '../context/AuthContext';
import { API_BASE_URL } from '../api/axios';

const getTomorrowDate = () => {
  const date = new Date();
  date.setDate(date.getDate() + 1);
  return date.toISOString().split('T')[0];
};

const renderAttachmentLinks = (item) => {
  const files = Array.isArray(item.attachment_files) ? item.attachment_files : [];
  if (files.length === 0) {
    return item.attachment_path ? [item.attachment_path] : [];
  }
  return files;
};

const TutorLeaveRequestsPage = () => {
  const { user } = useAuth();
  const [formData, setFormData] = useState({ leave_date: '', reason: '' });
  const [attachments, setAttachments] = useState([]);
  const [myRequests, setMyRequests] = useState([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [fieldError, setFieldError] = useState('');
  const minLeaveDate = getTomorrowDate();

  const fetchMyRequests = async () => {
    setLoading(true);
    setError('');
    try {
      const response = await axios.get('/tutor/my-leave-requests');
      setMyRequests(Array.isArray(response.data?.data) ? response.data.data : []);
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to load your leave requests');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchMyRequests();
  }, []);

  const handleChange = (event) => {
    const { name, value } = event.target;
    setFormData((current) => ({ ...current, [name]: value }));
    setError('');
    setSuccess('');
    if (name === 'leave_date') {
      setFieldError('');
    }
  };

  const handleFileChange = (event) => {
    setAttachments(Array.from(event.target.files || []));
    setError('');
    setSuccess('');
  };

  const handleSubmit = async (event) => {
    event.preventDefault();

    if (!formData.leave_date || !formData.reason.trim()) {
      setError('Leave date and reason are required');
      return;
    }

    if (attachments.length === 0) {
      setError('Upload file or worksheet is required');
      return;
    }

    if (formData.leave_date < minLeaveDate) {
      setFieldError('Select a future date only');
      return;
    }

    setSubmitting(true);
    setError('');
    setSuccess('');
    setFieldError('');

    try {
      const payload = new FormData();
      payload.append('leave_date', formData.leave_date);
      payload.append('reason', formData.reason.trim());
      attachments.forEach((file) => payload.append('attachment[]', file));

      const response = await axios.post('/tutor/my-leave-requests', payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      const created = response.data?.data;
      setMyRequests((current) => [created, ...current]);
      setFormData({ leave_date: '', reason: '' });
      setAttachments([]);
      if (event.target?.reset) {
        event.target.reset();
      }
      setSuccess('Leave request submitted successfully');
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to submit leave request');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="container">
      <div className="form-box profile-shell dashboard-shell">
        <div className="profile-header">
          <div>
            <h2>Leave Requests</h2>
            <p className="profile-subtitle">
              Submit your own leave requests here. Student absence requests remain available below.
            </p>
          </div>
        </div>

        {error && <div className="error">{error}</div>}
        {success && <div className="success">{success}</div>}

        <div className="dashboard-edit-card leave-request-layout">
          <div className="leave-request-panel">
            <h3>Submit Leave Request</h3>
            <p className="dashboard-subtitle">Choose a leave date and provide the reason for your absence.</p>
            <form className="leave-request-form" onSubmit={handleSubmit}>
              <div className="form-group">
                <label htmlFor="leave_date">Leave Date</label>
                <input
                  id="leave_date"
                  name="leave_date"
                  type="date"
                  value={formData.leave_date}
                  onChange={handleChange}
                  min={minLeaveDate}
                  className={fieldError ? 'field-invalid' : formData.leave_date ? 'field-valid' : ''}
                />
                {fieldError ? <div className="field-error-message">{fieldError}</div> : null}
              </div>
              <div className="form-group">
                <label htmlFor="reason">Reason</label>
                <textarea
                  id="reason"
                  name="reason"
                  rows="4"
                  value={formData.reason}
                  onChange={handleChange}
                  placeholder="Enter your leave reason"
                />
              </div>
              <div className="form-group">
                <label htmlFor="attachment">Upload File / Worksheet</label>
                <input
                  id="attachment"
                  name="attachment"
                  type="file"
                  accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                  onChange={handleFileChange}
                  multiple
                  required
                />
                <small className="leave-request-note">
                  Required. You can upload multiple files. Allowed: PDF, DOC, DOCX, JPG, JPEG, PNG. Max 5MB each.
                </small>
              </div>
              <div className="profile-footer">
                <button type="submit" className="success-button" disabled={submitting}>
                  {submitting ? 'Submitting...' : 'Submit Request'}
                </button>
              </div>
            </form>
          </div>

          <div className="leave-request-panel">
            <h3>My Leave Requests</h3>
            <p className="dashboard-subtitle">Review the status of your submitted requests and the admin reply.</p>
            {loading ? (
              <div className="dashboard-empty">Loading your leave requests...</div>
            ) : myRequests.length === 0 ? (
              <div className="dashboard-empty">No leave requests submitted yet.</div>
            ) : (
              <div className="table-card leave-requests-table-wrap">
                <table className="dashboard-table leave-requests-table">
                  <thead>
                    <tr>
                      <th>Leave Date</th>
                      <th>Reason</th>
                      <th>Attachment</th>
                      <th>Status</th>
                      <th>Admin Reply</th>
                    </tr>
                  </thead>
                  <tbody>
                    {myRequests.map((item) => (
                      <tr key={item.id}>
                        <td>{item.leave_date}</td>
                        <td className="leave-request-reason-cell">{item.reason}</td>
                        <td>
                          {renderAttachmentLinks(item).length > 0 ? (
                            <div className="leave-request-file-list">
                              {renderAttachmentLinks(item).map((filePath, index) => (
                                <a
                                  key={`${item.id}-${index}`}
                                  href={`${API_BASE_URL}${filePath}`}
                                  target="_blank"
                                  rel="noreferrer"
                                >
                                  {`View File ${index + 1}`}
                                </a>
                              ))}
                            </div>
                          ) : '-'}
                        </td>
                        <td>
                          <span className={`profile-badge ${String(item.status || '').toLowerCase()}`}>
                            {item.status}
                          </span>
                        </td>
                        <td>{item.admin_reply || '-'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default TutorLeaveRequestsPage;
