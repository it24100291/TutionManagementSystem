import { useEffect, useMemo, useState } from 'react';
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
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    gap: '14px',
    marginBottom: '16px',
    flexWrap: 'wrap',
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
  count: {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    minWidth: '92px',
    padding: '10px 14px',
    borderRadius: '999px',
    background: '#eff6ff',
    color: '#1d4ed8',
    fontWeight: 800,
  },
  searchWrap: {
    marginBottom: '16px',
  },
  searchInput: {
    width: '100%',
    padding: '12px 14px',
    borderRadius: '12px',
    border: '1px solid #cbd5e1',
    background: '#fff',
    color: '#0f172a',
    outline: 'none',
    fontSize: '0.95rem',
  },
  list: {
    display: 'grid',
    gap: '12px',
  },
  row: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: '14px',
    padding: '14px 16px',
    borderRadius: '14px',
    border: '1px solid #dbe7f2',
    background: '#f8fbff',
    boxShadow: '0 8px 18px rgba(15, 23, 42, 0.04)',
    flexWrap: 'wrap',
  },
  rowMain: {
    display: 'flex',
    alignItems: 'center',
    gap: '12px',
    minWidth: 0,
    flex: '1 1 260px',
  },
  avatar: {
    width: '42px',
    height: '42px',
    borderRadius: '999px',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    background: 'linear-gradient(135deg, #2563eb 0%, #3b82f6 100%)',
    color: '#fff',
    fontWeight: 800,
    fontSize: '0.95rem',
    flexShrink: 0,
  },
  name: {
    fontWeight: 800,
    color: '#0f172a',
    marginBottom: '4px',
  },
  meta: {
    color: '#64748b',
    fontSize: '0.9rem',
    fontWeight: 600,
  },
  badge: {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    minWidth: '84px',
    padding: '8px 12px',
    borderRadius: '999px',
    fontWeight: 800,
    fontSize: '0.85rem',
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
  skeleton: {
    height: '82px',
    borderRadius: '14px',
    background: 'linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%)',
    backgroundSize: '200% 100%',
    animation: 'studentListPulse 1.4s ease infinite',
  },
};

const getInitials = (name) => {
  const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return 'ST';
  return parts.slice(0, 2).map((part) => part[0]?.toUpperCase() || '').join('');
};

const attendanceTone = (value) => {
  const attendance = Number(value || 0);
  if (attendance >= 80) {
    return { color: '#166534', background: '#dcfce7' };
  }
  if (attendance >= 65) {
    return { color: '#b45309', background: '#fef3c7' };
  }
  return { color: '#b91c1c', background: '#fee2e2' };
};

const LoadingState = () => (
  <div style={styles.list}>
    {[1, 2, 3].map((item) => (
      <div key={item} style={styles.skeleton} />
    ))}
  </div>
);

const StudentList = ({ tutorId, compact = false }) => {
  const [students, setStudents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');

  useEffect(() => {
    if (!tutorId) {
      setLoading(false);
      setError('Student list is not available yet.');
      return;
    }

    const fetchStudents = async () => {
      try {
        setLoading(true);
        const res = await axios.get(`/api/tutor/students.php?tutor_id=${tutorId}`);
        setStudents(Array.isArray(res.data) ? res.data : []);
        setError('');
      } catch (err) {
        setError(err.response?.data?.error || 'Failed to load students');
      } finally {
        setLoading(false);
      }
    };

    fetchStudents();
  }, [tutorId]);

  const filteredStudents = useMemo(() => {
    const term = search.trim().toLowerCase();
    if (!term) return students;
    return students.filter((student) => {
      const haystack = [
        student.name,
        student.grade,
        student.class_name,
      ]
        .join(' ')
        .toLowerCase();
      return haystack.includes(term);
    });
  }, [students, search]);

  const visibleStudents = compact ? filteredStudents.slice(0, 6) : filteredStudents;

  return (
    <section style={styles.section}>
      <style>
        {`
          @keyframes studentListPulse {
            0% { background-position: 100% 0; }
            100% { background-position: -100% 0; }
          }
        `}
      </style>
      <div style={styles.header}>
        <div>
          <h3 style={styles.title}>Students</h3>
          <p style={styles.subtitle}>Search and review students assigned to your classes.</p>
        </div>
        <div style={styles.count}>{students.length} Total</div>
      </div>

      <div style={styles.searchWrap}>
        <input
          type="text"
          placeholder="Search by name, grade, or class"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          style={styles.searchInput}
        />
      </div>

      {loading ? (
        <LoadingState />
      ) : error ? (
        <div style={styles.error}>{error}</div>
      ) : visibleStudents.length === 0 ? (
        <div style={styles.empty}>
          {students.length === 0 ? 'No students are assigned yet.' : 'No students match your search.'}
        </div>
      ) : (
        <div style={styles.list}>
          {visibleStudents.map((student) => {
            const tone = attendanceTone(student.attendance_percent);
            return (
              <div key={student.id} style={styles.row}>
                <div style={styles.rowMain}>
                  <div style={styles.avatar}>{getInitials(student.name)}</div>
                  <div>
                    <div style={styles.name}>{student.name}</div>
                    <div style={styles.meta}>{student.grade} · {student.class_name}</div>
                  </div>
                </div>
                <span style={{ ...styles.badge, color: tone.color, background: tone.background }}>
                  {Number(student.attendance_percent || 0)}%
                </span>
              </div>
            );
          })}
        </div>
      )}

      <div style={styles.footer}>
        <Link to="/tutor/students" style={styles.actionLink}>View all students</Link>
      </div>
    </section>
  );
};

export default StudentList;
