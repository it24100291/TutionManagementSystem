import { useEffect, useMemo, useState } from 'react';
import axios, { API_BASE_URL } from '../api/axios';

const emptyModalState = { id: null, status: '', tutorName: '' };

function statusClassName(status) {
  return String(status || '').toLowerCase();
}

function getAttachmentFiles(item) {
  const files = Array.isArray(item.attachment_files) ? item.attachment_files : [];
  if (files.length > 0) {
    return files;
  }
  return item.attachment_path ? [item.attachment_path] : [];
}

const AdminLeaveRequests = () => {
  const [requests, setRequests] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [modalState, setModalState] = useState(emptyModalState);
  const [replyMessage, setReplyMessage] = useState('');
  const [saving, setSaving] = useState(false);

  const pendingCount = useMemo(
    () => requests.filter((item) => item.status === 'Pending').length,
    [requests]
  );

  const fetchRequests = async () => {
    setLoading(true);
    setError('');
    try {
      const response = await axios.get('/admin/tutor-leave-requests');
      setRequests(Array.isArray(response.data?.data) ? response.data.data : []);
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to load leave requests');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRequests();
  }, []);

  const openModal = (item, status) => {
    setModalState({
      id: item.id,
      status,
      tutorName: item.tutor_name,
    });
    setReplyMessage('');
    setError('');
    setSuccess('');
  };

  const closeModal = () => {
    if (saving) return;
    setModalState(emptyModalState);
    setReplyMessage('');
  };

  const handleSave = async () => {
    if (!modalState.id) return;

    if (modalState.status === 'Rejected' && !replyMessage.trim()) {
      setError('Reply is required when rejecting a request');
      return;
    }

    setSaving(true);
    setError('');
    setSuccess('');

    try {
      const response = await axios.put(`/admin/tutor-leave-requests/${modalState.id}`, {
        status: modalState.status,
        admin_reply: replyMessage.trim(),
      });

      const updated = response.data?.data;
      setRequests((current) =>
        current.map((item) => (item.id === updated.id ? { ...item, ...updated } : item))
      );
      setModalState(emptyModalState);
      setReplyMessage('');
      setSuccess(`Leave request ${modalState.status.toLowerCase()} successfully`);
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to update leave request');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="container">
      <div className="form-box dashboard-shell dashboard-xwide">
        <div className="dashboard-header">
          <div>
            <h2>Leave Requests</h2>
            <p className="dashboard-subtitle">
              Review tutor leave submissions, record decisions, and keep reply notes visible.
            </p>
          </div>
        </div>

        {error && <div className="error">{error}</div>}
        {success && <div className="success">{success}</div>}

        <div className="timetable-summary">
          <div className="summary-card">
            <span className="summary-label">Total Requests</span>
            <strong>{requests.length}</strong>
          </div>
          <div className="summary-card">
            <span className="summary-label">Pending</span>
            <strong>{pendingCount}</strong>
          </div>
          <div className="summary-card">
            <span className="summary-label">Resolved</span>
            <strong>{Math.max(requests.length - pendingCount, 0)}</strong>
          </div>
        </div>

        {loading ? (
          <div className="dashboard-empty">Loading leave requests...</div>
        ) : requests.length === 0 ? (
          <div className="dashboard-empty">No tutor leave requests found.</div>
        ) : (
          <div className="table-card leave-requests-table-wrap">
            <table className="dashboard-table leave-requests-table">
              <thead>
                <tr>
                  <th>Tutor Name</th>
                  <th>Subject</th>
                  <th>Leave Date</th>
                  <th>Reason</th>
                  <th>Attachment</th>
                  <th>Status</th>
                  <th>Admin Reply</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {requests.map((item) => (
                  <tr key={item.id}>
                    <td>{item.tutor_name}</td>
                    <td>{item.subject}</td>
                    <td>{item.leave_date}</td>
                    <td className="leave-request-reason-cell">{item.reason}</td>
                    <td>
                      {getAttachmentFiles(item).length > 0 ? (
                        <div className="leave-request-file-list">
                          {getAttachmentFiles(item).map((filePath, index) => (
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
                      <span className={`profile-badge ${statusClassName(item.status)}`}>
                        {item.status}
                      </span>
                    </td>
                    <td>{item.admin_reply || '-'}</td>
                    <td className="action-row">
                      {item.status === 'Pending' ? (
                        <>
                          <button
                            type="button"
                            className="success-button"
                            onClick={() => openModal(item, 'Approved')}
                          >
                            Approve
                          </button>
                          <button
                            type="button"
                            className="danger-button"
                            onClick={() => openModal(item, 'Rejected')}
                          >
                            Reject
                          </button>
                        </>
                      ) : (
                        <span className="leave-request-helper">Decision recorded</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {modalState.id ? (
        <div className="dashboard-modal-overlay" onClick={closeModal}>
          <div className="dashboard-modal-window" onClick={(event) => event.stopPropagation()}>
            <div className="dashboard-modal-header">
              <div>
                <h3>{modalState.status === 'Approved' ? 'Approve Leave Request' : 'Reject Leave Request'}</h3>
                <p className="dashboard-subtitle">
                  {modalState.status === 'Approved'
                    ? `Optional reply for ${modalState.tutorName}.`
                    : `Reply is required for ${modalState.tutorName}.`}
                </p>
              </div>
              <button type="button" className="secondary-button" onClick={closeModal}>
                Close
              </button>
            </div>

            <div className="dashboard-edit-card leave-request-modal-body">
              <div className="form-group">
                <label htmlFor="leave-request-reply">Reply Message</label>
                <textarea
                  id="leave-request-reply"
                  rows="5"
                  value={replyMessage}
                  onChange={(event) => setReplyMessage(event.target.value)}
                  placeholder={
                    modalState.status === 'Approved'
                      ? 'Optional confirmation message...'
                      : 'Enter the reason for rejection...'
                  }
                />
              </div>

              <div className="profile-footer">
                <button
                  type="button"
                  className={modalState.status === 'Approved' ? 'success-button' : 'danger-button'}
                  onClick={handleSave}
                  disabled={saving}
                >
                  {saving ? 'Saving...' : modalState.status}
                </button>
                <button type="button" className="secondary-button" onClick={closeModal} disabled={saving}>
                  Cancel
                </button>
              </div>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
};

export default AdminLeaveRequests;
