import { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from '../api/axios';

const emptyEditor = {
  source_day: '',
  source_time: '',
  day: '',
  time: '',
  grade: '',
  teacher_id: '',
  subject: '',
  room: ''
};

const renderTimetableModal = (content) => {
  if (typeof document === 'undefined') {
    return null;
  }

  return createPortal(content, document.body);
};

const AdminTimetable = () => {
  const [grades, setGrades] = useState([]);
  const [teachers, setTeachers] = useState([]);
  const [subjects, setSubjects] = useState([]);
  const [rooms, setRooms] = useState([]);
  const [days, setDays] = useState([]);
  const [timeSlots, setTimeSlots] = useState([]);
  const [dayTimeSlots, setDayTimeSlots] = useState({});
  const [entries, setEntries] = useState([]);
  const [grade, setGrade] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [editor, setEditor] = useState(emptyEditor);

  const isModalOpen = Boolean(editor.day && editor.time);

  const fetchTimetable = async ({ gradeValue } = {}) => {
    const nextGrade = gradeValue ?? grade;

    setLoading(true);
    setError('');
    try {
      const params = new URLSearchParams();
      if (nextGrade) params.append('grade', nextGrade);

      const query = params.toString();
      const res = await axios.get(query ? `/api/admin/timetable.php?${query}` : '/api/admin/timetable.php');
      const data = res.data.data;
      setGrades(data.grades);
      setTeachers(data.teachers);
      setSubjects(data.subjects);
      setRooms(data.rooms);
      setDays(data.days);
      setTimeSlots(data.time_slots);
      setDayTimeSlots(data.day_time_slots || {});
      setEntries(data.entries);
      setGrade(data.selected_grade);
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to load timetable');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTimetable({ gradeValue: '' });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const teacherOptions = useMemo(() => teachers, [teachers]);
  const filteredTeacherOptions = useMemo(() => {
    if (!editor.subject) {
      return teacherOptions;
    }

    return teacherOptions.filter((item) => String(item.subject || '').trim() === String(editor.subject).trim());
  }, [editor.subject, teacherOptions]);

  const timetableMap = useMemo(() => {
    const map = {};
    entries.forEach((entry) => {
      map[`${entry.time}_${entry.day}`] = entry;
    });
    return map;
  }, [entries]);

  const selectedTeacher = useMemo(
    () => teacherOptions.find((item) => item.id === editor.teacher_id),
    [editor.teacher_id, teacherOptions]
  );

  const selectedSlotConflict = useMemo(() => {
    if (!editor.day || !editor.time) {
      return null;
    }

    const entry = timetableMap[`${editor.time}_${editor.day}`];
    if (!entry) {
      return null;
    }

    if (editor.source_day === editor.day && editor.source_time === editor.time && editor.grade === grade) {
      return null;
    }

    return entry;
  }, [editor.day, editor.source_day, editor.source_time, editor.time, editor.grade, grade, timetableMap]);

  const handleGradeChange = (value) => {
    setGrade(value);
    fetchTimetable({ gradeValue: value });
  };

  const openCellEditor = (day, time) => {
    setSuccess('');
    const entry = timetableMap[`${time}_${day}`];
    setEditor(
      entry
        ? {
            source_day: day,
            source_time: time,
            day,
            time,
            grade,
            teacher_id: entry.teacher_id,
            subject: entry.subject,
            room: entry.room
          }
        : {
            ...emptyEditor,
            source_day: day,
            source_time: time,
            day,
            time,
            grade
          }
    );
  };

  const closeEditor = () => {
    setEditor(emptyEditor);
    setError('');
  };

  const handleTeacherSelect = (teacherId) => {
    const match = teacherOptions.find((item) => item.id === teacherId);
    setEditor((current) => ({
      ...current,
      teacher_id: teacherId,
      subject: current.subject || match?.subject || ''
    }));
  };

  const handleSubjectChange = (subject) => {
    setEditor((current) => {
      const matchingTeacher = teacherOptions.find((item) => item.id === current.teacher_id);
      const keepTeacher = matchingTeacher && String(matchingTeacher.subject || '').trim() === String(subject).trim();

      return {
        ...current,
        subject,
        teacher_id: keepTeacher ? current.teacher_id : '',
      };
    });
  };

  const saveCell = async () => {
    if (!editor.day || !editor.time) {
      setError('Select a timetable cell first');
      return;
    }

    if (selectedSlotConflict) {
      setError('This class already has a subject in that selected slot');
      return;
    }

    setSaving(true);
    setError('');
    setSuccess('');
    try {
      const payload = {
        grade: editor.grade || grade,
        original_day: editor.source_day || editor.day,
        original_time: editor.source_time || editor.time,
        day: editor.day,
        time: editor.time,
        teacher_id: editor.teacher_id,
        subject: editor.subject,
        room: editor.room
      };
      const res = await axios.post('/api/admin/timetable-save.php', payload);
      setSuccess(res.data.data?.message || 'Timetable updated');
      await fetchTimetable({ gradeValue: editor.grade || grade });
      setGrade(editor.grade || grade);
      closeEditor();
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to save timetable cell');
    } finally {
      setSaving(false);
    }
  };

  const clearCell = async () => {
    if (!editor.day || !editor.time) {
      setError('Select a timetable cell first');
      return;
    }

    setSaving(true);
    setError('');
    setSuccess('');
    try {
      const res = await axios.post('/api/admin/timetable-save.php', {
        grade: editor.grade || grade,
        day: editor.day,
        time: editor.time,
        teacher_id: '',
        subject: '',
        room: ''
      });
      setSuccess(res.data.data?.message || 'Timetable cell cleared');
      await fetchTimetable({ gradeValue: editor.grade || grade });
      setGrade(editor.grade || grade);
      closeEditor();
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to clear timetable cell');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="container">
      <div className="form-box dashboard-shell dashboard-xwide">
        <div className="dashboard-header">
          <div>
            <h2>Institution Timetable</h2>
            <p className="dashboard-subtitle">Use the fixed weekly time slots, then assign a teacher, subject, and room to each class period.</p>
          </div>
        </div>

        {error && !isModalOpen && <div className="error">{error}</div>}
        {success && <div className="success">{success}</div>}

        <div className="timetable-toolbar">
          <div className="filter-group">
            <label htmlFor="grade-select">Select Grade</label>
            <select id="grade-select" name="grade" value={grade} onChange={(e) => handleGradeChange(e.target.value)}>
              {grades.map((item) => (
                <option key={item} value={item}>{item}</option>
              ))}
            </select>
          </div>
        </div>

        {loading ? (
          <div className="dashboard-empty">Loading timetable...</div>
        ) : (
          <>
            <div className="timetable-summary">
              <div className="summary-card">
                <span className="summary-label">Grade</span>
                <strong>{grade}</strong>
              </div>
              <div className="summary-card">
                <span className="summary-label">Scheduled Slots</span>
                <strong>{entries.length}</strong>
              </div>
            </div>

            <div className="table-card timetable-card">
              <div className="timetable-scroll">
                <table className="dashboard-table timetable-table">
                  <thead>
                    <tr>
                      <th>Time</th>
                      {days.map((day) => (
                        <th key={day}>{day}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {timeSlots.map((time) => (
                      <tr key={time}>
                        <td className="time-slot">{time}</td>
                        {days.map((day) => {
                          const isAvailable = (dayTimeSlots[day] || []).includes(time);
                          const entry = timetableMap[`${time}_${day}`];
                          const isSelected = editor.day === day && editor.time === time;

                          return (
                            <td key={`${time}-${day}`}>
                              {isAvailable ? (
                                <button
                                  type="button"
                                  className={`timetable-cell-button${isSelected ? ' selected' : ''}`}
                                  onClick={() => openCellEditor(day, time)}
                                >
                                  {entry ? (
                                    <div className="timetable-cell">
                                      <span className="timetable-subject">{entry.subject}</span>
                                      <span className="timetable-teacher">{entry.teacher}</span>
                                      <span className="timetable-room">{entry.room}</span>
                                    </div>
                                  ) : (
                                    <span className="timetable-empty">Click to assign</span>
                                  )}
                                </button>
                              ) : (
                                <div className="timetable-unavailable">Not available</div>
                              )}
                            </td>
                          );
                        })}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            {isModalOpen && renderTimetableModal(
              <div className="timetable-modal-overlay" onClick={closeEditor}>
                <div
                  className="timetable-modal"
                  onClick={(e) => e.stopPropagation()}
                  role="dialog"
                  aria-modal="true"
                  aria-labelledby="timetable-modal-title"
                >
                  <div className="timetable-modal-header">
                    <div>
                      <p className="timetable-modal-eyebrow">Timetable Editor</p>
                      <h3 id="timetable-modal-title">Edit Timetable Slot</h3>
                    </div>
                    <button type="button" className="timetable-modal-close" onClick={closeEditor} aria-label="Close timetable editor">
                      ×
                    </button>
                  </div>

                  <div className="dashboard-edit-card">
                    {error ? <div className="error">{error}</div> : null}

                    <div className="timetable-modal-grid">
                      <div className="filter-group">
                        <label htmlFor="modal-selected-day">Selected Day</label>
                        <input id="modal-selected-day" type="text" value={editor.day} readOnly />
                      </div>

                      <div className="filter-group">
                        <label htmlFor="modal-selected-time">Selected Time Slot</label>
                        <input id="modal-selected-time" type="text" value={editor.time} readOnly />
                      </div>

                      <div className="filter-group">
                        <label htmlFor="modal-class">Class</label>
                        <select id="modal-class" value={editor.grade} onChange={(e) => setEditor((current) => ({ ...current, grade: e.target.value }))}>
                          {grades.map((item) => (
                            <option key={item} value={item}>{item}</option>
                          ))}
                        </select>
                      </div>

                      <div className="filter-group">
                        <label htmlFor="modal-subject">Subject</label>
                        <select id="modal-subject" value={editor.subject} onChange={(e) => handleSubjectChange(e.target.value)}>
                          <option value="">Select Subject</option>
                          {subjects.map((item) => (
                            <option key={item} value={item}>{item}</option>
                          ))}
                        </select>
                      </div>

                      <div className="filter-group">
                        <label htmlFor="modal-tutor">Tutor</label>
                        <select id="modal-tutor" value={editor.teacher_id} onChange={(e) => handleTeacherSelect(e.target.value)}>
                          <option value="">Select Tutor</option>
                          {filteredTeacherOptions.map((item) => (
                            <option key={item.id} value={item.id}>{item.name}</option>
                          ))}
                        </select>
                      </div>

                      <div className="filter-group">
                        <label htmlFor="modal-room">Room</label>
                        <select id="modal-room" value={editor.room} onChange={(e) => setEditor((current) => ({ ...current, room: e.target.value }))}>
                          <option value="">Select Room</option>
                          {rooms.map((item) => (
                            <option key={item} value={item}>{item}</option>
                          ))}
                        </select>
                      </div>
                    </div>

                    {selectedSlotConflict && (
                      <div className="timetable-conflict">
                        This slot is already booked for {selectedSlotConflict.subject} with {selectedSlotConflict.teacher}.
                      </div>
                    )}

                    <div className="profile-footer">
                      <button type="button" onClick={saveCell} disabled={saving}>
                        {saving ? 'Saving...' : 'Save'}
                      </button>
                      <button type="button" className="secondary-button" onClick={closeEditor} disabled={saving}>
                        Cancel
                      </button>
                      <button type="button" className="danger-button" onClick={clearCell} disabled={saving}>
                        Clear Cell
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
};

export default AdminTimetable;

