import { useEffect, useMemo, useState } from 'react';
import axios from '../api/axios';

const emptyEditor = {
  source_day: '',
  source_time: '',
  day: '',
  time: '',
  teacher_id: '',
  subject: '',
  room: ''
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
  const [teacher, setTeacher] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [editor, setEditor] = useState(emptyEditor);

  const fetchTimetable = async ({ gradeValue, teacherValue, preserveEditor = false } = {}) => {
    const nextGrade = gradeValue ?? grade;
    const nextTeacher = teacherValue ?? teacher;

    setLoading(true);
    setError('');
    try {
      const params = new URLSearchParams();
      if (nextGrade) params.append('grade', nextGrade);
      if (nextTeacher) params.append('teacher', nextTeacher);

      const res = await axios.get(`/admin/timetable?${params.toString()}`);
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
      setTeacher(data.selected_teacher);

      if (!preserveEditor) {
        setEditor((current) => {
          if (!current.day || !current.time) {
            return current;
          }

          const match = data.entries.find((entry) => entry.day === current.day && entry.time === current.time);
          return match
            ? {
                source_day: match.day,
                source_time: match.time,
                day: match.day,
                time: match.time,
                teacher_id: match.teacher_id,
                subject: match.subject,
                room: match.room
              }
            : {
                ...emptyEditor,
                day: current.day,
                time: current.time,
                source_day: current.day,
                source_time: current.time
              };
        });
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to load timetable');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTimetable({ gradeValue: '', teacherValue: '' });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const teacherOptions = useMemo(() => teachers, [teachers]);

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

  const selectedDaySlots = useMemo(() => {
    return editor.day ? dayTimeSlots[editor.day] || [] : [];
  }, [dayTimeSlots, editor.day]);

  const selectedSlotConflict = useMemo(() => {
    if (!editor.day || !editor.time) {
      return null;
    }

    const entry = timetableMap[`${editor.time}_${editor.day}`];
    if (!entry) {
      return null;
    }

    if (editor.source_day === editor.day && editor.source_time === editor.time) {
      return null;
    }

    return entry;
  }, [editor.day, editor.source_day, editor.source_time, editor.time, timetableMap]);

  const handleGradeChange = (value) => {
    setGrade(value);
    fetchTimetable({ gradeValue: value, teacherValue: teacher });
  };

  const handleTeacherFilterChange = (value) => {
    setTeacher(value);
    fetchTimetable({ gradeValue: grade, teacherValue: value });
  };

  const handleDaySelect = (value) => {
    const nextSlots = dayTimeSlots[value] || [];
    setSuccess('');
    setEditor((current) => ({
      ...current,
      day: value,
      time: nextSlots.includes(current.time) ? current.time : '',
      teacher_id: current.teacher_id,
      subject: current.subject,
      room: current.room
    }));
  };

  const openCellEditor = (day, time) => {
    if (!(dayTimeSlots[day] || []).includes(time)) {
      return;
    }

    setSuccess('');
    const entry = timetableMap[`${time}_${day}`];
    setEditor(
      entry
        ? {
            source_day: day,
            source_time: time,
            day,
            time,
            teacher_id: entry.teacher_id,
            subject: entry.subject,
            room: entry.room
          }
        : {
            ...emptyEditor,
            source_day: day,
            source_time: time,
            day,
            time
          }
    );
  };

  const handleTeacherSelect = (teacherId) => {
    const match = teacherOptions.find((item) => item.id === teacherId);
    setEditor((current) => ({
      ...current,
      teacher_id: teacherId,
      subject: current.subject || match?.subject || ''
    }));
  };

  const saveCell = async () => {
    if (!editor.day || !editor.time) {
      setError('Select a day and available time slot first');
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
        grade,
        original_day: editor.source_day || editor.day,
        original_time: editor.source_time || editor.time,
        day: editor.day,
        time: editor.time,
        teacher_id: editor.teacher_id,
        subject: editor.subject,
        room: editor.room
      };
      const res = await axios.post('/admin/timetable', payload);
      setSuccess(res.data.data?.message || 'Timetable updated');
      await fetchTimetable({ gradeValue: grade, teacherValue: teacher, preserveEditor: false });
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to save timetable cell');
    } finally {
      setSaving(false);
    }
  };

  const clearCell = async () => {
    if (!editor.day || !editor.time) {
      setError('Select a timetable slot first');
      return;
    }

    setSaving(true);
    setError('');
    setSuccess('');
    try {
      const res = await axios.post('/admin/timetable', {
        grade,
        day: editor.day,
        time: editor.time,
        teacher_id: '',
        subject: '',
        room: ''
      });
      setSuccess(res.data.data?.message || 'Timetable cell cleared');
      setEditor({
        ...emptyEditor,
        day: editor.day,
        time: editor.time,
        source_day: editor.day,
        source_time: editor.time
      });
      await fetchTimetable({ gradeValue: grade, teacherValue: teacher, preserveEditor: false });
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

        {error && <div className="error">{error}</div>}
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

          <div className="filter-group">
            <label htmlFor="teacher-select">Teacher Filter</label>
            <select id="teacher-select" name="teacher" value={teacher} onChange={(e) => handleTeacherFilterChange(e.target.value)}>
              <option value="">All Teachers</option>
              {teacherOptions.map((item) => (
                <option key={item.id} value={item.id}>{item.name} - {item.subject}</option>
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
                <span className="summary-label">Teacher Filter</span>
                <strong>{teacher ? teacherOptions.find((item) => item.id === teacher)?.name || 'Selected Teacher' : 'All Teachers'}</strong>
              </div>
              <div className="summary-card">
                <span className="summary-label">Scheduled Slots</span>
                <strong>{entries.length}</strong>
              </div>
            </div>

            <div className="timetable-editor-card">
              <div className="timetable-editor-header">
                <div>
                  <span className="summary-label">Selected Slot</span>
                  <strong>{editor.day && editor.time ? `${editor.day} • ${editor.time}` : 'Choose a cell from the timetable'}</strong>
                </div>
                {editor.day && editor.time && (
                  <span className="role-pill">{grade}</span>
                )}
              </div>
              <div className="timetable-editor-grid">
                <div className="filter-group">
                  <label htmlFor="cell-day">Day</label>
                  <select
                    id="cell-day"
                    name="cell_day"
                    value={editor.day}
                    onChange={(e) => handleDaySelect(e.target.value)}
                  >
                    <option value="">Select Day</option>
                    {days.map((item) => (
                      <option key={item} value={item}>{item}</option>
                    ))}
                  </select>
                </div>

                <div className="filter-group">
                  <label htmlFor="cell-time">Available Time Slot</label>
                  <select
                    id="cell-time"
                    name="cell_time"
                    value={editor.time}
                    onChange={(e) => setEditor((current) => ({ ...current, time: e.target.value }))}
                    disabled={!editor.day}
                  >
                    <option value="">Select Time Slot</option>
                    {selectedDaySlots.map((item) => (
                      <option key={item} value={item}>{item}</option>
                    ))}
                  </select>
                </div>

                <div className="filter-group">
                  <label htmlFor="cell-teacher">Teacher</label>
                  <select
                    id="cell-teacher"
                    name="cell_teacher"
                    value={editor.teacher_id}
                    onChange={(e) => handleTeacherSelect(e.target.value)}
                    disabled={!editor.day || !editor.time}
                  >
                    <option value="">Select Teacher</option>
                    {teacherOptions.map((item) => (
                      <option key={item.id} value={item.id}>{item.name}</option>
                    ))}
                  </select>
                </div>

                <div className="filter-group">
                  <label htmlFor="cell-subject">Subject</label>
                  <select
                    id="cell-subject"
                    name="cell_subject"
                    value={editor.subject}
                    onChange={(e) => setEditor((current) => ({ ...current, subject: e.target.value }))}
                    disabled={!editor.day || !editor.time}
                  >
                    <option value="">Select Subject</option>
                    {subjects.map((item) => (
                      <option key={item} value={item}>{item}</option>
                    ))}
                  </select>
                </div>

                <div className="filter-group">
                  <label htmlFor="cell-room">Room</label>
                  <select
                    id="cell-room"
                    name="cell_room"
                    value={editor.room}
                    onChange={(e) => setEditor((current) => ({ ...current, room: e.target.value }))}
                    disabled={!editor.day || !editor.time}
                  >
                    <option value="">Select Room</option>
                    {rooms.map((item) => (
                      <option key={item} value={item}>{item}</option>
                    ))}
                  </select>
                </div>

                <div className="summary-card timetable-teacher-card">
                  <span className="summary-label">Class Preview</span>
                  <strong>{editor.day && editor.time ? `${editor.day} • ${editor.time}` : 'Choose a timetable period'}</strong>
                  <small className="timetable-preview-note">{selectedTeacher?.subject || 'Choose a teacher to use the suggested subject'}</small>
                </div>
              </div>
              {selectedSlotConflict && (
                <div className="timetable-conflict">
                  This slot is already booked for {selectedSlotConflict.subject} with {selectedSlotConflict.teacher}.
                </div>
              )}
              <div className="profile-footer">
                <button type="button" onClick={saveCell} disabled={saving || !editor.day || !editor.time}>Save Cell</button>
                <button type="button" className="secondary-button" onClick={() => setEditor(emptyEditor)} disabled={saving}>Cancel</button>
                <button type="button" className="danger-button" onClick={clearCell} disabled={saving || !editor.day || !editor.time}>Clear Cell</button>
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
          </>
        )}
      </div>
    </div>
  );
};

export default AdminTimetable;
