import { useEffect, useState } from 'react';
import axios from '../../api/axios';

const styles = {
  section: {
    display: 'grid',
    gap: '20px',
  },
  tableWrap: {
    borderRadius: '12px',
    overflow: 'hidden',
    border: '1px solid #dbe7f2',
    background: '#ffffff',
    boxShadow: '0 12px 24px rgba(15, 23, 42, 0.05)',
  },
  tableHeader: {
    padding: '18px 20px',
    borderBottom: '1px solid #e2e8f0',
    background: '#f8fbff',
  },
  tableTitle: {
    margin: 0,
    color: '#0f172a',
    fontSize: '1.05rem',
    fontWeight: 800,
  },
  table: {
    width: '100%',
    borderCollapse: 'collapse',
  },
  th: {
    padding: '14px 18px',
    textAlign: 'left',
    background: '#e2e8f0',
    color: '#334155',
    fontWeight: 800,
    fontSize: '0.9rem',
  },
  td: {
    padding: '14px 18px',
    borderBottom: '1px solid #e2e8f0',
    color: '#334155',
    verticalAlign: 'middle',
  },
  badge: {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    minWidth: '92px',
    padding: '8px 12px',
    borderRadius: '999px',
    fontWeight: 800,
    fontSize: '0.82rem',
    textTransform: 'capitalize',
  },
  empty: {
    padding: '18px',
    borderRadius: '12px',
    border: '1px dashed #cbd5e1',
    background: '#f8fafc',
    color: '#64748b',
    textAlign: 'center',
    fontWeight: 600,
  },
  error: {
    padding: '14px 16px',
    borderRadius: '12px',
    background: '#fff7ed',
    color: '#c2410c',
    border: '1px solid #fed7aa',
  },
  success: {
    padding: '14px 16px',
    borderRadius: '12px',
    background: '#ecfdf5',
    color: '#166534',
    border: '1px solid #bbf7d0',
  },
  loadingWrap: {
    minHeight: '220px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'column',
    gap: '14px',
  },
  spinner: {
    width: '40px',
    height: '40px',
    borderRadius: '999px',
    border: '4px solid rgba(37, 99, 235, 0.14)',
    borderTopColor: '#2563eb',
    animation: 'studentPaymentsSpin 0.9s linear infinite',
  },
  uploadButton: {
    minHeight: '38px',
    borderRadius: '10px',
    border: '1px solid #2563eb',
    background: '#eff6ff',
    color: '#1d4ed8',
    fontWeight: 700,
    padding: '0 14px',
    cursor: 'pointer',
  },
  link: {
    color: '#2563eb',
    fontWeight: 700,
    textDecoration: 'none',
  },
  helper: {
    marginTop: '6px',
    color: '#64748b',
    fontSize: '0.82rem',
    fontWeight: 600,
  },
};

const currency = (value) => `LKR ${Number(value || 0).toLocaleString('en-LK')}`;

const statusTone = (status) => {
  const normalized = String(status || '').toLowerCase();
  if (normalized === 'paid') {
    return { color: '#166534', background: '#dcfce7' };
  }
  if (normalized === 'pending') {
    return { color: '#92400e', background: '#fef3c7' };
  }
  return { color: '#991b1b', background: '#fee2e2' };
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

const StudentPaymentsPanel = ({ studentId }) => {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [uploadingMonth, setUploadingMonth] = useState('');
  const resolvedStudentId = String(studentId ?? '').trim();
  const hasValidStudentId = /^\d+$/.test(resolvedStudentId);

  const fetchPayments = async () => {
    try {
      setLoading(true);
      const res = await axios.get(`/api/student_payments.php?student_id=${resolvedStudentId}`);
      setData(res.data || null);
      setError('');
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to load fee and payment details');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (!hasValidStudentId) {
      setLoading(false);
      setError('Fee & Payment is not available yet.');
      return;
    }

    fetchPayments();
  }, [hasValidStudentId, resolvedStudentId, studentId]);

  const handleReceiptUpload = async (month, file) => {
    if (!file) {
      return;
    }

    try {
      setUploadingMonth(month);
      setError('');
      setSuccess('');
      const formData = new FormData();
      formData.append('student_id', resolvedStudentId);
      formData.append('month', month);
      formData.append('receipt', file);

      const response = await axios.post('/api/student_payments.php', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      setData((current) => ({
        ...(current || {}),
        history: response.data?.history || current?.history || [],
      }));
      setSuccess(response.data?.message || 'Payment receipt uploaded successfully.');
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to upload payment receipt.');
    } finally {
      setUploadingMonth('');
    }
  };

  if (loading) {
    return (
      <div style={styles.loadingWrap}>
        <style>
          {`
            @keyframes studentPaymentsSpin {
              from { transform: rotate(0deg); }
              to { transform: rotate(360deg); }
            }
          `}
        </style>
        <div style={styles.spinner} />
        <div style={{ color: '#475569', fontWeight: 600 }}>Loading fee and payment details...</div>
      </div>
    );
  }

  if (error && !data?.history?.length) {
    return <div style={styles.error}>{error}</div>;
  }

  return (
    <div style={styles.section}>
      {error ? <div style={styles.error}>{error}</div> : null}
      {success ? <div style={styles.success}>{success}</div> : null}

      <div style={styles.tableWrap}>
        <div style={styles.tableHeader}>
          <h3 style={styles.tableTitle}>Fee & Payment</h3>
        </div>
        {Array.isArray(data?.history) && data.history.length > 0 ? (
          <div style={{ overflowX: 'auto' }}>
            <table style={styles.table}>
              <thead>
                <tr>
                  <th style={styles.th}>Month</th>
                  <th style={styles.th}>Payment Amount</th>
                  <th style={styles.th}>Update Payment Receipt</th>
                  <th style={styles.th}>Status</th>
                </tr>
              </thead>
              <tbody>
                {data.history.map((row, index) => {
                  const tone = statusTone(row.status);
                  const inputId = `receipt-${row.raw_month || row.month}`;
                  return (
                    <tr key={`${row.month}-${index}`}>
                      <td style={styles.td}>{row.month}</td>
                      <td style={styles.td}>{currency(row.amount)}</td>
                      <td style={styles.td}>
                        <div style={{ display: 'grid', gap: '8px' }}>
                          <label htmlFor={inputId} style={styles.uploadButton}>
                            {uploadingMonth === (row.raw_month || row.month) ? 'Uploading...' : 'Upload Receipt'}
                          </label>
                          <input
                            id={inputId}
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            style={{ display: 'none' }}
                            disabled={uploadingMonth !== ''}
                            onChange={(event) => {
                              const file = event.target.files?.[0];
                              handleReceiptUpload(row.raw_month || row.month, file);
                              event.target.value = '';
                            }}
                          />
                          {row.receipt_path ? (
                            <a href={resolveReceiptUrl(row.receipt_path)} target="_blank" rel="noreferrer" style={styles.link}>
                              View Uploaded Receipt
                            </a>
                          ) : (
                            <span style={styles.helper}>No receipt uploaded yet</span>
                          )}
                        </div>
                      </td>
                      <td style={styles.td}>
                        <span style={{ ...styles.badge, color: tone.color, background: tone.background }}>
                          {row.status}
                        </span>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        ) : (
          <div style={{ padding: '20px' }}>
            <div style={styles.empty}>No fee records available yet.</div>
          </div>
        )}
      </div>
    </div>
  );
};

export default StudentPaymentsPanel;
