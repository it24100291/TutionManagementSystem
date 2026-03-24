import { useEffect, useMemo, useState } from 'react';
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
  list: {
    display: 'grid',
    gap: '12px',
  },
  row: {
    display: 'grid',
    gridTemplateColumns: '180px 1.3fr 1fr auto',
    gap: '14px',
    alignItems: 'center',
    padding: '16px',
    borderRadius: '14px',
    border: '1px solid #dbe7f2',
    background: '#f8fbff',
    boxShadow: '0 8px 18px rgba(15, 23, 42, 0.04)',
    cursor: 'pointer',
    transition: 'all 0.2s ease',
  },
  rowSelected: {
    border: '1px solid #93c5fd',
    background: '#eff6ff',
    boxShadow: '0 10px 22px rgba(37, 99, 235, 0.08)',
  },
  timeBlock: {
    display: 'flex',
    flexDirection: 'column',
    gap: '4px',
  },
  timeMain: {
    fontWeight: 800,
    color: '#0f172a',
    fontSize: '1rem',
  },
  timeMeta: {
    color: '#64748b',
    fontSize: '0.9rem',
    fontWeight: 600,
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
  badge: {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    minWidth: '108px',
    padding: '8px 12px',
    borderRadius: '999px',
    fontSize: '0.85rem',
    fontWeight: 800,
    textTransform: 'capitalize',
  },
  badgeUpcoming: {
    background: '#dbeafe',
    color: '#1d4ed8',
  },
  badgeConfirmed: {
    background: '#dcfce7',
    color: '#15803d',
  },
  badgeRescheduled: {
    background: '#fef3c7',
    color: '#b45309',
  },
  badgeAbsentReported: {
    background: '#fee2e2',
    color: '#b91c1c',
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
  footer: {
    marginTop: '18px',
    display: 'flex',
    justifyContent: 'flex-end',
  },
  reportButton: {
    border: 'none',
    padding: '12px 18px',
    borderRadius: '12px',
    background: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
    color: '#fff',
    fontWeight: 800,
    cursor: 'pointer',
    boxShadow: '0 10px 20px rgba(239, 68, 68, 0.18)',
  },
  reportButtonDisabled: {
    opacity: 0.5,
    cursor: 'not-allowed',
    boxShadow: 'none',
  },
  skeletonRow: {
    height: '86px',
    borderRadius: '14px',
    background: 'linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%)',
    backgroundSize: '200% 100%',
    animation: 'todaySchedulePulse 1.4s ease infinite',
  },
  modalBackdrop: {
    position: 'fixed',
    inset: 0,
    background: 'rgba(15, 23, 42, 0.36)',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    padding: '20px',
    zIndex: 100,
  },
  modalCard: {
    width: '100%',
    maxWidth: '420px',
    padding: '22px',
    borderRadius: '18px',
    background: '#fff',
    border: '1px solid #dbe7f2',
    boxShadow: '0 20px 40px rgba(15, 23, 42, 0.18)',
  },
  modalTitle: {
    margin: 0,
    fontSize: '1.2rem',
    fontWeight: 800,
    color: '#0f172a',
  },
  modalText: {
    marginTop: '10px',
    color: '#64748b',
    lineHeight: 1.6,
  },
  modalActions: {
    marginTop: '18px',
    display: 'flex',
    justifyContent: 'flex-end',
    gap: '10px',
  },
  secondaryButton: {
    border: '1px solid #cbd5e1',
    padding: '11px 16px',
    borderRadius: '12px',
    background: '#fff',
    color: '#334155',
    fontWeight: 700,
    cursor: 'pointer',
  },
};

const badgeStyles = {
  upcoming: styles.badgeUpcoming,
  confirmed: styles.badgeConfirmed,
  rescheduled: styles.badgeRescheduled,
  absent_reported: styles.badgeAbsentReported,
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
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [selectedId, setSelectedId] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [reporting, setReporting] = useState(false);

  const selectedRow = useMemo(
    () => rows.find((row) => String(row.id) === String(selectedId)) || null,
    [rows, selectedId]
  );

  const fetchSchedule = async () => {
    try {
      setLoading(true);
      const res = await axios.get(`/api/tutor/today-schedule.php?tutor_id=${tutorId}`);
      setRows(Array.isArray(res.data) ? res.data : []);
      setError('');
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to load today schedule');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (!tutorId) {
      setLoading(false);
      setError('Tutor schedule is not available yet.');
      return;
    }

    fetchSchedule();
  }, [tutorId]);

  const confirmReportAbsence = async () => {
    if (!selectedRow) {
      return;
    }

    try {
      setReporting(true);
      await axios.post('/api/tutor/report-absence.php', {
        tutor_id: tutorId,
        timetable_id: selectedRow.id,
      });
      setShowModal(false);
      await fetchSchedule();
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to report absence');
      setShowModal(false);
    } finally {
      setReporting(false);
    }
  };

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
          }
        `}
      </style>

      <div style={styles.header}>
        <h3 style={styles.title}>Today's Schedule</h3>
        <p style={styles.subtitle}>Review today&apos;s teaching sessions and report an absence if needed.</p>
      </div>

      {loading ? (
        <LoadingState />
      ) : error ? (
        <div style={styles.error}>{error}</div>
      ) : rows.length === 0 ? (
        <div style={styles.empty}>No classes scheduled for today. Enjoy the free space in your day.</div>
      ) : (
        <>
          <div style={styles.list}>
            {rows.map((row) => {
              const isSelected = String(selectedId) === String(row.id);
              return (
                <div
                  key={row.id}
                  className="today-schedule-row"
                  style={{ ...styles.row, ...(isSelected ? styles.rowSelected : {}) }}
                  onClick={() => setSelectedId(row.id)}
                  role="button"
                  tabIndex={0}
                  onKeyDown={(event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                      event.preventDefault();
                      setSelectedId(row.id);
                    }
                  }}
                >
                  <div style={styles.timeBlock}>
                    <span style={styles.timeMain}>{row.start_time}</span>
                    <span style={styles.timeMeta}>{row.duration_hours} hour{Number(row.duration_hours) === 1 ? '' : 's'}</span>
                  </div>
                  <div>
                    <div style={styles.infoTitle}>{row.class_name}</div>
                    <div style={styles.infoMeta}>{row.grade}</div>
                  </div>
                  <div>
                    <div style={styles.infoTitle}>{row.room}</div>
                    <div style={styles.infoMeta}>{row.student_count} students</div>
                  </div>
                  <div>
                    <span style={{ ...styles.badge, ...(badgeStyles[row.status] || styles.badgeUpcoming) }}>
                      {row.status.replace('_', ' ')}
                    </span>
                  </div>
                </div>
              );
            })}
          </div>

          <div style={styles.footer}>
            <button
              type="button"
              style={{
                ...styles.reportButton,
                ...(!selectedRow || reporting ? styles.reportButtonDisabled : {}),
              }}
              onClick={() => setShowModal(true)}
              disabled={!selectedRow || reporting}
            >
              Report absence
            </button>
          </div>
        </>
      )}

      {showModal && selectedRow ? (
        <div style={styles.modalBackdrop}>
          <div style={styles.modalCard}>
            <h4 style={styles.modalTitle}>Confirm absence report</h4>
            <p style={styles.modalText}>
              Are you sure you want to report absence for {selectedRow.class_name} at {selectedRow.start_time}?
            </p>
            <div style={styles.modalActions}>
              <button type="button" style={styles.secondaryButton} onClick={() => setShowModal(false)}>
                Cancel
              </button>
              <button type="button" style={styles.reportButton} onClick={confirmReportAbsence} disabled={reporting}>
                {reporting ? 'Reporting...' : 'Yes'}
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </section>
  );
};

export default TodaySchedule;
