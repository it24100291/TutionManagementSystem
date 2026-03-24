import { useEffect, useMemo, useState } from 'react';
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
  classHeading: {
    marginBottom: '14px',
    padding: '14px 16px',
    borderRadius: '14px',
    background: '#eff6ff',
    border: '1px solid #bfdbfe',
  },
  className: {
    margin: 0,
    fontSize: '1rem',
    fontWeight: 800,
    color: '#0f172a',
  },
  classMeta: {
    marginTop: '4px',
    color: '#64748b',
    fontSize: '0.92rem',
    fontWeight: 600,
  },
  list: {
    display: 'grid',
    gap: '12px',
  },
  row: {
    display: 'grid',
    gridTemplateColumns: '56px 1fr auto',
    gap: '12px',
    alignItems: 'center',
    padding: '14px',
    borderRadius: '14px',
    border: '1px solid #dbe7f2',
    background: '#f8fbff',
  },
  avatar: {
    width: '44px',
    height: '44px',
    borderRadius: '50%',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    background: '#dbeafe',
    color: '#1d4ed8',
    fontWeight: 800,
    fontSize: '0.95rem',
  },
  studentName: {
    fontWeight: 700,
    color: '#0f172a',
  },
  toggles: {
    display: 'inline-flex',
    gap: '8px',
    flexWrap: 'wrap',
  },
  toggleButton: {
    border: '1px solid #cbd5e1',
    background: '#fff',
    color: '#334155',
    fontWeight: 700,
    padding: '9px 14px',
    borderRadius: '999px',
    cursor: 'pointer',
    transition: 'all 0.2s ease',
  },
  presentActive: {
    borderColor: '#22c55e',
    background: '#dcfce7',
    color: '#15803d',
    boxShadow: '0 8px 16px rgba(34, 197, 94, 0.14)',
  },
  absentActive: {
    borderColor: '#ef4444',
    background: '#fee2e2',
    color: '#b91c1c',
    boxShadow: '0 8px 16px rgba(239, 68, 68, 0.14)',
  },
  actionRow: {
    marginTop: '18px',
    display: 'flex',
    justifyContent: 'space-between',
    gap: '10px',
    flexWrap: 'wrap',
  },
  primaryButton: {
    border: 'none',
    padding: '12px 18px',
    borderRadius: '12px',
    background: 'linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%)',
    color: '#fff',
    fontWeight: 800,
    cursor: 'pointer',
    boxShadow: '0 10px 20px rgba(37, 99, 235, 0.18)',
  },
  secondaryButton: {
    border: '1px solid #cbd5e1',
    padding: '12px 18px',
    borderRadius: '12px',
    background: '#fff',
    color: '#334155',
    fontWeight: 700,
    cursor: 'pointer',
  },
  buttonDisabled: {
    opacity: 0.5,
    cursor: 'not-allowed',
    boxShadow: 'none',
  },
  success: {
    marginTop: '14px',
    padding: '14px 16px',
    borderRadius: '12px',
    background: '#dcfce7',
    color: '#166534',
    border: '1px solid #bbf7d0',
  },
  error: {
    padding: '14px 16px',
    borderRadius: '12px',
    background: '#fff7ed',
    color: '#c2410c',
    border: '1px solid #fed7aa',
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
  skeletonRow: {
    height: '74px',
    borderRadius: '14px',
    background: 'linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%)',
    backgroundSize: '200% 100%',
    animation: 'attendancePulse 1.4s ease infinite',
  },
  statusPill: {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: '10px',
    padding: '6px 10px',
    borderRadius: '999px',
    background: '#dbeafe',
    color: '#1d4ed8',
    fontSize: '0.82rem',
    fontWeight: 800,
  },
};

const getInitials = (name = '') =>
  name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('') || 'S';

const AttendanceMarking = ({ tutorId }) => {
  const [nextClass, setNextClass] = useState(null);
  const [students, setStudents] = useState([]);
  const [marks, setMarks] = useState({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [submitted, setSubmitted] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const hasMarkedRecords = useMemo(() => Object.keys(marks).length > 0, [marks]);

  useEffect(() => {
    if (!tutorId) {
      setLoading(false);
      setError('Attendance panel is not available yet.');
      return;
    }

    const fetchAttendanceContext = async () => {
      try {
        setLoading(true);
        setError('');
        setSuccess('');

        const classRes = await axios.get(`/api/tutor/next-class.php?tutor_id=${tutorId}`);
        const classData = classRes.data;

        if (!classData || !classData.id) {
          setNextClass(null);
          setStudents([]);
          setMarks({});
          setSubmitted(false);
          return;
        }

        setNextClass(classData);
        setSubmitted(Boolean(classData.attendance_submitted));

        const studentsRes = await axios.get(
          `/api/tutor/class-students.php?class_id=${classData.class_id}&timetable_id=${classData.id}`
        );
        const studentRows = Array.isArray(studentsRes.data) ? studentsRes.data : [];
        setStudents(studentRows);

        const initialMarks = {};
        studentRows.forEach((student) => {
          initialMarks[String(student.id)] = student.attendance_status || 'present';
        });
        setMarks(initialMarks);
      } catch (err) {
        setError(err.response?.data?.error || 'Failed to load attendance panel');
      } finally {
        setLoading(false);
      }
    };

    fetchAttendanceContext();
  }, [tutorId]);

  const setStudentStatus = (studentId, status) => {
    if (submitted) {
      return;
    }

    setMarks((current) => ({
      ...current,
      [String(studentId)]: status,
    }));
  };

  const markAllPresent = () => {
    if (submitted) {
      return;
    }

    const nextMarks = {};
    students.forEach((student) => {
      nextMarks[String(student.id)] = 'present';
    });
    setMarks(nextMarks);
  };

  const handleSubmit = async () => {
    if (!nextClass?.id || !hasMarkedRecords || submitted) {
      return;
    }

    try {
      setSubmitting(true);
      setError('');
      setSuccess('');

      const records = students.map((student) => ({
        student_id: student.id,
        status: marks[String(student.id)] || 'present',
      }));

      const res = await axios.post('/api/tutor/submit-attendance.php', {
        tutor_id: tutorId,
        timetable_id: nextClass.id,
        records,
      });

      setSubmitted(true);
      setSuccess(res.data?.message || `Attendance submitted for ${records.length} students.`);
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to submit attendance');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <section style={styles.section}>
      <style>
        {`
          @keyframes attendancePulse {
            0% { background-position: 100% 0; }
            100% { background-position: -100% 0; }
          }
          @media (max-width: 700px) {
            .attendance-row {
              grid-template-columns: 48px 1fr !important;
            }
            .attendance-row-actions {
              grid-column: 1 / -1;
            }
          }
        `}
      </style>

      <div style={styles.header}>
        <h3 style={styles.title}>Attendance Marking</h3>
        <p style={styles.subtitle}>Mark attendance for the next upcoming class and submit once for today.</p>
      </div>

      {loading ? (
        <div style={{ display: 'grid', gap: '12px' }}>
          {[1, 2, 3].map((item) => (
            <div key={item} style={styles.skeletonRow} />
          ))}
        </div>
      ) : error ? (
        <div style={styles.error}>{error}</div>
      ) : !nextClass ? (
        <div style={styles.empty}>No upcoming class found for today.</div>
      ) : (
        <>
          <div style={styles.classHeading}>
            <h4 style={styles.className}>{nextClass.name} - {nextClass.grade}</h4>
            <div style={styles.classMeta}>Starts at {nextClass.start_time}</div>
            {submitted ? <span style={styles.statusPill}>Attendance already submitted</span> : null}
          </div>

          {students.length === 0 ? (
            <div style={styles.empty}>No students found for this class.</div>
          ) : (
            <div style={styles.list}>
              {students.map((student) => {
                const status = marks[String(student.id)] || 'present';

                return (
                  <div key={student.id} className="attendance-row" style={styles.row}>
                    <div style={styles.avatar}>{getInitials(student.name)}</div>
                    <div style={styles.studentName}>{student.name}</div>
                    <div className="attendance-row-actions" style={styles.toggles}>
                      <button
                        type="button"
                        style={{ ...styles.toggleButton, ...(status === 'present' ? styles.presentActive : {}) }}
                        onClick={() => setStudentStatus(student.id, 'present')}
                        disabled={submitted}
                      >
                        Present
                      </button>
                      <button
                        type="button"
                        style={{ ...styles.toggleButton, ...(status === 'absent' ? styles.absentActive : {}) }}
                        onClick={() => setStudentStatus(student.id, 'absent')}
                        disabled={submitted}
                      >
                        Absent
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          )}

          <div style={styles.actionRow}>
            <button
              type="button"
              style={{ ...styles.secondaryButton, ...(submitted ? styles.buttonDisabled : {}) }}
              onClick={markAllPresent}
              disabled={submitted}
            >
              Mark all present
            </button>
            <button
              type="button"
              style={{ ...styles.primaryButton, ...(!hasMarkedRecords || submitted || submitting ? styles.buttonDisabled : {}) }}
              onClick={handleSubmit}
              disabled={!hasMarkedRecords || submitted || submitting}
            >
              {submitted ? 'Attendance Submitted' : submitting ? 'Submitting...' : 'Submit attendance'}
            </button>
          </div>

          {success ? <div style={styles.success}>{success}</div> : null}
        </>
      )}
    </section>
  );
};

export default AttendanceMarking;
