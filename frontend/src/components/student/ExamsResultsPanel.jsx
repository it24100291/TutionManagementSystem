import { useEffect, useState } from 'react';
import axios from '../../api/axios';

const styles = {
  section: {
    display: 'grid',
    gap: '20px',
  },
  summaryCard: {
    padding: '20px',
    borderRadius: '12px',
    background: 'linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(248,250,252,0.96) 100%)',
    border: '1px solid #dbe7f2',
    boxShadow: '0 12px 24px rgba(15, 23, 42, 0.05)',
    maxWidth: '320px',
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
    animation: 'studentExamsSpin 0.9s linear infinite',
  },
};

const StudentExamsResultsPanel = ({ studentId }) => {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!studentId) {
      setLoading(false);
      setError('Exams & results are not available yet.');
      return;
    }

    const fetchResults = async () => {
      try {
        setLoading(true);
        const res = await axios.get(`/api/student_exams_results.php?student_id=${studentId}`);
        setData(res.data || null);
        setError('');
      } catch (err) {
        setError(err.response?.data?.error || 'Failed to load exam results');
      } finally {
        setLoading(false);
      }
    };

    fetchResults();
  }, [studentId]);

  if (loading) {
    return (
      <div style={styles.loadingWrap}>
        <style>
          {`
            @keyframes studentExamsSpin {
              from { transform: rotate(0deg); }
              to { transform: rotate(360deg); }
            }
          `}
        </style>
        <div style={styles.spinner} />
        <div style={{ color: '#475569', fontWeight: 600 }}>Loading exam results...</div>
      </div>
    );
  }

  if (error) {
    return <div style={styles.error}>{error}</div>;
  }

  return (
    <div style={styles.section}>
      <div style={styles.summaryCard}>
        <span style={styles.summaryLabel}>Average Mark</span>
        <p style={styles.summaryValue}>{Number(data?.average_mark ?? 0)}%</p>
      </div>

      <div style={styles.tableWrap}>
        <div style={styles.tableHeader}>
          <h3 style={styles.tableTitle}>Exam Results</h3>
        </div>
        {Array.isArray(data?.results) && data.results.length > 0 ? (
          <div style={{ overflowX: 'auto' }}>
            <table style={styles.table}>
              <thead>
                <tr>
                  <th style={styles.th}>Exam Name</th>
                  <th style={styles.th}>Subject</th>
                  <th style={styles.th}>Marks Obtained</th>
                  <th style={styles.th}>Total Marks</th>
                  <th style={styles.th}>Grade</th>
                </tr>
              </thead>
              <tbody>
                {data.results.map((row, index) => (
                  <tr key={`${row.exam_name}-${row.subject}-${index}`}>
                    <td style={styles.td}>{row.exam_name}</td>
                    <td style={styles.td}>{row.subject}</td>
                    <td style={styles.td}>{row.marks_obtained}</td>
                    <td style={styles.td}>{row.total_marks}</td>
                    <td style={styles.td}>{row.grade}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div style={{ padding: '20px' }}>
            <div style={styles.empty}>No exam results available yet.</div>
          </div>
        )}
      </div>
    </div>
  );
};

export default StudentExamsResultsPanel;
