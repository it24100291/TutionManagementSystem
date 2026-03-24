import { useEffect, useState } from 'react';
import axios from '../../api/axios';

const styles = {
  section: {
    marginBottom: '20px',
    padding: '20px',
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
    color: '#0f172a',
  },
  subtitle: {
    margin: '6px 0 0',
    color: '#64748b',
  },
  grid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
    gap: '16px',
  },
  card: {
    padding: '18px',
    borderRadius: '14px',
    background: '#f8fbff',
    border: '1px solid #dbe7f2',
    boxShadow: '0 8px 18px rgba(15, 23, 42, 0.04)',
  },
  label: {
    display: 'block',
    marginBottom: '10px',
    color: '#64748b',
    fontSize: '0.82rem',
    fontWeight: 700,
    textTransform: 'uppercase',
    letterSpacing: '0.06em',
  },
  value: {
    display: 'block',
    color: '#0f172a',
    fontSize: '2rem',
    lineHeight: 1.1,
    fontWeight: 800,
  },
  subtext: {
    display: 'block',
    marginTop: '10px',
    color: '#64748b',
    fontSize: '0.92rem',
    fontWeight: 600,
  },
  progressTrack: {
    marginTop: '12px',
    width: '100%',
    height: '6px',
    borderRadius: '999px',
    background: '#dbeafe',
    overflow: 'hidden',
  },
  progressFill: {
    height: '100%',
    borderRadius: '999px',
    background: '#2563eb',
    transition: 'width 0.3s ease',
  },
  skeletonCard: {
    padding: '18px',
    borderRadius: '14px',
    background: '#f8fbff',
    border: '1px solid #dbe7f2',
  },
  skeletonLine: {
    height: '14px',
    borderRadius: '999px',
    background: 'linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%)',
    backgroundSize: '200% 100%',
    animation: 'tutorOverviewPulse 1.4s ease infinite',
  },
  error: {
    padding: '14px 16px',
    borderRadius: '12px',
    background: '#fff7ed',
    color: '#c2410c',
    border: '1px solid #fed7aa',
  },
};

const LoadingSkeleton = () => (
  <div style={styles.grid}>
    {[1, 2, 3, 4].map((item) => (
      <div key={item} style={styles.skeletonCard}>
        <div style={{ ...styles.skeletonLine, width: '42%', marginBottom: '16px' }} />
        <div style={{ ...styles.skeletonLine, width: '65%', height: '32px', marginBottom: '12px' }} />
        <div style={{ ...styles.skeletonLine, width: '55%' }} />
      </div>
    ))}
  </div>
);

const OverviewPanel = ({ tutorId }) => {
  const [overview, setOverview] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!tutorId) {
      setLoading(false);
      setError('Tutor overview is not available yet.');
      return;
    }

    const fetchOverview = async () => {
      try {
        setLoading(true);
        const res = await axios.get(`/api/tutor/overview.php?tutor_id=${tutorId}`);
        setOverview(res.data);
        setError('');
      } catch (err) {
        setError(err.response?.data?.error || 'Failed to load tutor overview');
      } finally {
        setLoading(false);
      }
    };

    fetchOverview();
  }, [tutorId]);

  const cards = overview
    ? [
        {
          label: 'Classes Today',
          value: overview.classes_today,
          subtext: 'Scheduled active classes for today',
        },
        {
          label: 'Total Students',
          value: overview.total_students,
          subtext: 'Distinct students across assigned classes',
        },
        {
          label: 'Hours This Month',
          value: overview.hours_this_month,
          subtext: `${overview.target_hours} hour target this month`,
          progress: overview.target_hours > 0
            ? Math.min(100, Math.round((overview.hours_this_month / overview.target_hours) * 100))
            : 0,
        },
        {
          label: 'Salary Status',
          value: overview.salary_status === 'paid' ? 'Paid' : 'Pending',
          subtext: overview.salary_status === 'paid' ? 'Current month settled' : 'Awaiting monthly payment',
          valueColor: overview.salary_status === 'paid' ? '#22c55e' : '#f59e0b',
        },
      ]
    : [];

  return (
    <section style={styles.section}>
      <style>
        {`
          @keyframes tutorOverviewPulse {
            0% { background-position: 100% 0; }
            100% { background-position: -100% 0; }
          }
        `}
      </style>
      <div style={styles.header}>
        <h3 style={styles.title}>Tutor Overview</h3>
        <p style={styles.subtitle}>A quick look at today’s work, current teaching load, and salary progress.</p>
      </div>

      {loading ? (
        <LoadingSkeleton />
      ) : error ? (
        <div style={styles.error}>{error}</div>
      ) : (
        <div style={styles.grid}>
          {cards.map((card) => (
            <div key={card.label} style={styles.card}>
              <span style={styles.label}>{card.label}</span>
              <strong style={{ ...styles.value, color: card.valueColor || styles.value.color }}>
                {card.value}
              </strong>
              {card.subtext ? <span style={styles.subtext}>{card.subtext}</span> : null}
              {typeof card.progress === 'number' ? (
                <div style={styles.progressTrack}>
                  <div style={{ ...styles.progressFill, width: `${card.progress}%` }} />
                </div>
              ) : null}
            </div>
          ))}
        </div>
      )}
    </section>
  );
};

export default OverviewPanel;
