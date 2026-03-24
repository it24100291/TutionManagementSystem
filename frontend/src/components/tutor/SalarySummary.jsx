import { useEffect, useMemo, useState } from 'react';
import { API_BASE_URL } from '../../api/axios';
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
  metricsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(2, minmax(0, 1fr))',
    gap: '14px',
    marginBottom: '20px',
  },
  metricBox: {
    padding: '14px 16px',
    borderRadius: '14px',
    border: '1px solid #dbe7f2',
    background: '#f8fbff',
    boxShadow: '0 8px 18px rgba(15, 23, 42, 0.04)',
  },
  metricLabel: {
    fontSize: '0.82rem',
    fontWeight: 700,
    letterSpacing: '0.04em',
    textTransform: 'uppercase',
    color: '#64748b',
    marginBottom: '8px',
  },
  metricValue: {
    margin: 0,
    fontSize: '1.45rem',
    fontWeight: 800,
    color: '#0f172a',
  },
  historyTitle: {
    margin: '0 0 12px',
    fontSize: '1rem',
    fontWeight: 800,
    color: '#0f172a',
  },
  historyList: {
    display: 'grid',
    gap: '12px',
  },
  historyRow: {
    padding: '14px 16px',
    borderRadius: '14px',
    border: '1px solid #dbe7f2',
    background: '#ffffff',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: '12px',
  },
  historyMonth: {
    fontWeight: 700,
    color: '#0f172a',
  },
  historyAmount: {
    color: '#475569',
    fontWeight: 700,
    marginTop: '4px',
  },
  badge: {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    minWidth: '86px',
    padding: '8px 12px',
    borderRadius: '999px',
    fontWeight: 800,
    fontSize: '0.82rem',
    textTransform: 'capitalize',
  },
  footer: {
    marginTop: '18px',
    display: 'flex',
    justifyContent: 'flex-end',
  },
  actionButton: {
    border: 'none',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    padding: '12px 18px',
    borderRadius: '12px',
    background: 'linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%)',
    color: '#fff',
    fontWeight: 800,
    cursor: 'pointer',
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
  skeletonGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(2, minmax(0, 1fr))',
    gap: '14px',
    marginBottom: '20px',
  },
  skeletonBox: {
    height: '92px',
    borderRadius: '14px',
    background: 'linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%)',
    backgroundSize: '200% 100%',
    animation: 'salarySummaryPulse 1.4s ease infinite',
  },
};

const currency = (amount) =>
  `LKR ${Number(amount || 0).toLocaleString('en-LK')}`;

const badgeTone = (status) => {
  const normalized = String(status || '').toLowerCase();
  if (normalized === 'paid') {
    return {
      color: '#166534',
      background: '#dcfce7',
    };
  }

  return {
    color: '#b45309',
    background: '#fef3c7',
  };
};

const currentMonthParam = () => {
  const now = new Date();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  return `${now.getFullYear()}-${month}`;
};

const LoadingState = () => (
  <>
    <div style={styles.skeletonGrid}>
      {[1, 2, 3, 4].map((item) => (
        <div key={item} style={styles.skeletonBox} />
      ))}
    </div>
    <div style={styles.skeletonBox} />
  </>
);

const SalarySummary = ({ tutorId }) => {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!tutorId) {
      setLoading(false);
      setError('Salary summary is not available yet.');
      return;
    }

    const fetchSummary = async () => {
      try {
        setLoading(true);
        const res = await axios.get(`/api/tutor/salary-summary.php?tutor_id=${tutorId}`);
        setData(res.data || null);
        setError('');
      } catch (err) {
        setError(err.response?.data?.error || 'Failed to load salary summary');
      } finally {
        setLoading(false);
      }
    };

    fetchSummary();
  }, [tutorId]);

  const slipUrl = useMemo(
    () => `${API_BASE_URL}/api/tutor/salary-slip.php?tutor_id=${tutorId}&month=${currentMonthParam()}`,
    [tutorId]
  );

  const openSlip = () => {
    window.open(slipUrl, '_blank', 'noopener,noreferrer');
  };

  const currentStatusTone = badgeTone(data?.current_status);

  return (
    <section style={styles.section}>
      <style>
        {`
          @keyframes salarySummaryPulse {
            0% { background-position: 100% 0; }
            100% { background-position: -100% 0; }
          }

          @media (max-width: 640px) {
            .salary-summary-metrics {
              grid-template-columns: 1fr !important;
            }

            .salary-summary-history-row {
              flex-direction: column;
              align-items: flex-start !important;
            }
          }
        `}
      </style>
      <div style={styles.header}>
        <h3 style={styles.title}>Salary Summary</h3>
        <p style={styles.subtitle}>Review your monthly hours, salary estimate, and latest payment updates.</p>
      </div>

      {loading ? (
        <LoadingState />
      ) : error ? (
        <div style={styles.error}>{error}</div>
      ) : (
        <>
          <div className="salary-summary-metrics" style={styles.metricsGrid}>
            <div style={styles.metricBox}>
              <div style={styles.metricLabel}>Hours this month</div>
              <p style={styles.metricValue}>{Number(data?.hours_this_month || 0)}</p>
            </div>
            <div style={styles.metricBox}>
              <div style={styles.metricLabel}>Rate per hour</div>
              <p style={styles.metricValue}>{currency(data?.rate_per_hour)}</p>
            </div>
            <div style={styles.metricBox}>
              <div style={styles.metricLabel}>Base salary</div>
              <p style={styles.metricValue}>{currency(data?.base_salary)}</p>
            </div>
            <div style={styles.metricBox}>
              <div style={styles.metricLabel}>Payment status</div>
              <p style={{ ...styles.metricValue, color: currentStatusTone.color, textTransform: 'capitalize' }}>
                {data?.current_status || 'pending'}
              </p>
            </div>
          </div>

          <div>
            <h4 style={styles.historyTitle}>Last 3 months</h4>
            {Array.isArray(data?.history) && data.history.length > 0 ? (
              <div style={styles.historyList}>
                {data.history.map((item, index) => {
                  const tone = badgeTone(item.status);
                  return (
                    <div
                      key={`${item.month}-${index}`}
                      className="salary-summary-history-row"
                      style={styles.historyRow}
                    >
                      <div>
                        <div style={styles.historyMonth}>{item.month}</div>
                        <div style={styles.historyAmount}>{currency(item.amount)}</div>
                      </div>
                      <span style={{ ...styles.badge, color: tone.color, background: tone.background }}>
                        {item.status}
                      </span>
                    </div>
                  );
                })}
              </div>
            ) : (
              <div style={styles.empty}>No salary payment history is available yet.</div>
            )}
          </div>

          <div style={styles.footer}>
            <button type="button" style={styles.actionButton} onClick={openSlip} disabled={!tutorId}>
              Download salary slip
            </button>
          </div>
        </>
      )}
    </section>
  );
};

export default SalarySummary;
