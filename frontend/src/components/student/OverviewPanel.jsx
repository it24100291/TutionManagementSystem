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
    transition: 'transform 0.25s ease, box-shadow 0.25s ease',
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
  subCardGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))',
    gap: '18px',
  },
  panel: {
    padding: '20px',
    borderRadius: '12px',
    background: 'linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(248,250,252,0.96) 100%)',
    border: '1px solid #dbe7f2',
    boxShadow: '0 12px 24px rgba(15, 23, 42, 0.05)',
  },
  panelTitle: {
    margin: '0 0 14px',
    color: '#0f172a',
    fontSize: '1.1rem',
    fontWeight: 800,
  },
  list: {
    display: 'grid',
    gap: '12px',
  },
  listRow: {
    padding: '14px 16px',
    borderRadius: '12px',
    background: '#f8fbff',
    border: '1px solid #dbe7f2',
  },
  listTitle: {
    fontWeight: 700,
    color: '#0f172a',
    marginBottom: '4px',
  },
  listMeta: {
    color: '#64748b',
    fontSize: '0.92rem',
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
    animation: 'studentOverviewSpin 0.9s linear infinite',
  },
};

const StudentOverviewPanel = ({ studentId }) => {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!studentId) {
      setLoading(false);
      setError('Overview is not available yet.');
      return;
    }

    const fetchOverview = async () => {
      try {
        setLoading(true);
        const res = await axios.get(`/api/student_overview.php?student_id=${studentId}`);
        setData(res.data || null);
        setError('');
      } catch (err) {
        setError(err.response?.data?.error || 'Failed to load overview');
      } finally {
        setLoading(false);
      }
    };

    fetchOverview();
  }, [studentId]);

  if (loading) {
    return (
      <div style={styles.loadingWrap}>
        <style>
          {`
            @keyframes studentOverviewSpin {
              from { transform: rotate(0deg); }
              to { transform: rotate(360deg); }
            }
          `}
        </style>
        <div style={styles.spinner} />
        <div style={{ color: '#475569', fontWeight: 600 }}>Loading overview...</div>
      </div>
    );
  }

  if (error) {
    return <div style={styles.error}>{error}</div>;
  }

  const stats = [
    { label: 'Total Enrolled Classes', value: data?.total_enrolled_classes ?? 0 },
    { label: 'Attendance Percentage', value: `${data?.attendance_percentage ?? 0}%` },
    { label: 'Pending Payments Count', value: data?.pending_payments_count ?? 0 },
    { label: 'Upcoming Exams Count', value: data?.upcoming_exams_count ?? 0 },
  ];

  return (
    <div style={styles.section}>
      <div style={styles.statsGrid}>
        {stats.map((stat) => (
          <div
            key={stat.label}
            style={styles.card}
            onMouseEnter={(e) => {
              e.currentTarget.style.transform = 'translateY(-3px)';
              e.currentTarget.style.boxShadow = '0 18px 30px rgba(15, 23, 42, 0.08)';
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.transform = 'translateY(0)';
              e.currentTarget.style.boxShadow = '0 12px 24px rgba(15, 23, 42, 0.05)';
            }}
          >
            <span style={styles.cardLabel}>{stat.label}</span>
            <p style={styles.cardValue}>{stat.value}</p>
          </div>
        ))}
      </div>

      <div style={styles.subCardGrid}>
        <section style={styles.panel}>
          <h3 style={styles.panelTitle}>Today&apos;s Classes</h3>
          {Array.isArray(data?.todays_classes) && data.todays_classes.length > 0 ? (
            <div style={styles.list}>
              {data.todays_classes.map((item, index) => (
                <div key={`${item.class_name}-${item.start_time}-${index}`} style={styles.listRow}>
                  <div style={styles.listTitle}>{item.class_name}</div>
                  <div style={styles.listMeta}>
                    {item.grade ? `${item.grade} · ` : ''}{item.start_time || 'Time not set'}{item.room ? ` · ${item.room}` : ''}
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div style={styles.empty}>No classes scheduled for today.</div>
          )}
        </section>

        <section style={styles.panel}>
          <h3 style={styles.panelTitle}>Latest Announcements</h3>
          {Array.isArray(data?.announcements) && data.announcements.length > 0 ? (
            <div style={styles.list}>
              {data.announcements.map((item, index) => (
                <div key={`${item.title}-${index}`} style={styles.listRow}>
                  <div style={styles.listTitle}>{item.title}</div>
                  <div style={styles.listMeta}>
                    {item.created_at ? `${item.created_at} · ` : ''}{item.message}
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div style={styles.empty}>No announcements available.</div>
          )}
        </section>
      </div>
    </div>
  );
};

export default StudentOverviewPanel;
