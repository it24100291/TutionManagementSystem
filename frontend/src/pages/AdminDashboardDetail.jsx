import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { createPortal } from 'react-dom';
import axios from '../api/axios';

const detailConfigs = {
  students: {
    title: 'Registered Students',
    subtitle: 'Review all student registrations and their saved details.',
    empty: 'No registered students found.',
    createMode: 'redirect',
    createLabel: 'Register Student',
    createTo: '/register',
    columns: [
      { key: 'full_name', label: 'Full Name' },
      { key: 'email', label: 'Email', className: 'truncate-cell' },
      { key: 'grade', label: 'Grade' },
      { key: 'school_name', label: 'School' },
      { key: 'phone', label: 'Phone' },
      { key: 'status', label: 'Status', badge: true },
      { key: 'guardian_name', label: 'Guardian' },
      { key: 'created_at', label: 'Created At', date: true },
    ],
    formFields: [
      { key: 'full_name', label: 'Full Name', type: 'text', required: true },
      { key: 'email', label: 'Email', type: 'email', required: true },
      { key: 'phone', label: 'Phone', type: 'text' },
      { key: 'status', label: 'Status', type: 'select', options: ['PENDING', 'ACTIVE', 'REJECTED'], required: true },
      { key: 'school_name', label: 'School Name', type: 'text', required: true },
      { key: 'grade', label: 'Grade', type: 'text', required: true },
      { key: 'guardian_name', label: 'Guardian Name', type: 'text', required: true },
    ],
  },
  tutors: {
    title: 'Registered Tutors',
    subtitle: 'Review tutor registrations, subjects, and contact details.',
    empty: 'No registered tutors found.',
    createMode: 'redirect',
    createLabel: 'Register Tutor',
    createTo: '/register',
    columns: [
      { key: 'full_name', label: 'Full Name' },
      { key: 'email', label: 'Email', className: 'truncate-cell' },
      { key: 'subject', label: 'Subject' },
      { key: 'nic_number', label: 'NIC Number' },
      { key: 'phone', label: 'Phone' },
      { key: 'status', label: 'Status', badge: true },
      { key: 'created_at', label: 'Created At', date: true },
    ],
    formFields: [
      { key: 'full_name', label: 'Full Name', type: 'text', required: true },
      { key: 'email', label: 'Email', type: 'email', required: true },
      { key: 'phone', label: 'Phone', type: 'text' },
      { key: 'status', label: 'Status', type: 'select', options: ['PENDING', 'ACTIVE', 'REJECTED'], required: true },
      { key: 'nic_number', label: 'NIC Number', type: 'text', required: true },
      { key: 'subject', label: 'Subject', type: 'text', required: true },
    ],
  },
  classes: {
    title: 'Classes',
    subtitle: 'See the class records currently available in the system.',
    empty: 'No class records found.',
    createMode: 'modal',
    createLabel: 'Add Class',
    columns: [
      { key: 'id', label: 'ID' },
      { key: 'name', label: 'Name' },
      { key: 'title', label: 'Title' },
      { key: 'subject', label: 'Subject' },
      { key: 'grade', label: 'Grade' },
      { key: 'created_at', label: 'Created At', date: true },
    ],
    formFields: [
      { key: 'name', label: 'Name', type: 'text', required: true },
      { key: 'title', label: 'Title', type: 'text', required: true },
      { key: 'subject', label: 'Subject', type: 'text', required: true },
      { key: 'grade', label: 'Grade', type: 'text', required: true },
    ],
  },
  'paid-payments': {
    title: 'Paid Fees',
    subtitle: 'Review successfully paid fee records.',
    empty: 'No paid fee records found.',
    createMode: 'modal',
    createLabel: 'Add Payment',
    columns: [
      { key: 'id', label: 'Payment ID' },
      { key: 'user_id', label: 'User ID' },
      { key: 'amount', label: 'Amount', currency: true },
      { key: 'status', label: 'Status', badge: true },
      { key: 'created_at', label: 'Created At', date: true },
    ],
    formFields: [
      { key: 'user_id', label: 'User ID', type: 'number', required: true },
      { key: 'amount', label: 'Amount', type: 'number', required: true },
      { key: 'status', label: 'Status', type: 'select', options: ['Paid', 'Unpaid'], required: true },
    ],
  },
  'pending-payments': {
    title: 'Pending Payments',
    subtitle: 'Track unpaid fee records that still need attention.',
    empty: 'No pending payment records found.',
    createMode: 'modal',
    createLabel: 'Add Payment',
    columns: [
      { key: 'id', label: 'Payment ID' },
      { key: 'user_id', label: 'User ID' },
      { key: 'amount', label: 'Amount', currency: true },
      { key: 'status', label: 'Status', badge: true },
      { key: 'created_at', label: 'Created At', date: true },
    ],
    formFields: [
      { key: 'user_id', label: 'User ID', type: 'number', required: true },
      { key: 'amount', label: 'Amount', type: 'number', required: true },
      { key: 'status', label: 'Status', type: 'select', options: ['Paid', 'Unpaid'], required: true },
    ],
  },
  'active-users': {
    title: 'Active Users',
    subtitle: 'Review users who currently have active access.',
    empty: 'No active users found.',
    columns: [
      { key: 'full_name', label: 'Full Name' },
      { key: 'email', label: 'Email', className: 'truncate-cell' },
      { key: 'role', label: 'Role' },
      { key: 'phone', label: 'Phone' },
      { key: 'status', label: 'Status', badge: true },
      { key: 'created_at', label: 'Created At', date: true },
    ],
    formFields: [
      { key: 'full_name', label: 'Full Name', type: 'text', required: true },
      { key: 'email', label: 'Email', type: 'email', required: true },
      { key: 'phone', label: 'Phone', type: 'text' },
      { key: 'status', label: 'Status', type: 'select', options: ['PENDING', 'ACTIVE', 'REJECTED'], required: true },
    ],
  },
};

const formatValue = (row, column) => {
  const value = row[column.key];
  if (column.date) {
    return value ? new Date(value).toLocaleDateString() : 'N/A';
  }
  if (column.currency) {
    return new Intl.NumberFormat('en-LK', {
      style: 'currency',
      currency: 'LKR',
      maximumFractionDigits: 0,
    }).format(Number(value || 0));
  }
  return value || 'N/A';
};

const buildInitialForm = (config, row = {}) => {
  const next = {};
  (config?.formFields || []).forEach((field) => {
    next[field.key] = row[field.key] ?? '';
  });
  return next;
};

const renderDashboardModal = (content) => {
  if (typeof document === 'undefined') {
    return null;
  }

  return createPortal(content, document.body);
};

const AdminDashboardDetail = () => {
  const { metric } = useParams();
  const navigate = useNavigate();
  const config = useMemo(() => detailConfigs[metric], [metric]);
  const fixedGradeOptions = ['Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11'];
  const [rows, setRows] = useState([]);
  const [selectedGrade, setSelectedGrade] = useState('ALL');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [formMode, setFormMode] = useState(null);
  const [editingRowId, setEditingRowId] = useState(null);
  const [formValues, setFormValues] = useState({});
  const [submitting, setSubmitting] = useState(false);

  const normalizeGrade = (grade) => {
    const value = String(grade || '').trim();
    if (!value) return '';
    return value.toLowerCase().startsWith('grade ') ? value : `Grade ${value}`;
  };

  const filteredRows = metric !== 'students' || selectedGrade === 'ALL'
    ? rows
    : rows.filter((row) => normalizeGrade(row.grade) === selectedGrade);

  const fetchRows = async () => {
    if (!config) {
      setError('Dashboard detail not found');
      setLoading(false);
      return;
    }

    try {
      setLoading(true);
      const res = await axios.get(`/admin/dashboard/${metric}`);
      setRows(res.data.data || []);
      setError('');
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to load dashboard details');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRows();
  }, [config, metric]);

  const openCreate = () => {
    if (config?.createMode === 'redirect' && config.createTo) {
      navigate(config.createTo);
      return;
    }

    setFormMode('create');
    setEditingRowId(null);
    setFormValues(buildInitialForm(config));
    setError('');
    setSuccess('');
  };

  const openEdit = (row) => {
    setFormMode('edit');
    setEditingRowId(row.id);
    setFormValues(buildInitialForm(config, row));
    setError('');
    setSuccess('');
  };

  const closeForm = () => {
    setFormMode(null);
    setEditingRowId(null);
    setFormValues({});
  };

  const handleChange = (key, value) => {
    setFormValues((current) => ({ ...current, [key]: value }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    setError('');
    setSuccess('');

    try {
      if (formMode === 'create') {
        const res = await axios.post(`/admin/dashboard/${metric}`, formValues);
        setSuccess(res.data.data?.message || 'Record created');
      } else if (formMode === 'edit' && editingRowId) {
        const res = await axios.put(`/admin/dashboard/${metric}/${editingRowId}`, formValues);
        setSuccess(res.data.data?.message || 'Record updated');
      }

      closeForm();
      fetchRows();
    } catch (err) {
      setError(err.response?.data?.error || 'Unable to save changes');
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async (rowId) => {
    if (!window.confirm('Delete this record?')) {
      return;
    }

    setError('');
    setSuccess('');

    try {
      const res = await axios.delete(`/admin/dashboard/${metric}/${rowId}`);
      setSuccess(res.data.data?.message || 'Record deleted');
      fetchRows();
    } catch (err) {
      setError(err.response?.data?.error || 'Unable to delete record');
    }
  };

  if (loading) {
    return (
      <div className="container">
        <div className="form-box dashboard-shell dashboard-xwide">
          <div className="dashboard-empty">Loading dashboard details...</div>
        </div>
      </div>
    );
  }

  return (
    <div className="container">
      <div className="form-box dashboard-shell dashboard-xwide">
        <div className="dashboard-header">
          <div>
            <h2>{config?.title || 'Dashboard Detail'}</h2>
            <p className="dashboard-subtitle">{config?.subtitle || 'Review the selected dashboard records.'}</p>
          </div>
          <div className="action-row">
            {config?.createLabel && (
              <button type="button" onClick={openCreate}>
                {config.createLabel}
              </button>
            )}
            <button type="button" className="secondary-button" onClick={() => navigate('/admin/dashboard')}>
              Back to Dashboard
            </button>
          </div>
        </div>

        {error && <div className="error">{error}</div>}
        {success && <div className="success">{success}</div>}

        {!error && metric === 'students' && rows.length > 0 && (
          <div className="filter-card">
            <div className="form-group" style={{ minWidth: 220, marginBottom: 0 }}>
              <label htmlFor="dashboard-student-grade-filter">Filter By Grade</label>
              <select
                id="dashboard-student-grade-filter"
                value={selectedGrade}
                onChange={(e) => setSelectedGrade(e.target.value)}
              >
                <option value="ALL">All Grades</option>
                {fixedGradeOptions.map((grade) => (
                  <option key={grade} value={grade}>
                    {grade}
                  </option>
                ))}
              </select>
            </div>

            <div className="form-group" style={{ minWidth: 180, marginBottom: 0 }}>
              <label>Students Shown</label>
              <input type="text" value={`${filteredRows.length}`} readOnly />
            </div>
          </div>
        )}

        {formMode && (formMode === 'edit' || config?.createMode !== 'redirect') && renderDashboardModal(
          <div className="dashboard-modal-overlay" onClick={closeForm}>
            <div
              className="dashboard-modal-window"
              onClick={(e) => e.stopPropagation()}
              role="dialog"
              aria-modal="true"
              aria-labelledby="dashboard-detail-form-title"
            >
              <div className="dashboard-modal-header">
                <h3 id="dashboard-detail-form-title">
                  {formMode === 'create' ? `Create ${config?.title || 'Record'}` : `Edit ${config?.title || 'Record'}`}
                </h3>
                <button type="button" className="secondary-button" onClick={closeForm}>
                  Close
                </button>
              </div>

              <form className="dashboard-edit-card" onSubmit={handleSubmit}>
                <div className="dashboard-edit-grid">
                  {config.formFields.map((field) => (
                    <div className="form-group" key={field.key}>
                      <label htmlFor={`${metric}-${field.key}`}>{field.label}</label>
                      {field.type === 'select' ? (
                        <select
                          id={`${metric}-${field.key}`}
                          value={formValues[field.key] ?? ''}
                          onChange={(e) => handleChange(field.key, e.target.value)}
                          required={field.required}
                        >
                          <option value="">Select {field.label}</option>
                          {field.options.map((option) => (
                            <option key={option} value={option}>{option}</option>
                          ))}
                        </select>
                      ) : (
                        <input
                          id={`${metric}-${field.key}`}
                          type={field.type}
                          value={formValues[field.key] ?? ''}
                          onChange={(e) => handleChange(field.key, e.target.value)}
                          required={field.required}
                        />
                      )}
                    </div>
                  ))}
                </div>
                <div className="profile-footer">
                  <button type="submit" disabled={submitting}>
                    {submitting ? 'Saving...' : formMode === 'create' ? 'Create' : 'Save Changes'}
                  </button>
                  <button type="button" className="secondary-button" onClick={closeForm}>
                    Cancel
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {!error && rows.length === 0 ? (
          <div className="dashboard-empty">{config?.empty || 'No records found.'}</div>
        ) : !error && metric === 'students' && filteredRows.length === 0 ? (
          <div className="dashboard-empty">No students found for the selected grade.</div>
        ) : (
          <div className="table-card">
            <table className="dashboard-table">
              <thead>
                <tr>
                  {(config?.columns || []).map((column) => (
                    <th key={column.key}>{column.label}</th>
                  ))}
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {filteredRows.map((row, index) => (
                  <tr key={row.id || `${metric}-${index}`}>
                    {(config?.columns || []).map((column) => (
                      <td key={column.key} className={column.className || ''}>
                        {column.badge ? (
                          <span className={`profile-badge ${String(row[column.key] || '').toLowerCase()}`}>
                            {row[column.key] || 'N/A'}
                          </span>
                        ) : (
                          column.key === 'grade' ? (normalizeGrade(row.grade) || 'N/A') : formatValue(row, column)
                        )}
                      </td>
                    ))}
                    <td className="action-row">
                      <button type="button" className="secondary-button" onClick={() => openEdit(row)}>
                        Edit
                      </button>
                      <button type="button" className="danger-button" onClick={() => handleDelete(row.id)}>
                        Delete
                      </button>
                    </td>
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

export default AdminDashboardDetail;
