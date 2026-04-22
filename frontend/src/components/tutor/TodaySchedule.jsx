import { useEffect, useMemo, useState } from 'react';
import axios from '../../api/axios';

const styles = {
  section: {
    marginBottom: '20px',
    padding: '22px',
    borderRadius: '18px',
    border: '1px solid #dbe7f2',
    background: 'linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(248,250,252,0.96) 100%)',
    boxShadow: '0 10px 24px rgba(15, 23, 42, 0.05)',
  },
  header: {
    marginBottom: '18px',
  },
  title: {
    margin: 0,
    fontSize: '1.35rem',
    fontWeight: 800,
    color: '#2563eb',
  },
  subtitle: {
    margin: '6px 0 0',
    color: '#64748b',
  },
  list: {
    display: 'grid',
    gap: '12px',
  },
  row: {
    display: 'grid',
    gridTemplateColumns: '180px 1fr 180px',
    gap: '14px',
    alignItems: 'center',
    padding: '16px',
    borderRadius: '14px',
    border: '1px solid #dbe7f2',
    background: '#f8fbff',
    boxShadow: '0 8px 18px rgba(15, 23, 42, 0.04)',
    transition: 'all 0.3s ease',
  },
  rowHover: {
    borderColor: '#bfdbfe',
    boxShadow: '0 12px 22px rgba(37, 99, 235, 0.08)',
    transform: 'translateY(-1px)',
  },
  timeMain: {
    fontWeight: 800,
    color: '#0f172a',
    fontSize: '1rem',
  },
  infoTitle: {
    fontWeight: 800,
    color: '#0f172a',
    fontSize: '1rem',
  },
  infoMeta: {
    marginTop: '4px',
    color: '#64748b',
    fontSize: '0.92rem',
    fontWeight: 600,
  },
  roomBlock: {
    justifySelf: 'end',
    textAlign: 'right',
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
  },
  skeletonRow: {
    height: '86px',
    borderRadius: '14px',
    background: 'linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%)',
    backgroundSize: '200% 100%',
    animation: 'todaySchedulePulse 1.4s ease infinite',
  },
};

const LoadingState = () => (
  <div style={styles.list}>
    {[1, 2, 3].map((item) => (
      <div key={item} style={styles.skeletonRow} />
    ))}
  </div>
);

const TodaySchedule = ({ tutorId }) => {
  const [rows, setRows] = useState([]);
  const [today, setToday] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [hoveredId, setHoveredId] = useState(null);

  const orderedRows = useMemo(() => [...rows], [rows]);

  useEffect(() => {
    if (!tutorId) {
      setLoading(false);
      setError('Tutor schedule is not available yet.');
      return;
    }

    const fetchSchedule = async () => {
      try {
        setLoading(true);
        const res = await axios.get(`/api/tutor/today-schedule.php?tutor_id=${tutorId}`);
        const data = res.data || {};
        setToday(data.day || '');
        setRows(Array.isArray(data.rows) ? data.rows : []);
        setError('');
      } catch (err) {
        setError(err.response?.data?.error || 'Failed to load today schedule');
      } finally {
        setLoading(false);
      }
    };

    fetchSchedule();
  }, [tutorId]);

  return (
    <section style={styles.section}>
      <style>
        {`
          @keyframes todaySchedulePulse {
            0% { background-position: 100% 0; }
            100% { background-position: -100% 0; }
          }
          @media (max-width: 900px) {
            .today-schedule-row {
              grid-template-columns: 1fr !important;
            }
            .today-schedule-room {
              justify-self: start !important;
              text-align: left !important;
            }
          }
        `}
      </style>

      <div style={styles.header}>
        <h3 style={styles.title}>{`Today's Schedule${today ? ` – ${today}` : ''}`}</h3>
        <p style={styles.subtitle}>Review only your scheduled classes for today from the central timetable.</p>
      </div>

      {loading ? (
        <LoadingState />
      ) : error ? (
        <div style={styles.error}>{error}</div>
      ) : orderedRows.length === 0 ? (
        <div style={styles.empty}>No classes scheduled for today.</div>
      ) : (
        <div style={styles.list}>
          {orderedRows.map((row) => (
            <div
              key={row.id}
              className="today-schedule-row"
              style={{ ...styles.row, ...(hoveredId === row.id ? styles.rowHover : {}) }}
              onMouseEnter={() => setHoveredId(row.id)}
              onMouseLeave={() => setHoveredId(null)}
            >
              <div>
                <div style={styles.timeMain}>{row.time_slot}</div>
                <div style={styles.infoMeta}>{row.day}</div>
              </div>
              <div>
                <div style={styles.infoTitle}>{row.subject}</div>
                <div style={styles.infoMeta}>{row.grade}</div>
              </div>
              <div className="today-schedule-room" style={styles.roomBlock}>
                <div style={styles.infoTitle}>{row.room || 'N/A'}</div>
                <div style={styles.infoMeta}>Room</div>
              </div>
            </div>
          ))}
        </div>
      )}
    </section>
  );
};

export default TodaySchedule;
