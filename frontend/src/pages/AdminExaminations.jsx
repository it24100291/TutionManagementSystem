import { useEffect, useMemo, useState } from 'react';
import axios from '../api/axios';

const gradeOptions = ['Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11'];
const termOptions = ['Term 1', 'Term 2', 'Term 3'];
const subjectOptions = ['Tamil', 'Maths', 'Science', 'Religion', 'English', 'Civics', 'History', 'Geography', 'ICT', 'Health Science', 'Sinhala', 'Commerce'];

const initialForm = {
  exam_name: '',
  exam_date: '',
  grade: 'Grade 6',
  term: 'Term 1',
  subjects: [],
};

const styles = {
  gradeTabs: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(120px, 1fr))',
    gap: '10px',
    marginBottom: '18px',
  },
  tabButton: {
    minHeight: '44px',
    borderRadius: '12px',
    border: '1px solid #cbd5e1',
    background: '#ffffff',
    color: '#0f172a',
    fontWeight: 700,
    cursor: 'pointer',
    transition: 'all 0.3s ease',
  },
  tabButtonActive: {
    background: '#2563eb',
    borderColor: '#2563eb',
    color: '#ffffff',
    boxShadow: '0 12px 24px rgba(37, 99, 235, 0.2)',
  },
  termTabs: {
    display: 'flex',
    flexWrap: 'wrap',
    gap: '10px',
    marginBottom: '18px',
  },
  toolbar: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
    gap: '14px',
    marginBottom: '20px',
  },
  classCard: {
    marginTop: '20px',
    padding: '20px',
    borderRadius: '16px',
    border: '1px solid #dbe7f2',
    background: '#ffffff',
    boxShadow: '0 12px 24px rgba(15, 23, 42, 0.05)',
  },
  tableWrap: {
    overflowX: 'auto',
  },
  sectionMeta: {
    display: 'flex',
    flexWrap: 'wrap',
    gap: '12px',
    marginBottom: '12px',
    color: '#64748b',
    fontWeight: 600,
  },
};

const toNumber = (value) => {
  const numeric = Number(value);
  return Number.isFinite(numeric) ? numeric : 0;
};

const formatMark = (value) => {
  if (value === '' || value === null || typeof value === 'undefined') {
    return '';
  }
  const numeric = Number(value);
  return Number.isFinite(numeric) ? numeric : value;
};

const visibleSubjectsForGrade = (subjects, grade) => {
  const gradeValue = String(grade || '').trim();
  if (['Grade 6', 'Grade 7', 'Grade 8', 'Grade 9'].includes(gradeValue)) {
    return subjects.filter((subject) => String(subject).trim() !== 'Commerce');
  }

  if (['Grade 10', 'Grade 11'].includes(gradeValue)) {
    const preferredOrder = [
      'Tamil',
      'Religion',
      'Science',
      'Maths',
      'English',
      'History',
      'ICT',
      'Health Science',
      'Commerce',
      'Geography',
      'Civics',
      'Sinhala',
    ];

    const subjectSet = new Set(subjects.map((subject) => String(subject).trim()));
    const orderedSubjects = preferredOrder.filter((subject) => subjectSet.has(subject));
    const remainingSubjects = subjects.filter((subject) => !preferredOrder.includes(String(subject).trim()));
    return [...orderedSubjects, ...remainingSubjects];
  }

  return subjects;
};

const AdminExaminations = () => {
  const [formValues, setFormValues] = useState(initialForm);
  const [submitting, setSubmitting] = useState(false);
  const [loadingExams, setLoadingExams] = useState(true);
  const [loadingResults, setLoadingResults] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [exams, setExams] = useState([]);
  const [selectedGrade, setSelectedGrade] = useState('Grade 6');
  const [selectedTerm, setSelectedTerm] = useState('Term 1');
  const [selectedExamId, setSelectedExamId] = useState('');
  const [sortMode, setSortMode] = useState('name');
  const [results, setResults] = useState(null);

  const loadExams = async () => {
    try {
      setLoadingExams(true);
      const response = await axios.get('/api/admin/examinations.php');
      const nextExams = response.data?.data || [];
      setExams(Array.isArray(nextExams) ? nextExams : []);
      setError('');
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to load examinations.');
    } finally {
      setLoadingExams(false);
    }
  };

  useEffect(() => {
    loadExams();
  }, []);

  const filteredExams = useMemo(
    () => exams.filter((exam) => String(exam.grade || '').trim() === selectedGrade && String(exam.term || '').trim() === selectedTerm),
    [exams, selectedGrade, selectedTerm]
  );

  useEffect(() => {
    if (filteredExams.length === 0) {
      setSelectedExamId('');
      setResults(null);
      return;
    }

    const hasSelected = filteredExams.some((exam) => String(exam.id) === String(selectedExamId));
    if (!hasSelected) {
      setSelectedExamId(String(filteredExams[0].id));
    }
  }, [filteredExams, selectedExamId]);

  useEffect(() => {
    setResults(null);
    let isMounted = true;


    const loadResults = async (showLoader = true) => {
      try {
        if (showLoader) {
          setLoadingResults(true);
        }
        const query = selectedExamId ? `/api/admin/examination-results.php?exam_id=${selectedExamId}` : `/api/admin/examination-results.php?grade=${encodeURIComponent(selectedGrade)}&term=${encodeURIComponent(selectedTerm)}`;
        const response = await axios.get(query);
        if (!isMounted) {
          return;
        }
        setResults(response.data?.data || null);
        setError('');
      } catch (err) {
        if (!isMounted) {
          return;
        }
        setResults(null);
        setError(err.response?.data?.error || 'Failed to load examination results.');
      } finally {
        if (isMounted && showLoader) {
          setLoadingResults(false);
        }
      }
    };

    loadResults(true);
    const intervalId = window.setInterval(() => {
      loadResults(false);
    }, 5000);

    return () => {
      isMounted = false;
      window.clearInterval(intervalId);
    };
  }, [selectedExamId, selectedGrade, selectedTerm]);

  const handleSubjectToggle = (subject) => {
    setFormValues((current) => {
      const exists = current.subjects.includes(subject);
      return {
        ...current,
        subjects: exists ? current.subjects.filter((item) => item !== subject) : [...current.subjects, subject],
      };
    });
    setError('');
    setSuccess('');
  };

  const handleCreateExam = async (event) => {
    event.preventDefault();
    setSubmitting(true);
    setError('');
    setSuccess('');

    try {
      const response = await axios.post('/api/admin/examinations.php', formValues);
      setSuccess(response.data?.data?.message || 'Examination created successfully.');
      setFormValues(initialForm);
      await loadExams();
      setSelectedGrade(formValues.grade);
      setSelectedTerm(formValues.term);
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to create examination.');
    } finally {
      setSubmitting(false);
    }
  };

  const processedClasses = useMemo(() => {
    if (!results?.subjects || !results?.classes) {
      return [];
    }

    const visibleSubjects = visibleSubjectsForGrade(results.subjects, results?.exam?.grade);

    return results.classes.map((classGroup) => {
      const rows = (classGroup.rows || []).map((row) => {
        const subjectValues = visibleSubjects.map((subject) => toNumber(row.marks?.[subject]));
        const total = subjectValues.reduce((sum, value) => sum + value, 0);
        const average = visibleSubjects.length > 0 ? total / visibleSubjects.length : 0;
        return {
          ...row,
          total,
          average,
        };
      });

      rows.sort((a, b) => {
        if (sortMode === 'total') {
          if (b.total !== a.total) {
            return b.total - a.total;
          }
        }
        return String(a.student_name || '').localeCompare(String(b.student_name || ''));
      });

      return {
        ...classGroup,
        subjects: visibleSubjects,
        rows,
      };
    });
  }, [results, sortMode]);

  return (
    <div className="container">
      <div className="form-box dashboard-shell dashboard-xwide">
        <div className="dashboard-header">
          <div>
            <h2>Exams &amp; Results</h2>
            <p className="dashboard-subtitle">
              Manage grade-wise examinations and review live tutor-entered marks by term.
            </p>
          </div>
        </div>

        {error ? <div className="error">{error}</div> : null}
        {success ? <div className="success">{success}</div> : null}
<div className="dashboard-edit-card" style={{ marginBottom: 24 }}>
          <h3 style={{ marginTop: 0 }}>Browse Results</h3>

          <div style={styles.gradeTabs}>
            {gradeOptions.map((grade) => (
              <button
                key={grade}
                type="button"
                style={{
                  ...styles.tabButton,
                  ...(selectedGrade === grade ? styles.tabButtonActive : {}),
                }}
                onClick={() => {
                  setSelectedGrade(grade);
                  setError('');
                  setSuccess('');
                }}
              >
                {grade}
              </button>
            ))}
          </div>

          <div style={styles.termTabs}>
            {termOptions.map((term) => (
              <button
                key={term}
                type="button"
                style={{
                  ...styles.tabButton,
                  minWidth: '120px',
                  ...(selectedTerm === term ? styles.tabButtonActive : {}),
                }}
                onClick={() => {
                  setSelectedTerm(term);
                  setError('');
                  setSuccess('');
                }}
              >
                {term}
              </button>
            ))}
          </div>
        </div>

        {loadingResults ? (
          <div className="dashboard-empty">Loading results...</div>
        ) : !results ? (
          <div className="dashboard-empty">No student details available for this grade and term yet.</div>
        ) : (
          <>
            <div style={styles.sectionMeta}>
              <span><strong>Grade:</strong> {results.exam?.grade}</span>
              <span><strong>Term:</strong> {results.exam?.term}</span>
              <span><strong>Exam:</strong> {results.exam?.exam_name}</span>
              <span><strong>Date:</strong> {results.exam?.exam_date}</span>
            </div>

            {processedClasses.length ? (
              processedClasses.map((classGroup) => (
                <div key={classGroup.class_name} style={styles.classCard}>
                  <h3 style={{ marginTop: 0 }}>{classGroup.class_name}</h3>
                  <div style={styles.tableWrap}>
                    <table className="dashboard-table">
                      <thead>
                        <tr>
                          <th>Student Name</th>
                          {classGroup.subjects.map((subject) => (
                            <th key={subject}>{subject}</th>
                          ))}
                          <th>Total</th>
                          <th>Average</th>
                        </tr>
                      </thead>
                      <tbody>
                        {classGroup.rows.map((row) => (
                          <tr key={row.student_id}>
                            <td>{row.student_name}</td>
                            {classGroup.subjects.map((subject) => (
                              <td key={`${row.student_id}-${subject}`}>{formatMark(row.marks?.[subject])}</td>
                            ))}
                            <td>{row.total}</td>
                            <td>{row.average.toFixed(1)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              ))
            ) : (
              <div className="dashboard-empty">No student marks found for this grade and term yet.</div>
            )}
          </>
        )}
      </div>
    </div>
  );
};

export default AdminExaminations;







