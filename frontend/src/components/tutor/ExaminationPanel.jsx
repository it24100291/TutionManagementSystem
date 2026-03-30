import { useEffect, useMemo, useState } from 'react';
import axios from '../../api/axios';

const TARGET_GRADES = ['Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11'];
const TERM_OPTIONS = ['Term 1', 'Term 2', 'Term 3'];

const styles = {
  section: {
    padding: '20px',
    borderRadius: '18px',
    border: '1px solid #dbe7f2',
    background: 'linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(248,250,252,0.96) 100%)',
    boxShadow: '0 10px 24px rgba(15, 23, 42, 0.05)',
  },
  header: {
    marginBottom: '20px',
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
  subjectBadge: {
    display: 'inline-flex',
    alignItems: 'center',
    marginTop: '10px',
    padding: '8px 12px',
    borderRadius: '999px',
    background: '#dbeafe',
    color: '#1d4ed8',
    fontWeight: 700,
    fontSize: '0.92rem',
  },
  gradeGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))',
    gap: '12px',
    marginBottom: '18px',
  },
  gradeButton: {
    padding: '14px 16px',
    borderRadius: '14px',
    border: '1px solid #cbd5e1',
    background: '#ffffff',
    color: '#0f172a',
    fontWeight: 700,
    cursor: 'pointer',
    transition: 'all 0.2s ease',
  },
  gradeButtonActive: {
    borderColor: '#2563eb',
    background: '#2563eb',
    color: '#ffffff',
    boxShadow: '0 10px 20px rgba(37, 99, 235, 0.18)',
  },
  termGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(120px, 1fr))',
    gap: '12px',
    marginBottom: '18px',
  },
  controlsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
    gap: '14px',
    marginBottom: '18px',
  },
  field: {
    display: 'grid',
    gap: '8px',
  },
  label: {
    fontWeight: 700,
    color: '#0f172a',
    fontSize: '0.95rem',
  },
  select: {
    minHeight: '46px',
    borderRadius: '12px',
    border: '1px solid #cbd5e1',
    padding: '0 14px',
    background: '#ffffff',
    color: '#0f172a',
    fontSize: '0.95rem',
  },
  classSection: {
    marginTop: '18px',
    padding: '16px',
    borderRadius: '16px',
    border: '1px solid #dbe7f2',
    background: '#f8fbff',
  },
  classTitle: {
    margin: 0,
    color: '#0f172a',
    fontWeight: 800,
    fontSize: '1.05rem',
  },
  classMeta: {
    margin: '6px 0 0',
    color: '#64748b',
    fontWeight: 600,
  },
  tableWrap: {
    marginTop: '14px',
    overflowX: 'auto',
  },
  table: {
    width: '100%',
    borderCollapse: 'separate',
    borderSpacing: 0,
    background: '#ffffff',
    border: '1px solid #dbe7f2',
    borderRadius: '14px',
    overflow: 'hidden',
  },
  th: {
    textAlign: 'left',
    padding: '14px 16px',
    background: '#e8f0fb',
    color: '#1e293b',
    fontWeight: 800,
    fontSize: '0.93rem',
    borderBottom: '1px solid #dbe7f2',
  },
  td: {
    padding: '14px 16px',
    borderBottom: '1px solid #e2e8f0',
    color: '#0f172a',
    fontWeight: 600,
    verticalAlign: 'middle',
  },
  marksInput: {
    width: '120px',
    minHeight: '40px',
    borderRadius: '10px',
    border: '1px solid #cbd5e1',
    padding: '0 12px',
    fontSize: '0.95rem',
    color: '#0f172a',
    background: '#ffffff',
  },
  actions: {
    marginTop: '20px',
    display: 'flex',
    justifyContent: 'flex-end',
  },
  saveButton: {
    minWidth: '180px',
    minHeight: '46px',
    borderRadius: '12px',
    border: 'none',
    background: '#2563eb',
    color: '#ffffff',
    fontWeight: 800,
    cursor: 'pointer',
    boxShadow: '0 14px 24px rgba(37, 99, 235, 0.22)',
  },
  disabledButton: {
    opacity: 0.6,
    cursor: 'not-allowed',
    boxShadow: 'none',
  },
  message: {
    marginTop: '14px',
    padding: '12px 14px',
    borderRadius: '12px',
    fontWeight: 700,
  },
  success: {
    background: '#ecfdf5',
    color: '#166534',
    border: '1px solid #bbf7d0',
  },
  error: {
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
};

const normalizeGrade = (grade) => {
  const value = String(grade || '').trim();
  if (!value) return '';
  return value.toLowerCase().startsWith('grade ') ? value.replace(/^grade\s+/i, 'Grade ') : `Grade ${value}`;
};

const normalizeTerm = (term) => {
  const value = String(term || '').trim();
  if (!value) return '';
  return /^term\s*\d+$/i.test(value) ? value.replace(/^term\s*/i, 'Term ') : value;
};

const ExaminationPanel = ({ tutorId }) => {
  const [optionsLoading, setOptionsLoading] = useState(true);
  const [studentsLoading, setStudentsLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [subject, setSubject] = useState('');
  const [allExams, setAllExams] = useState([]);
  const [selectedGrade, setSelectedGrade] = useState('Grade 6');
  const [selectedTerm, setSelectedTerm] = useState('Term 1');
  const [selectedExamId, setSelectedExamId] = useState('');
  const [selectedClass, setSelectedClass] = useState('');
  const [availableClasses, setAvailableClasses] = useState([]);
  const [students, setStudents] = useState([]);
  const [marksByStudent, setMarksByStudent] = useState({});

  useEffect(() => {
    if (!tutorId) {
      setOptionsLoading(false);
      setError('Exam & Results is not available yet.');
      return;
    }

    const fetchOptions = async () => {
      try {
        setOptionsLoading(true);
        const response = await axios.get(`/api/tutor/examination-options.php?tutor_id=${tutorId}`);
        const data = response.data?.data || {};
        const exams = Array.isArray(data.exams) ? data.exams : [];

        setSubject(data.subject || '');
        setAllExams(exams);
        setError('');
      } catch (err) {
        setError(err.response?.data?.error || 'Failed to load examinations.');
      } finally {
        setOptionsLoading(false);
      }
    };

    fetchOptions();
  }, [tutorId]);

  const filteredExams = useMemo(
    () => allExams.filter(
      (exam) => normalizeGrade(exam.grade) === selectedGrade && normalizeTerm(exam.term) === selectedTerm
    ),
    [allExams, selectedGrade, selectedTerm]
  );

  useEffect(() => {
    if (filteredExams.length === 0) {
      setSelectedExamId('');
      setSelectedClass('');
      return;
    }

    const examExists = filteredExams.some((exam) => String(exam.id) === String(selectedExamId));
    if (!examExists) {
      setSelectedExamId(String(filteredExams[0].id));
      setSelectedClass('');
    }
  }, [filteredExams, selectedExamId]);

  useEffect(() => {
    const fetchStudents = async () => {
      try {
        setStudentsLoading(true);
        setError('');
        setSuccess('');

        if (filteredExams.length === 0) {
          const response = await axios.get(`/api/tutor/examination-grade-students.php?tutor_id=${tutorId}&grade=${encodeURIComponent(selectedGrade)}`);
          const data = response.data?.data || {};
          const fetchedStudents = Array.isArray(data.students) ? data.students : [];
          const classNames = Array.isArray(data.available_classes) ? data.available_classes : [];

          setAvailableClasses(classNames);
          setStudents(fetchedStudents);
          setMarksByStudent({});
          return;
        }

        if (!selectedExamId) {
          setStudents([]);
          setAvailableClasses([]);
          setMarksByStudent({});
          return;
        }

        const query = new URLSearchParams({
          tutor_id: String(tutorId),
          exam_id: String(selectedExamId),
        });
        if (selectedClass) {
          query.set('class_name', selectedClass);
        }

        const response = await axios.get(`/api/tutor/examination-students.php?${query.toString()}`);
        const data = response.data?.data || {};
        const fetchedStudents = Array.isArray(data.students) ? data.students : [];
        const nextClasses = Array.isArray(data.available_classes) ? data.available_classes : [];

        setAvailableClasses(nextClasses);
        setStudents(fetchedStudents);
        setMarksByStudent(
          fetchedStudents.reduce((accumulator, row) => {
            accumulator[row.student_id] = row.marks === '' || row.marks === null ? '' : String(row.marks);
            return accumulator;
          }, {})
        );
      } catch (err) {
        setStudents([]);
        setMarksByStudent({});
        setAvailableClasses([]);
        setError(err.response?.data?.error || 'Failed to load students for this grade.');
      } finally {
        setStudentsLoading(false);
      }
    };

    if (!tutorId) {
      return;
    }

    fetchStudents();
  }, [filteredExams, selectedExamId, selectedClass, tutorId, selectedGrade]);
  const groupedStudents = useMemo(() => [
    {
      className: '',
      rows: [...students].sort((a, b) => a.student_name.localeCompare(b.student_name)),
    },
  ], [students]);

  const handleMarksChange = (studentId, value) => {
    const nextValue = value.replace(/[^\d.]/g, '');

    if (nextValue === '') {
      setMarksByStudent((current) => ({
        ...current,
        [studentId]: '',
      }));
      setError('');
      setSuccess('');
      return;
    }

    if (!/^\d+(\.\d{0,2})?$/.test(nextValue)) {
      return;
    }

    const numericValue = Number(nextValue);
    if (Number.isNaN(numericValue) || numericValue < 0 || numericValue > 100) {
      setError('Marks must be between 0 and 100.');
      return;
    }

    setMarksByStudent((current) => ({
      ...current,
      [studentId]: nextValue,
    }));
    setError('');
    setSuccess('');
  };

  const handleSave = async () => {
    const marksPayload = students
      .map((student) => ({
        student_id: student.student_id,
        marks: String(marksByStudent[student.student_id] ?? '').trim(),
      }))
      .filter((row) => row.marks !== '');

    if (!selectedExamId) {
      setError(hasActiveExam ? 'Please select an exam first.' : 'No examination is available for this grade and term.');
      return;
    }

    if (marksPayload.length === 0) {
      setError('Please enter marks before saving.');
      return;
    }

    const hasInvalidMarks = marksPayload.some((row) => {
      if (!/^\d+(\.\d+)?$/.test(row.marks)) {
        return true;
      }
      const numericValue = Number(row.marks);
      return Number.isNaN(numericValue) || numericValue < 0 || numericValue > 100;
    });

    if (hasInvalidMarks) {
      setError('Marks must be numeric and between 0 and 100.');
      return;
    }

    try {
      setSaving(true);
      setError('');
      setSuccess('');

      const response = await axios.post(`/api/tutor/examination-marks.php?tutor_id=${tutorId}`, {
        exam_id: Number(selectedExamId),
        marks: marksPayload,
      });

      setSuccess(response.data?.data?.message || 'Marks saved successfully.');
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to save marks.');
    } finally {
      setSaving(false);
    }
  };

  const hasActiveExam = filteredExams.length > 0 && selectedExamId;

  const allStudentsMarked = students.length > 0 && students.every((student) => {
    const value = String(marksByStudent[student.student_id] ?? '').trim();
    if (value === '' || !/^\d+(\.\d+)?$/.test(value)) {
      return false;
    }

    const numericValue = Number(value);
    return !Number.isNaN(numericValue) && numericValue >= 0 && numericValue <= 100;
  });

  const canSubmitMarks = hasActiveExam && allStudentsMarked;

  return (
    <section style={styles.section}>
      <div style={styles.header}>
        <h3 style={styles.title}>Exam & Results Mark Entry</h3>
        <p style={styles.subtitle}>
          Click a grade to see all registered students. When an exam exists for the selected term, you can update only your own subject marks.
        </p>
        {subject ? <div style={styles.subjectBadge}>Subject: {subject}</div> : null}
      </div>

      <div style={styles.gradeGrid}>
        {TARGET_GRADES.map((grade) => (
          <button
            key={grade}
            type="button"
            style={{
              ...styles.gradeButton,
              ...(selectedGrade === grade ? styles.gradeButtonActive : {}),
            }}
            onClick={() => {
              setSelectedGrade(grade);
              setSelectedClass('');
              setError('');
              setSuccess('');
            }}
          >
            {grade}
          </button>
        ))}
      </div>

      <div style={styles.termGrid}>
        {TERM_OPTIONS.map((term) => (
          <button
            key={term}
            type="button"
            style={{
              ...styles.gradeButton,
              ...(selectedTerm === term ? styles.gradeButtonActive : {}),
            }}
            onClick={() => {
              setSelectedTerm(term);
              setSelectedClass('');
              setError('');
              setSuccess('');
            }}
          >
            {term}
          </button>
        ))}
      </div>

      {optionsLoading ? (
        <div style={styles.empty}>Loading examinations...</div>
      ) : (
        <>
          {hasActiveExam ? (
            <div style={styles.controlsGrid}>
              <div style={styles.field}>
                <label style={styles.label} htmlFor="tutor-exam-select">Select Exam</label>
                <select
                  id="tutor-exam-select"
                  style={styles.select}
                  value={selectedExamId}
                  onChange={(event) => {
                    setSelectedExamId(event.target.value);
                    setSelectedClass('');
                    setError('');
                    setSuccess('');
                  }}
                >
                  {filteredExams.map((exam) => (
                    <option key={exam.id} value={exam.id}>
                      {exam.exam_name} ({normalizeTerm(exam.term)} - {exam.exam_date})
                    </option>
                  ))}
                </select>
              </div>
            </div>
          ) : null}

          {studentsLoading ? (
            <div style={styles.empty}>Loading students...</div>
          ) : groupedStudents.length === 0 ? (
            <div style={styles.empty}>No registered students found for {selectedGrade}.</div>
          ) : (
            <>
              {groupedStudents.map((classGroup) => (
                <div key={`${selectedGrade}-${selectedTerm}`} style={styles.classSection}>
                  <p style={styles.classMeta}>
                    {hasActiveExam ? `${selectedGrade} | ${selectedTerm} | ${subject || 'your subject'}` : `${selectedGrade} registered students`}
                  </p>

                  <div style={styles.tableWrap}>
                    <table style={styles.table}>
                      <thead>
                        <tr>
                          <th style={styles.th}>Student Name</th>
                          <th style={styles.th}>{hasActiveExam ? (subject || 'Marks') : 'Marks'}</th>
                        </tr>
                      </thead>
                      <tbody>
                        {classGroup.rows.map((student, index) => (
                          <tr key={student.student_id}>
                            <td
                              style={{
                                ...styles.td,
                                ...(index === classGroup.rows.length - 1 ? { borderBottom: 'none' } : {}),
                              }}
                            >
                              {student.student_name}
                            </td>
                            <td
                              style={{
                                ...styles.td,
                                ...(index === classGroup.rows.length - 1 ? { borderBottom: 'none' } : {}),
                              }}
                            >
                              <input
                                type="number"
                                inputMode="decimal"
                                min="0"
                                max="100"
                                step="0.01"
                                placeholder="0 - 100"
                                value={marksByStudent[student.student_id] ?? ''}
                                onChange={(event) => handleMarksChange(student.student_id, event.target.value)}
                                style={{
                                  ...styles.marksInput,
                                  ...(hasActiveExam ? {} : { opacity: 0.85 }),
                                }}
                              />
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              ))}

              <div style={styles.actions}>
                <button
                  type="button"
                  style={{
                    ...styles.saveButton,
                    ...(saving ? styles.disabledButton : {}),
                  }}
                  onClick={handleSave}
                  disabled={saving}
                  title={saving ? 'Submitting marks...' : ''}
                >
                  {saving ? 'Submitting...' : `Submit ${subject || ''} Marks`.trim()}
                </button>
              </div>
            </>
          )}

          {error ? <div style={{ ...styles.message, ...styles.error }}>{error}</div> : null}
          {success ? <div style={{ ...styles.message, ...styles.success }}>{success}</div> : null}
        </>
      )}
    </section>
  );
};

export default ExaminationPanel;
























