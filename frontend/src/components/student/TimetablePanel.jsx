import { useEffect, useMemo, useState } from 'react';
import axios from '../../api/axios';

const styles = {
  section: {
    display: 'grid',
    gap: '20px',
  },
  summaryRow: {
    display: 'flex',
    flexWrap: 'wrap',
    gap: '16px',
    alignItems: 'stretch',
  },
  summaryCard: {
    padding: '20px',
    borderRadius: '12px',
    background: 'linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(248,250,252,0.96) 100%)',
    border: '1px solid #dbe7f2',
    boxShadow: '0 12px 24px rgba(15, 23, 42, 0.05)',
    maxWidth: '340px',
  },
  summaryLabel: {
    display: 'block',
    marginBottom: '10px',
    color: '#64748b',
    fontSize: '0.84rem',
    fontWeight: 700,
    textTransform: 'uppercase',
    letterSpacing: '0.06em',
  },
  summaryValue: {
    margin: 0,
    color: '#0f172a',
    fontSize: '1.7rem',
    fontWeight: 800,
  },
  refreshCard: {
    padding: '20px',
    borderRadius: '12px',
    background: 'linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(248,250,252,0.96) 100%)',
    border: '1px solid #dbe7f2',
    boxShadow: '0 12px 24px rgba(15, 23, 42, 0.05)',
    minWidth: '220px',
  },
  refreshStatus: {
    margin: 0,
    color: '#0f172a',
    fontSize: '1rem',
    fontWeight: 700,
  },
  refreshMeta: {
    marginTop: '8px',
    color: '#64748b',
    fontSize: '0.92rem',
    fontWeight: 600,
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
  gridTable: {
    width: '100%',
    minWidth: '980px',
    borderCollapse: 'collapse',
  },
  th: {
    padding: '14px 16px',
    textAlign: 'left',
    background: '#e2e8f0',
    color: '#334155',
    fontWeight: 800,
    fontSize: '0.9rem',
    verticalAlign: 'top',
  },
  td: {
    padding: '12px 14px',
    borderBottom: '1px solid #e2e8f0',
    borderRight: '1px solid #e2e8f0',
    verticalAlign: 'top',
    background: '#ffffff',
  },
  timeCell: {
    minWidth: '140px',
    fontWeight: 800,
    color: '#0f172a',
    background: '#f8fafc',
  },
  classCard: {
    minHeight: '110px',
    display: 'grid',
    gap: '6px',
    padding: '12px',
    borderRadius: '12px',
    background: 'linear-gradient(135deg, rgba(37, 99, 235, 0.08) 0%, rgba(219, 234, 254, 0.5) 100%)',
    border: '1px solid rgba(147, 197, 253, 0.55)',
  },
  subject: {
    color: '#0f172a',
    fontWeight: 800,
  },
  teacher: {
    color: '#1d4ed8',
    fontWeight: 700,
  },
  meta: {
    color: '#64748b',
    fontSize: '0.9rem',
  },
  emptyCell: {
    minHeight: '110px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: '12px',
    background: '#f8fafc',
    border: '1px dashed #cbd5e1',
    color: '#94a3b8',
    fontWeight: 700,
  },
  unavailableCell: {
    minHeight: '110px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: '12px',
    background: '#fee2e2',
    border: '1px solid #fca5a5',
    color: '#b91c1c',
    fontWeight: 700,
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
    minHeight: '240px',
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
    animation: 'studentTimetableSpin 0.9s linear infinite',
  },
};

const StudentTimetablePanel = ({ studentId }) => {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');
  const [lastUpdated, setLastUpdated] = useState(null);

  useEffect(() => {
    if (!studentId) {
      setLoading(false);
      setError('Timetable is not available yet.');
      return;
    }

    let isMounted = true;

    const fetchTimetable = async ({ silent = false } = {}) => {
      try {
        if (silent) {
          setRefreshing(true);
        } else {
          setLoading(true);
        }

        const res = await axios.get(`/api/student_timetable.php?student_id=${studentId}`);
        if (!isMounted) {
          return;
        }

        setData(res.data || null);
        setError('');
        setLastUpdated(new Date());
      } catch (err) {
        if (!isMounted) {
          return;
        }

        setError(err.response?.data?.error || 'Failed to load timetable');
      } finally {
        if (!isMounted) {
          return;
        }

        if (silent) {
          setRefreshing(false);
        } else {
          setLoading(false);
        }
      }
    };

    fetchTimetable();
    const intervalId = window.setInterval(() => {
      fetchTimetable({ silent: true });
    }, 30000);

    return () => {
      isMounted = false;
      window.clearInterval(intervalId);
    };
  }, [studentId]);

  const gridMap = useMemo(() => {
    const map = {};
    if (Array.isArray(data?.entries)) {
      data.entries.forEach((entry) => {
        map[`${entry.day}__${entry.time}`] = entry;
      });
    }
    return map;
  }, [data]);

  if (loading) {
    return (
      <div style={styles.loadingWrap}>
        <style>
          {`
            @keyframes studentTimetableSpin {
              from { transform: rotate(0deg); }
              to { transform: rotate(360deg); }
            }
          `}
        </style>
        <div style={styles.spinner} />
        <div style={{ color: '#475569', fontWeight: 600 }}>Loading timetable...</div>
      </div>
    );
  }

  if (error) {
    return <div style={styles.error}>{error}</div>;
  }

  if (!data || !Array.isArray(data.days) || !Array.isArray(data.time_slots)) {
    return <div style={styles.empty}>Timetable is not available.</div>;
  }

  return (
    <div style={styles.section}>
      <div style={styles.summaryRow}>
        <div style={styles.summaryCard}>
          <span style={styles.summaryLabel}>Current Grade</span>
          <p style={styles.summaryValue}>{data.grade || 'N/A'}</p>
        </div>

        <div style={styles.refreshCard}>
          <span style={styles.summaryLabel}>Timetable Status</span>
          <p style={styles.refreshStatus}>{refreshing ? 'Refreshing...' : 'Auto-updating'}</p>
          <div style={styles.refreshMeta}>
            {lastUpdated ? `Last updated: ${lastUpdated.toLocaleTimeString()}` : 'Waiting for first sync'}
          </div>
        </div>
      </div>

      <div style={styles.tableWrap}>
        <div style={styles.tableHeader}>
          <h3 style={styles.tableTitle}>Weekly Timetable</h3>
        </div>
        <div style={{ overflowX: 'auto' }}>
          <table style={styles.gridTable}>
            <thead>
              <tr>
                <th style={styles.th}>Time</th>
                {data.days.map((day) => (
                  <th key={day} style={styles.th}>{day}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {data.time_slots.map((time) => (
                <tr key={time}>
                  <td style={{ ...styles.td, ...styles.timeCell }}>{time}</td>
                  {data.days.map((day) => {
                    const key = `${day}__${time}`;
                    const entry = gridMap[key];
                    const isUnavailable = Array.isArray(data.day_time_slots?.[day]) && !data.day_time_slots[day].includes(time);

                    return (
                      <td key={key} style={styles.td}>
                        {entry ? (
                          <div style={styles.classCard}>
                            <div style={styles.subject}>{entry.subject}</div>
                            <div style={styles.teacher}>{entry.teacher}</div>
                            <div style={styles.meta}>{entry.room}</div>
                          </div>
                        ) : isUnavailable ? (
                          <div style={styles.unavailableCell}>Not available</div>
                        ) : (
                          <div style={styles.emptyCell}>No class</div>
                        )}
                      </td>
                    );
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};

export default StudentTimetablePanel;
