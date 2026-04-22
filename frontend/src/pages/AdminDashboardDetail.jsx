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
    subtitle: 'Review successfully paid fee records and submitted receipts.',
    empty: 'No paid fee records found.',
    createMode: 'modal',
    createLabel: 'Add Payment',
    columns: [
      { key: 'id', label: 'Payment ID' },
      { key: 'full_name', label: 'Student Name' },
      { key: 'grade', label: 'Grade' },
      { key: 'payment_month', label: 'Month' },
      { key: 'amount', label: 'Amount', currency: true },
      { key: 'receipt_path', label: 'Receipt', link: true },
      { key: 'status', label: 'Status', badge: true },
      { key: 'created_at', label: 'Created At', date: true },
    ],
    formFields: [
      { key: 'user_id', label: 'User ID', type: 'number', required: true },
      { key: 'amount', label: 'Amount', type: 'number', required: true },
      { key: 'status', label: 'Status', type: 'select', options: ['Paid', 'Pending', 'Unpaid'], required: true },
    ],
  },
  'pending-payments': {
    title: 'Pending Payments',
    subtitle: 'Track unpaid or receipt-submitted fee records that still need attention.',
    empty: 'No pending payment records found.',
    createMode: 'modal',
    createLabel: 'Add Payment',
    columns: [
      { key: 'id', label: 'Payment ID' },
      { key: 'full_name', label: 'Student Name' },
      { key: 'grade', label: 'Grade' },
      { key: 'payment_month', label: 'Month' },
      { key: 'amount', label: 'Amount', currency: true },
      { key: 'receipt_path', label: 'Receipt', link: true },
      { key: 'status', label: 'Status', badge: true },
      { key: 'created_at', label: 'Created At', date: true },
    ],
    formFields: [
      { key: 'user_id', label: 'User ID', type: 'number', required: true },
      { key: 'amount', label: 'Amount', type: 'number', required: true },
      { key: 'status', label: 'Status', type: 'select', options: ['Paid', 'Pending', 'Unpaid'], required: true },
    ],
  },
  'student-payment-details': {
    title: 'Student Payment Details',
    subtitle: 'Review all student fee payment records, receipts, and statuses in one place.',
    empty: 'No student payment records found.',
    readOnly: true,
    columns: [
      { key: 'id', label: 'Payment ID' },
      { key: 'full_name', label: 'Student Name' },
      { key: 'grade', label: 'Grade' },
      { key: 'payment_month', label: 'Month' },
      { key: 'amount', label: 'Amount', currency: true },
      { key: 'receipt_path', label: 'Receipt', link: true },
      { key: 'status', label: 'Status', badge: true },
      { key: 'created_at', label: 'Created At', date: true },
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
  'salary-details': {
    title: 'Salary Details',
    subtitle: 'Review monthly tutor salary summaries with hours, rate, and payment status.',
    empty: 'No salary records found.',
    readOnly: true,
    columns: [
      { key: 'full_name', label: 'Tutor Name' },
      { key: 'subject', label: 'Subject' },
      { key: 'payment_month', label: 'Month' },
      { key: 'hours_this_month', label: 'Hours' },
      { key: 'rate_per_hour', label: 'Rate / Hour', currency: true },
      { key: 'amount', label: 'Amount', currency: true },
      { key: 'status', label: 'Status', badge: true },
    ],
  },
  'attendance-records': {
    title: 'All Class Attendance Records',
    subtitle: 'Review attendance entries across all classes and tutors.',
    empty: 'No attendance records found.',
    readOnly: true,
    columns: [
      { key: 'student_name', label: 'Student' },
      { key: 'class_name', label: 'Class' },
      { key: 'grade', label: 'Grade' },
      { key: 'tutor_name', label: 'Tutor' },
      { key: 'status', label: 'Status', badge: true },
      { key: 'marked_at', label: 'Marked At', date: true },
    ],
  },
};

const formatValue = (row, column) => {
  const value = row[column.key];
  if (column.date) {
    return value ? new Date(value).toLocaleDateString() : 'N/A';
  }
  if (column.link) {
    return value ? 'View Receipt' : 'N/A';
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

const resolveReceiptUrl = (path) => {
  const value = String(path || '').trim();
  if (!value) {
    return '';
  }

  const normalizedPath = value.startsWith('/api/uploads/')
    ? value.replace(/^\/api/, '')
    : value;

  return normalizedPath.startsWith('http')
    ? normalizedPath
    : `http://localhost:8000${normalizedPath}`;
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
  const monthOptions = [
    { value: '1', label: 'January' },
    { value: '2', label: 'February' },
    { value: '3', label: 'March' },
    { value: '4', label: 'April' },
    { value: '5', label: 'May' },
    { value: '6', label: 'June' },
    { value: '7', label: 'July' },
    { value: '8', label: 'August' },
    { value: '9', label: 'September' },
    { value: '10', label: 'October' },
    { value: '11', label: 'November' },
    { value: '12', label: 'December' },
  ];
  const currentYear = new Date().getFullYear();
  const currentMonth = new Date().getMonth() + 1;
  const yearOptions = Array.from({ length: 6 }, (_, index) => String(currentYear - 5 + index));
  const [rows, setRows] = useState([]);
  const [selectedGrade, setSelectedGrade] = useState('ALL');
  const [selectedDate, setSelectedDate] = useState('');
  const [selectedMonth, setSelectedMonth] = useState(String(new Date().getMonth() + 1));
  const [selectedYear, setSelectedYear] = useState(String(currentYear));
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [formMode, setFormMode] = useState(null);
  const [editingRowId, setEditingRowId] = useState(null);
  const [formValues, setFormValues] = useState({});
  const [submitting, setSubmitting] = useState(false);
  const todayDate = new Date().toISOString().split('T')[0];

  const normalizeGrade = (grade) => {
    const value = String(grade || '').trim();
    if (!value) return '';
    return value.toLowerCase().startsWith('grade ') ? value : `Grade ${value}`;
  };

  const usesGradeFilter = metric === 'students' || metric === 'attendance-records' || metric === 'student-payment-details';
  const usesDateFilter = metric === 'attendance-records';
  const usesSalaryMonthFilter = metric === 'salary-details';
  const showsActionButtons = !config?.readOnly;
  const availableMonthOptions = usesSalaryMonthFilter && Number(selectedYear) === currentYear
    ? monthOptions.filter((month) => Number(month.value) <= currentMonth)
    : monthOptions;

  const filteredRows = rows.filter((row) => {
    if (usesGradeFilter && selectedGrade !== 'ALL' && normalizeGrade(row.grade) !== selectedGrade) {
      return false;
    }

    if (usesDateFilter && selectedDate) {
      const rowDate = row.marked_at ? new Date(row.marked_at).toISOString().split('T')[0] : '';
      if (rowDate !== selectedDate) {
        return false;
      }
    }

    return true;
  });

  const fetchRows = async (showLoader = true) => {
    if (!config) {
      setError('Dashboard detail not found');
      if (showLoader) {
        setLoading(false);
      }
      return;
    }

    try {
      if (showLoader) {
        setLoading(true);
      }
      const params = new URLSearchParams();
      if (usesSalaryMonthFilter) {
        params.append('month', selectedMonth);
        params.append('year', selectedYear);
      }

      const queryString = params.toString();
      const res = await axios.get(`/admin/dashboard/${metric}${queryString ? `?${queryString}` : ''}`);
      setRows(res.data.data || []);
      setError('');
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to load dashboard details');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (!usesSalaryMonthFilter) {
      return;
    }

    const hasSelectedMonth = availableMonthOptions.some((month) => month.value === selectedMonth);
    if (!hasSelectedMonth) {
      setSelectedMonth(String(Number(selectedYear) === currentYear ? currentMonth : 12));
    }
  }, [usesSalaryMonthFilter, availableMonthOptions, selectedMonth, selectedYear, currentYear, currentMonth]);

  useEffect(() => {
    fetchRows();
  }, [config, metric, selectedMonth, selectedYear]);

  useEffect(() => {
    if (metric !== 'attendance-records') {
      return undefined;
    }

    const intervalId = window.setInterval(() => {
      fetchRows(false);
    }, 5000);

    return () => {
      window.clearInterval(intervalId);
    };
  }, [metric, selectedGrade, selectedDate]);

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

  const handleMarkSalaryPaid = async (row) => {
    setError('');
    setSuccess('');

    try {
      const res = await axios.put(`/admin/dashboard/salary-details/${row.id}`, {
        month: selectedMonth,
        year: selectedYear,
        amount: row.amount,
        status: 'Paid',
      });
      setSuccess(res.data.data?.message || 'Salary marked as paid');
      fetchRows();
    } catch (err) {
      setError(err.response?.data?.error || 'Unable to update salary status');
    }
  };

  const handleMarkStudentPaymentPaid = async (row) => {
    setError('');
    setSuccess('');

    try {
      const res = await axios.put(`/admin/dashboard/student-payment-details/${row.id}`, {
        user_id: row.user_id,
        amount: row.amount,
        status: 'Paid',
      });
      setSuccess(res.data.data?.message || 'Payment marked as paid');
      fetchRows(false);
    } catch (err) {
      setError(err.response?.data?.error || 'Unable to update payment status');
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
            {config?.createLabel && !config?.readOnly && (
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

        {!error && (usesGradeFilter || usesSalaryMonthFilter) && rows.length > 0 && (
          <div className="filter-card">
            {usesGradeFilter && (
              <div className="form-group" style={{ minWidth: 220, marginBottom: 0 }}>
                <label htmlFor="dashboard-grade-filter">Filter By Grade</label>
                <select
                  id="dashboard-grade-filter"
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
            )}

            {usesSalaryMonthFilter && (
              <div className="form-group" style={{ minWidth: 220, marginBottom: 0 }}>
                <label htmlFor="dashboard-salary-month-filter">Month</label>
                <select
                  id="dashboard-salary-month-filter"
                  value={selectedMonth}
                  onChange={(e) => setSelectedMonth(e.target.value)}
                >
                  {availableMonthOptions.map((month) => (
                    <option key={month.value} value={month.value}>
                      {month.label}
                    </option>
                  ))}
                </select>
              </div>
            )}

            {usesSalaryMonthFilter && (
              <div className="form-group" style={{ minWidth: 180, marginBottom: 0 }}>
                <label htmlFor="dashboard-salary-year-filter">Year</label>
                <select
                  id="dashboard-salary-year-filter"
                  value={selectedYear}
                  onChange={(e) => setSelectedYear(e.target.value)}
                >
                  {yearOptions.map((year) => (
                    <option key={year} value={year}>
                      {year}
                    </option>
                  ))}
                </select>
              </div>
            )}

            {usesDateFilter && (
              <div className="form-group" style={{ minWidth: 220, marginBottom: 0 }}>
                <label htmlFor="dashboard-attendance-date-filter">Filter By Date</label>
                <input
                  id="dashboard-attendance-date-filter"
                  type="date"
                  value={selectedDate}
                  max={todayDate}
                  onChange={(e) => setSelectedDate(e.target.value)}
                />
              </div>
            )}

            <div className="form-group" style={{ minWidth: 180, marginBottom: 0 }}>
              <label>
                {metric === 'attendance-records'
                  ? 'Records Shown'
                  : metric === 'salary-details'
                    ? 'Tutors Shown'
                    : 'Students Shown'}
              </label>
              <input type="text" value={`${filteredRows.length}`} readOnly />
            </div>
          </div>
        )}

        {formMode && !config?.readOnly && (formMode === 'edit' || config?.createMode !== 'redirect') && renderDashboardModal(
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
        ) : !error && usesGradeFilter && filteredRows.length === 0 ? (
          <div className="dashboard-empty">
            {metric === 'attendance-records'
              ? 'No attendance records found for the selected grade.'
              : metric === 'student-payment-details'
                ? 'No student payment records found for the selected grade.'
                : 'No students found for the selected grade.'}
          </div>
        ) : (
          <div className="table-card">
            <table className="dashboard-table">
              <thead>
                <tr>
                  {(config?.columns || []).map((column) => (
                    <th key={column.key}>{column.label}</th>
                  ))}
                  {showsActionButtons && <th>Actions</th>}
                </tr>
              </thead>
              <tbody>
                {filteredRows.map((row, index) => (
                  <tr key={row.id || `${metric}-${index}`}>
                    {(config?.columns || []).map((column) => (
                      <td key={column.key} className={column.className || ''}>
                        {metric === 'student-payment-details' && column.key === 'status' ? (
                          String(row.status || '').toLowerCase() === 'paid' ? (
                            <button type="button" className="payment-status-button paid-status-button" disabled>
                              Paid
                            </button>
                          ) : (
                            <button
                              type="button"
                              className="payment-status-button pending-status-button"
                              onClick={() => handleMarkStudentPaymentPaid(row)}
                            >
                              Pending
                            </button>
                          )
                        ) : metric === 'salary-details' && column.key === 'status' ? (
                          String(row.status || '').toLowerCase() === 'paid' ? (
                            <button type="button" className="payment-status-button paid-status-button" disabled>
                              Paid
                            </button>
                          ) : (
                            <button
                              type="button"
                              className="payment-status-button pending-status-button"
                              onClick={() => handleMarkSalaryPaid(row)}
                            >
                              Pending
                            </button>
                          )
                        ) : column.badge ? (
                          <span className={`profile-badge ${String(row[column.key] || '').toLowerCase()}`}>
                            {row[column.key] || 'N/A'}
                          </span>
                        ) : column.link ? (
                          row[column.key] ? <a href={resolveReceiptUrl(row[column.key])} target="_blank" rel="noreferrer">View Receipt</a> : 'N/A'
                        ) : (
                          column.key === 'grade' ? (normalizeGrade(row.grade) || 'N/A') : formatValue(row, column)
                        )}
                      </td>
                    ))}
                    {showsActionButtons && (
                      <td className="action-row">
                        {metric === 'salary-details' ? (
                          String(row.status || '').toLowerCase() === 'paid' ? (
                            <button type="button" className="secondary-button" disabled>
                              Paid
                            </button>
                          ) : (
                            <button type="button" onClick={() => handleMarkSalaryPaid(row)}>
                              Paid
                            </button>
                          )
                        ) : (
                          <>
                            <button type="button" className="secondary-button" onClick={() => openEdit(row)}>
                              Edit
                            </button>
                            <button type="button" className="danger-button" onClick={() => handleDelete(row.id)}>
                              Delete
                            </button>
                          </>
                        )}
                      </td>
                    )}
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






