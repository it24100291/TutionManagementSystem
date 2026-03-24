import { useEffect, useMemo, useState } from 'react';
import axios from '../../api/axios';

const styles = {
  section: {
    padding: '20px',
    borderRadius: '18px',
    border: '1px solid #dbe7f2',
    background: 'linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(248,250,252,0.96) 100%)',
    boxShadow: '0 10px 24px rgba(15, 23, 42, 0.05)',
    height: '100%',
  },
  header: {
    marginBottom: '18px',
  },
  title: {
    margin: 0,
    fontSize: '1.35rem',
    fontWeight: 800,
    color: '#0f172a',
  },
  subtitle: {
    margin: '6px 0 0',
    color: '#64748b',
  },
  groupTitle: {
    margin: '0 0 12px',
    fontSize: '1rem',
    fontWeight: 800,
    color: '#0f172a',
  },
  list: {
    display: 'grid',
    gap: '12px',
  },
  row: {
    padding: '14px 16px',
    borderRadius: '14px',
    border: '1px solid #dbe7f2',
    background: '#f8fbff',
    boxShadow: '0 8px 18px rgba(15, 23, 42, 0.04)',
  },
  rowTop: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    gap: '12px',
    marginBottom: '8px',
    flexWrap: 'wrap',
  },
  studentName: {
    fontWeight: 800,
    color: '#0f172a',
    fontSize: '1rem',
  },
  classMeta: {
    marginTop: '4px',
    color: '#64748b',
    fontSize: '0.9rem',
    fontWeight: 600,
  },
  dateText: {
    color: '#334155',
    fontWeight: 700,
    fontSize: '0.9rem',
  },
  reason: {
    color: '#475569',
    fontSize: '0.92rem',
    marginBottom: '12px',
  },
  actions: {
    display: 'flex',
    gap: '10px',
    flexWrap: 'wrap',
  },
  approveButton: {
    border: 'none',
    borderRadius: '10px',
    padding: '10px 14px',
    background: '#22c55e',
    color: '#fff',
    fontWeight: 800,
    cursor: 'pointer',
  },
  denyButton: {
    border: 'none',
    borderRadius: '10px',
    padding: '10px 14px',
    background: '#ef4444',
    color: '#fff',
    fontWeight: 800,
    cursor: 'pointer',
  },
  badge: {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    minWidth: '90px',
    padding: '8px 12px',
    borderRadius: '999px',
    fontWeight: 800,
    fontSize: '0.82rem',
    textTransform: 'capitalize',
  },
  empty: {
    padding: '18px',
    borderRadius: '14px',
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
    marginBottom: '14px',
  },
  success: {
    padding: '14px 16px',
    borderRadius: '12px',
    background: '#ecfdf5',
    color: '#166534',
    border: '1px solid #bbf7d0',
    marginBottom: '14px',
  },
  skeleton: {
    height: '120px',
    borderRadius: '14px',
    background: 'linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%)',
    backgroundSize: '200% 100%',
    animation: 'leaveRequestsPulse 1.4s ease infinite',
  },
  sectionSpacing: {
    marginTop: '20px',
  },
};

const truncate = (value, length = 60) => {
  const text = String(value || '').trim();
  if (text.length <= length) return text;
  return `${text.slice(0, length).trimEnd()}...`;
};

const badgeTone = (status) => {
  const normalized = String(status || '').toLowerCase();
  if (normalized === 'approved') {
    return { color: '#166534', background: '#dcfce7' };
  }
  if (normalized === 'denied') {
    return { color: '#991b1b', background: '#fee2e2' };
  }
  return { color: '#1d4ed8', background: '#dbeafe' };
};

const LoadingState = () => (
  <div style={styles.list}>
    {[1, 2].map((item) => (
      <div key={item} style={styles.skeleton} />
    ))}
  </div>
);

const LeaveRequests = ({ tutorId }) => {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [processingId, setProcessingId] = useState(null);

  useEffect(() => {
    if (!tutorId) {
      setLoading(false);
      setError('Leave requests are not available yet.');
      return;
    }

    const fetchRequests = async () => {
      try {
        setLoading(true);
        const res = await axios.get(`/api/tutor/leave-requests.php?tutor_id=${tutorId}`);
        setRows(Array.isArray(res.data) ? res.data : []);
        setError('');
      } catch (err) {
        setError(err.response?.data?.error || 'Failed to load leave requests');
      } finally {
        setLoading(false);
      }
    };

    fetchRequests();
  }, [tutorId]);

  const pending = useMemo(
    () => rows.filter((row) => String(row.status || '').toLowerCase() === 'pending'),
    [rows]
  );

  const resolved = useMemo(
    () =>
      rows
        .filter((row) => ['approved', 'denied'].includes(String(row.status || '').toLowerCase()))
        .slice(0, 5),
    [rows]
  );

  const updateLeaveStatus = async (leaveId, status) => {
    let denyReason = '';
    if (status === 'denied') {
      denyReason = window.prompt('Enter a reason for denying this request (optional):', '') || '';
    }

    try {
      setProcessingId(leaveId);
      setError('');
      setSuccess('');

      await axios.post('/api/tutor/update-leave.php', {
        tutor_id: tutorId,
        leave_id: leaveId,
        status,
        deny_reason: denyReason,
      });

      setRows((current) =>
        current.map((row) =>
          row.id === leaveId
            ? {
                ...row,
                status,
                deny_reason: denyReason,
              }
            : row
        )
      );
      setSuccess(`Leave request ${status} successfully.`);
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to update leave request');
    } finally {
      setProcessingId(null);
    }
  };

  return (
    <section style={styles.section}>
      <style>
        {`
          @keyframes leaveRequestsPulse {
            0% { background-position: 100% 0; }
            100% { background-position: -100% 0; }
          }
        `}
      </style>
      <div style={styles.header}>
        <h3 style={styles.title}>Leave Requests</h3>
        <p style={styles.subtitle}>Review parent-submitted absence requests and record your decision.</p>
      </div>

      {error && <div style={styles.error}>{error}</div>}
      {success && <div style={styles.success}>{success}</div>}

      <div>
        <h4 style={styles.groupTitle}>Pending requests</h4>
        {loading ? (
          <LoadingState />
        ) : pending.length === 0 ? (
          <div style={styles.empty}>No pending leave requests right now.</div>
        ) : (
          <div style={styles.list}>
            {pending.map((row) => (
              <div key={row.id} style={styles.row}>
                <div style={styles.rowTop}>
                  <div>
                    <div style={styles.studentName}>{row.student_name}</div>
                    <div style={styles.classMeta}>{row.class_name}</div>
                  </div>
                  <div style={styles.dateText}>{row.absence_date}</div>
                </div>
                <div style={styles.reason}>{truncate(row.reason, 60)}</div>
                <div style={styles.actions}>
                  <button
                    type="button"
                    style={styles.approveButton}
                    onClick={() => updateLeaveStatus(row.id, 'approved')}
                    disabled={processingId === row.id}
                  >
                    Approve
                  </button>
                  <button
                    type="button"
                    style={styles.denyButton}
                    onClick={() => updateLeaveStatus(row.id, 'denied')}
                    disabled={processingId === row.id}
                  >
                    Deny
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      <div style={styles.sectionSpacing}>
        <h4 style={styles.groupTitle}>Recent resolved</h4>
        {loading ? (
          <LoadingState />
        ) : resolved.length === 0 ? (
          <div style={styles.empty}>No resolved requests yet.</div>
        ) : (
          <div style={styles.list}>
            {resolved.map((row) => {
              const tone = badgeTone(row.status);
              return (
                <div key={row.id} style={styles.row}>
                  <div style={styles.rowTop}>
                    <div>
                      <div style={styles.studentName}>{row.student_name}</div>
                      <div style={styles.classMeta}>{row.class_name}</div>
                    </div>
                    <span style={{ ...styles.badge, color: tone.color, background: tone.background }}>
                      {row.status}
                    </span>
                  </div>
                  <div style={styles.reason}>{truncate(row.reason, 60)}</div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </section>
  );
};

export default LeaveRequests;
