import { useEffect, useState } from 'react';
import axios from '../../api/axios';

const styles = {
  section: {
    display: 'grid',
    gap: '20px',
  },
  statsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
    gap: '16px',
  },
  card: {
    padding: '20px',
    borderRadius: '12px',
    background: 'linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(248,250,252,0.96) 100%)',
    border: '1px solid #dbe7f2',
    boxShadow: '0 12px 24px rgba(15, 23, 42, 0.05)',
  },
  cardLabel: {
    display: 'block',
    marginBottom: '10px',
    color: '#64748b',
    fontSize: '0.84rem',
    fontWeight: 700,
    textTransform: 'uppercase',
    letterSpacing: '0.06em',
  },
  cardValue: {
    margin: 0,
    color: '#0f172a',
    fontSize: '1.9rem',
    fontWeight: 800,
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
  },
  badge: {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    minWidth: '84px',
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
    animation: 'studentAttendanceSpin 0.9s linear infinite',
  },
};

const statusTone = (status) => {
  const normalized = String(status || '').toLowerCase();
  if (normalized === 'present' || normalized === 'excused') {
    return { color: '#166534', background: '#dcfce7' };
  }
  return { color: '#991b1b', background: '#fee2e2' };
};

const StudentAttendancePanel = ({ studentId }) => {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!studentId) {
      setLoading(false);
      setError('Attendance is not available yet.');
      return;
    }

    const fetchAttendance = async () => {
      try {
        setLoading(true);
        const res = await axios.get(`/api/student_attendance.php?student_id=${studentId}`);
        setData(res.data || null);
        setError('');
      } catch (err) {
        setError(err.response?.data?.error || 'Failed to load attendance');
      } finally {
        setLoading(false);
      }
    };

    fetchAttendance();
  }, [studentId]);

  if (loading) {
    return (
      <div style={styles.loadingWrap}>
        <style>
          {`
            @keyframes studentAttendanceSpin {
              from { transform: rotate(0deg); }
              to { transform: rotate(360deg); }
            }
          `}
        </style>
        <div style={styles.spinner} />
        <div style={{ color: '#475569', fontWeight: 600 }}>Loading attendance...</div>
      </div>
    );
  }

  if (error) {
    return <div style={styles.error}>{error}</div>;
  }

  const stats = [
    { label: 'Total Classes Held', value: data?.total_classes_held ?? 0 },
    { label: 'Total Present', value: data?.total_present ?? 0 },
    { label: 'Total Absent', value: data?.total_absent ?? 0 },
    { label: 'Attendance Percentage', value: `${data?.attendance_percentage ?? 0}%` },
  ];

  return (
    <div style={styles.section}>
      <div style={styles.statsGrid}>
        {stats.map((stat) => (
          <div key={stat.label} style={styles.card}>
            <span style={styles.cardLabel}>{stat.label}</span>
            <p style={styles.cardValue}>{stat.value}</p>
          </div>
        ))}
      </div>

      <div style={styles.tableWrap}>
        <div style={styles.tableHeader}>
          <h3 style={styles.tableTitle}>Attendance History</h3>
        </div>
        {Array.isArray(data?.history) && data.history.length > 0 ? (
          <div style={{ overflowX: 'auto' }}>
            <table style={styles.table}>
              <thead>
                <tr>
                  <th style={styles.th}>Date</th>
                  <th style={styles.th}>Subject</th>
                  <th style={styles.th}>Status</th>
                </tr>
              </thead>
              <tbody>
                {data.history.map((row, index) => {
                  const tone = statusTone(row.status);
                  return (
                    <tr key={`${row.date}-${row.subject}-${index}`}>
                      <td style={styles.td}>{row.date}</td>
                      <td style={styles.td}>{row.subject}</td>
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
            <div style={styles.empty}>No attendance records available yet.</div>
          </div>
        )}
      </div>
    </div>
  );
};

export default StudentAttendancePanel;
