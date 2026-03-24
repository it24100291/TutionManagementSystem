import { useEffect, useState } from 'react';
import axios from '../api/axios';

const panelStyles = {
  wrapper: {
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
    animation: 'overviewPulse 1.4s ease infinite',
  },
  error: {
    padding: '14px 16px',
    borderRadius: '12px',
    background: '#fff0f0',
    color: '#b91c1c',
    border: '1px solid #f5caca',
  },
};

const formatScoreChange = (value) => {
  const number = Number(value || 0);
  return `${number >= 0 ? '+' : ''}${number} from last term`;
};

const formatCurrency = (value) =>
  new Intl.NumberFormat('en-LK', {
    style: 'currency',
    currency: 'LKR',
    maximumFractionDigits: 0,
  }).format(Number(value || 0));

const LoadingSkeleton = () => (
  <div style={panelStyles.grid}>
    {[1, 2, 3, 4].map((item) => (
      <div key={item} style={panelStyles.skeletonCard}>
        <div style={{ ...panelStyles.skeletonLine, width: '42%', marginBottom: '16px' }} />
        <div style={{ ...panelStyles.skeletonLine, width: '65%', height: '32px', marginBottom: '12px' }} />
        <div style={{ ...panelStyles.skeletonLine, width: '55%' }} />
      </div>
    ))}
  </div>
);

const OverviewPanel = ({ studentId }) => {
  const [overview, setOverview] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!studentId) {
      setLoading(false);
      setError('Student overview is not available yet.');
      return;
    }

    const fetchOverview = async () => {
      try {
        setLoading(true);
        const res = await axios.get(`/api/overview.php?student_id=${studentId}`);
        setOverview(res.data);
        setError('');
      } catch (err) {
        setError(err.response?.data?.error || 'Failed to load overview');
      } finally {
        setLoading(false);
      }
    };

    fetchOverview();
  }, [studentId]);

  const cards = overview
    ? [
        {
          label: 'Attendance',
          value: `${overview.attendance_percent}%`,
          subtext: 'Overall attendance this term',
          progress: overview.attendance_percent,
        },
        {
          label: 'Average Exam Score',
          value: `${overview.avg_score}`,
          subtext: formatScoreChange(overview.avg_score_change),
        },
        {
          label: 'Fee Status',
          value: overview.fee_status === 'overdue' ? 'Overdue' : 'Paid',
          subtext: overview.fee_status === 'overdue'
            ? `${formatCurrency(overview.outstanding_amount)} due`
            : 'No outstanding balance',
          valueColor: overview.fee_status === 'overdue' ? '#ef4444' : '#22c55e',
        },
        {
          label: 'Upcoming Classes',
          value: overview.upcoming_classes,
          subtext: 'Classes scheduled for this week',
        },
      ]
    : [];

  return (
    <section style={panelStyles.wrapper}>
      <style>
        {`
          @keyframes overviewPulse {
            0% { background-position: 100% 0; }
            100% { background-position: -100% 0; }
          }
        `}
      </style>
      <div style={panelStyles.header}>
        <h3 style={panelStyles.title}>Overview Panel</h3>
        <p style={panelStyles.subtitle}>A quick look at your attendance, exams, fees, and classes.</p>
      </div>

      {loading ? (
        <LoadingSkeleton />
      ) : error ? (
        <div style={panelStyles.error}>{error}</div>
      ) : (
        <div style={panelStyles.grid}>
          {cards.map((card) => (
            <div key={card.label} style={panelStyles.card}>
              <span style={panelStyles.label}>{card.label}</span>
              <strong style={{ ...panelStyles.value, color: card.valueColor || panelStyles.value.color }}>
                {card.value}
              </strong>
              {card.subtext ? <span style={panelStyles.subtext}>{card.subtext}</span> : null}
              {typeof card.progress === 'number' ? (
                <div style={panelStyles.progressTrack}>
                  <div style={{ ...panelStyles.progressFill, width: `${Math.max(0, Math.min(100, card.progress))}%` }} />
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
