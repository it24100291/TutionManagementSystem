import { useEffect, useMemo, useState } from 'react';
import axios from '../../api/axios';

const DAY_ORDER = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

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
  toolbar: {
    display: 'grid',
    gridTemplateColumns: 'minmax(220px, 280px)',
    gap: '16px',
    marginBottom: '18px',
  },
  fieldBlock: {
    display: 'grid',
    gap: '8px',
  },
  fieldLabel: {
    fontSize: '0.9rem',
    fontWeight: 800,
    color: '#475569',
  },
  select: {
    width: '100%',
    borderRadius: '12px',
    border: '1px solid #cbd5e1',
    padding: '12px 14px',
    fontSize: '0.98rem',
    fontWeight: 600,
    color: '#0f172a',
    background: '#ffffff',
    outline: 'none',
    boxShadow: '0 6px 14px rgba(15, 23, 42, 0.04)',
  },
  dayList: {
    display: 'grid',
    gap: '16px',
  },
  dayCard: {
    border: '1px solid #dbe7f2',
    borderRadius: '16px',
    background: '#f8fbff',
    overflow: 'hidden',
    boxShadow: '0 10px 22px rgba(15, 23, 42, 0.04)',
  },
  dayHeader: {
    padding: '14px 16px',
    borderBottom: '1px solid #dbe7f2',
    background: 'linear-gradient(90deg, rgba(37,99,235,0.08) 0%, rgba(255,255,255,0.95) 100%)',
    color: '#1d4ed8',
    fontWeight: 800,
    fontSize: '1rem',
  },
  classList: {
    display: 'grid',
    gap: '0',
  },
  row: {
    display: 'grid',
    gridTemplateColumns: '170px 1fr 150px',
    gap: '14px',
    alignItems: 'center',
    padding: '16px',
    borderTop: '1px solid #e2e8f0',
    transition: 'background 0.3s ease',
  },
  rowHover: {
    background: '#eff6ff',
  },
  time: {
    fontWeight: 800,
    color: '#0f172a',
  },
  subject: {
    fontWeight: 800,
    color: '#0f172a',
  },
  grade: {
    marginTop: '4px',
    color: '#64748b',
    fontWeight: 600,
  },
  roomBlock: {
    justifySelf: 'end',
    textAlign: 'right',
  },
  roomLabel: {
    color: '#64748b',
    fontSize: '0.88rem',
    fontWeight: 600,
  },
  roomValue: {
    color: '#0f172a',
    fontWeight: 800,
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
  skeletonCard: {
    height: '108px',
    borderRadius: '16px',
    background: 'linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%)',
    backgroundSize: '200% 100%',
    animation: 'myClassesPulse 1.4s ease infinite',
  },
};

const LoadingState = () => (
  <div style={styles.dayList}>
    {[1, 2, 3].map((item) => (
      <div key={item} style={styles.skeletonCard} />
    ))}
  </div>
);

const MyClasses = ({ tutorId }) => {
  const [rows, setRows] = useState([]);
  const [selectedDay, setSelectedDay] = useState('ALL');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [hoveredId, setHoveredId] = useState(null);

  useEffect(() => {
    if (!tutorId) {
      setLoading(false);
      setError('Tutor classes are not available yet.');
      return;
    }

    const fetchClasses = async () => {
      try {
        setLoading(true);
        const res = await axios.get(`/api/tutor/my-classes.php?tutor_id=${tutorId}`);
        const data = res.data || {};
        setRows(Array.isArray(data.rows) ? data.rows : []);
        setError('');
      } catch (err) {
        setError(err.response?.data?.error || 'Failed to load your classes');
      } finally {
        setLoading(false);
      }
    };

    fetchClasses();
  }, [tutorId]);

  const groupedRows = useMemo(() => {
    const map = new Map(DAY_ORDER.map((day) => [day, []]));
    rows
      .filter((row) => selectedDay === 'ALL' || row.day === selectedDay)
      .forEach((row) => {
        const day = DAY_ORDER.includes(row.day) ? row.day : 'Other';
        if (!map.has(day)) {
          map.set(day, []);
        }
        map.get(day).push(row);
      });
    return Array.from(map.entries()).filter(([, dayRows]) => dayRows.length > 0);
  }, [rows, selectedDay]);

  return (
    <section style={styles.section}>
      <style>
        {`
          @keyframes myClassesPulse {
            0% { background-position: 100% 0; }
            100% { background-position: -100% 0; }
          }
          @media (max-width: 900px) {
            .my-classes-row {
              grid-template-columns: 1fr !important;
            }
            .my-classes-room {
              justify-self: start !important;
              text-align: left !important;
            }
          }
        `}
      </style>

      <div style={styles.header}>
        <h3 style={styles.title}>My Classes</h3>
        <p style={styles.subtitle}>View your full timetable from the central admin timetable, grouped by day.</p>
      </div>

      <div style={styles.toolbar}>
        <div style={styles.fieldBlock}>
          <label htmlFor="my-classes-day-filter" style={styles.fieldLabel}>Select Day</label>
          <select
            id="my-classes-day-filter"
            value={selectedDay}
            onChange={(event) => setSelectedDay(event.target.value)}
            style={styles.select}
          >
            <option value="ALL">All Days</option>
            {DAY_ORDER.map((day) => (
              <option key={day} value={day}>{day}</option>
            ))}
          </select>
        </div>
      </div>

      {loading ? (
        <LoadingState />
      ) : error ? (
        <div style={styles.error}>{error}</div>
      ) : groupedRows.length === 0 ? (
        <div style={styles.empty}>
          {selectedDay === 'ALL' ? 'No classes are assigned to you yet.' : `No classes are scheduled for ${selectedDay}.`}
        </div>
      ) : (
        <div style={styles.dayList}>
          {groupedRows.map(([day, dayRows]) => (
            <div key={day} style={styles.dayCard}>
              <div style={styles.dayHeader}>{day}</div>
              <div style={styles.classList}>
                {dayRows.map((row) => (
                  <div
                    key={row.id}
                    className="my-classes-row"
                    style={{ ...styles.row, ...(hoveredId === row.id ? styles.rowHover : {}) }}
                    onMouseEnter={() => setHoveredId(row.id)}
                    onMouseLeave={() => setHoveredId(null)}
                  >
                    <div style={styles.time}>{row.time_slot}</div>
                    <div>
                      <div style={styles.subject}>{row.subject}</div>
                      <div style={styles.grade}>{row.grade}</div>
                    </div>
                    <div className="my-classes-room" style={styles.roomBlock}>
                      <div style={styles.roomValue}>{row.room || 'N/A'}</div>
                      <div style={styles.roomLabel}>Room</div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}
    </section>
  );
};

export default MyClasses;
