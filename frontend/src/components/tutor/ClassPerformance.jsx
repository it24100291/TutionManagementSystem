import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
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
  list: {
    display: 'grid',
    gap: '14px',
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
    marginBottom: '10px',
  },
  className: {
    fontWeight: 800,
    color: '#0f172a',
    fontSize: '1rem',
  },
  meta: {
    marginTop: '4px',
    color: '#64748b',
    fontSize: '0.9rem',
    fontWeight: 600,
  },
  score: {
    fontWeight: 800,
    fontSize: '1.1rem',
  },
  progressTrack: {
    width: '100%',
    height: '8px',
    borderRadius: '999px',
    background: '#e2e8f0',
    overflow: 'hidden',
  },
  progressFill: {
    height: '100%',
    borderRadius: '999px',
    transition: 'width 0.3s ease',
  },
  footer: {
    marginTop: '18px',
    display: 'flex',
    justifyContent: 'flex-end',
  },
  actionLink: {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    padding: '12px 18px',
    borderRadius: '12px',
    background: 'linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%)',
    color: '#fff',
    fontWeight: 800,
    textDecoration: 'none',
    boxShadow: '0 10px 20px rgba(37, 99, 235, 0.18)',
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
    height: '84px',
    borderRadius: '14px',
    background: 'linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%)',
    backgroundSize: '200% 100%',
    animation: 'classPerformancePulse 1.4s ease infinite',
  },
};

const getPerformanceTone = (score) => {
  const numericScore = Number(score || 0);
  if (numericScore >= 80) {
    return { color: '#15803d', fill: '#22c55e' };
  }
  if (numericScore >= 65) {
    return { color: '#b45309', fill: '#f59e0b' };
  }
  return { color: '#b91c1c', fill: '#ef4444' };
};

const LoadingState = () => (
  <div style={styles.list}>
    {[1, 2, 3].map((item) => (
      <div key={item} style={styles.skeletonRow} />
    ))}
  </div>
);

const ClassPerformance = ({ tutorId }) => {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!tutorId) {
      setLoading(false);
      setError('Class performance is not available yet.');
      return;
    }

    const fetchPerformance = async () => {
      try {
        setLoading(true);
        const res = await axios.get(`/api/tutor/class-performance.php?tutor_id=${tutorId}`);
        setRows(Array.isArray(res.data) ? res.data : []);
        setError('');
      } catch (err) {
        setError(err.response?.data?.error || 'Failed to load class performance');
      } finally {
        setLoading(false);
      }
    };

    fetchPerformance();
  }, [tutorId]);

  return (
    <section style={styles.section}>
      <style>
        {`
          @keyframes classPerformancePulse {
            0% { background-position: 100% 0; }
            100% { background-position: -100% 0; }
          }
        `}
      </style>
      <div style={styles.header}>
        <h3 style={styles.title}>Class Performance</h3>
        <p style={styles.subtitle}>Track average exam marks for your classes in the current term.</p>
      </div>

      {loading ? (
        <LoadingState />
      ) : error ? (
        <div style={styles.error}>{error}</div>
      ) : rows.length === 0 ? (
        <div style={styles.empty}>No exam performance data is available yet.</div>
      ) : (
        <div style={styles.list}>
          {rows.map((row, index) => {
            const tone = getPerformanceTone(row.avg_score);
            return (
              <div key={`${row.class_name}-${row.grade}-${index}`} style={styles.row}>
                <div style={styles.rowTop}>
                  <div>
                    <div style={styles.className}>{row.class_name}</div>
                    <div style={styles.meta}>{row.grade} · {row.student_count} students</div>
                  </div>
                  <div style={{ ...styles.score, color: tone.color }}>{row.avg_score}%</div>
                </div>
                <div style={styles.progressTrack}>
                  <div
                    style={{
                      ...styles.progressFill,
                      width: `${Math.max(0, Math.min(100, Number(row.avg_score || 0)))}%`,
                      background: tone.fill,
                    }}
                  />
                </div>
              </div>
            );
          })}
        </div>
      )}

      <div style={styles.footer}>
        <Link to="/tutor/exam-marks" style={styles.actionLink}>Enter exam marks</Link>
      </div>
    </section>
  );
};

export default ClassPerformance;
