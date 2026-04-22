import { useEffect, useMemo, useState } from 'react';
import { NavLink } from 'react-router-dom';
import axios from '../../api/axios';
import './AttendancePanel.css';

const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const MONTH_LABELS = [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
];

const formatDateKey = (date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const normalizeStatus = (status) => {
  const value = String(status || '').toLowerCase();
  if (value.includes('late')) return 'late';
  if (value === 'present' || value === 'excused') return 'present';
  if (value === 'absent') return 'absent';
  return 'none';
};

const formatClock = (date) =>
  date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });

const formatDateLabel = (date) =>
  date.toLocaleDateString([], { weekday: 'long', day: 'numeric', month: 'long' });

const initials = (name) => {
  const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return 'ST';
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
};

const ringDotPosition = (progress) => {
  const clamped = Math.max(0, Math.min(100, progress));
  const angle = Math.PI * (1 - clamped / 100);
  const radius = 90;
  const cx = 120 + radius * Math.cos(angle);
  const cy = 130 - radius * Math.sin(angle);
  return { cx, cy };
};

const StudentAttendancePanel = ({ studentId, user }) => {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [now, setNow] = useState(new Date());
  const [isHistoryModalOpen, setIsHistoryModalOpen] = useState(false);
  const [viewedMonth, setViewedMonth] = useState(() => {
    const base = new Date();
    return new Date(base.getFullYear(), base.getMonth(), 1);
  });

  useEffect(() => {
    const timer = window.setInterval(() => setNow(new Date()), 1000);
    return () => window.clearInterval(timer);
  }, []);

  useEffect(() => {
    if (!studentId) {
      setLoading(false);
      setError('Attendance is not available yet.');
      return;
    }

    const fetchAttendance = async () => {
      try {
        setLoading(true);
        const res = await axios.get(`/api/student_attendance.php?student_id=${studentId}`);
        setData(res.data || null);
        setError('');
      } catch (err) {
        setError(err.response?.data?.error || 'Failed to load attendance');
      } finally {
        setLoading(false);
      }
    };

    fetchAttendance();
  }, [studentId]);

  const todayKey = formatDateKey(now);
  const history = Array.isArray(data?.history) ? data.history : [];
  const attendancePct = Math.max(0, Math.min(100, Number(data?.attendance_percentage || 0)));
  const totalWorking = Number(data?.total_classes_held || 0);
  const totalPresent = Number(data?.total_present || 0);
  const totalAbsent = Number(data?.total_absent || 0);

  const todayEntry = history.find((row) => String(row?.date || '') === todayKey);
  const todayStatus = normalizeStatus(todayEntry?.status);
  const checkOut = todayEntry?.check_out || (todayStatus === 'present' ? '18:00:00' : '--:--:--');

  const monthCells = useMemo(() => {
    const year = viewedMonth.getFullYear();
    const month = viewedMonth.getMonth();
    const firstDay = new Date(year, month, 1);
    const firstWeekday = firstDay.getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const statusMap = new Map();
    history.forEach((row) => {
      const key = String(row?.date || '');
      if (key) statusMap.set(key, normalizeStatus(row.status));
    });

    const cells = [];
    for (let i = 0; i < firstWeekday; i += 1) {
      cells.push({ key: `pad-start-${i}`, label: '', tone: 'none', muted: true });
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
      const key = formatDateKey(new Date(year, month, day));
      cells.push({
        key,
        label: day,
        tone: statusMap.get(key) || 'none',
        muted: false,
        today: key === todayKey,
      });
    }

    while (cells.length % 7 !== 0) {
      cells.push({ key: `pad-end-${cells.length}`, label: '', tone: 'none', muted: true });
    }

    return cells;
  }, [history, viewedMonth, todayKey]);

  const monthSummary = useMemo(() => {
    const year = viewedMonth.getFullYear();
    const month = viewedMonth.getMonth();
    const selectedRows = history.filter((row) => {
      const value = String(row?.date || '');
      if (!value) return false;
      const dt = new Date(`${value}T00:00:00`);
      return dt.getFullYear() === year && dt.getMonth() === month;
    });

    const present = selectedRows.filter((row) => normalizeStatus(row.status) === 'present').length;
    const absent = selectedRows.filter((row) => normalizeStatus(row.status) === 'absent').length;
    const late = selectedRows.filter((row) => normalizeStatus(row.status) === 'late').length;
    return {
      working: selectedRows.length,
      present,
      absent,
      late,
    };
  }, [history, viewedMonth]);

  const viewedMonthTone = useMemo(() => {
    const current = new Date(now.getFullYear(), now.getMonth(), 1).getTime();
    const selected = new Date(viewedMonth.getFullYear(), viewedMonth.getMonth(), 1).getTime();
    if (selected < current) return 'past';
    if (selected > current) return 'future';
    return 'current';
  }, [now, viewedMonth]);

  const pastMonthGroups = useMemo(() => {
    const currentYear = now.getFullYear();
    const currentMonth = now.getMonth();
    const firstDayOfCurrentMonth = new Date(currentYear, currentMonth, 1);
    const grouped = new Map();
    const fallbackGrouped = new Map();

    history.forEach((row) => {
      const value = String(row?.date || '');
      if (!value) return;
      const dt = new Date(`${value}T00:00:00`);
      if (Number.isNaN(dt.getTime())) return;

      const key = `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}`;
      if (!fallbackGrouped.has(key)) {
        fallbackGrouped.set(key, {
          key,
          year: dt.getFullYear(),
          monthIndex: dt.getMonth(),
          rows: [],
        });
      }
      fallbackGrouped.get(key).rows.push(row);

      if (dt < firstDayOfCurrentMonth) {
        if (!grouped.has(key)) {
          grouped.set(key, {
            key,
            year: dt.getFullYear(),
            monthIndex: dt.getMonth(),
            rows: [],
          });
        }
        grouped.get(key).rows.push(row);
      }
    });

    const sourceGroups = grouped.size > 0
      ? grouped
      : new Map(
          Array.from(fallbackGrouped.entries()).filter(([key]) => {
            const [year, month] = key.split('-');
            const y = Number(year);
            const m = Number(month) - 1;
            return !(y === currentYear && m === currentMonth);
          })
        );

    return Array.from(sourceGroups.values())
      .map((group) => {
        const sortedRows = group.rows.sort((a, b) => String(b.date || '').localeCompare(String(a.date || '')));
        const present = sortedRows.filter((row) => normalizeStatus(row.status) === 'present').length;
        const late = sortedRows.filter((row) => normalizeStatus(row.status) === 'late').length;
        const absentRows = sortedRows.filter((row) => normalizeStatus(row.status) === 'absent');
        const presentDays = present + late;
        const absentDays = absentRows.length;
        const attendanceRate = sortedRows.length > 0
          ? Math.round((presentDays / sortedRows.length) * 100)
          : 0;
        return {
          key: group.key,
          title: `${MONTH_LABELS[group.monthIndex]}, ${group.year}`,
          totalClassDays: sortedRows.length,
          presentDays,
          absentDays,
          attendanceRate,
          absentRows,
        };
      })
      .sort((a, b) => b.key.localeCompare(a.key));
  }, [history, now]);

  const overallPastSummary = useMemo(() => {
    const months = pastMonthGroups.length;
    const totalClassDays = pastMonthGroups.reduce((sum, group) => sum + group.totalClassDays, 0);
    const totalPresentDays = pastMonthGroups.reduce((sum, group) => sum + group.presentDays, 0);
    const avgAttendance = totalClassDays > 0 ? Math.round((totalPresentDays / totalClassDays) * 100) : 0;
    return { months, totalClassDays, avgAttendance };
  }, [pastMonthGroups]);

  const arcLength = Math.PI * 90;
  const progressLength = (arcLength * attendancePct) / 100;
  const dot = ringDotPosition(attendancePct);

  if (loading) {
    return (
      <div className="student-attendance-ux loading">
        <div className="sa-loader" />
        <p>Loading attendance dashboard...</p>
      </div>
    );
  }

  if (error) {
    return <div className="student-attendance-ux error">{error}</div>;
  }

  return (
    <section className="student-attendance-ux">
      <article className="sa-phone-card sa-left">
        <header className="sa-welcome">
          <div>
            <p className="sa-welcome-label">Welcome,</p>
            <h3>{user?.full_name || 'Student'}</h3>
          </div>
          <div className="sa-avatar">{initials(user?.full_name)}</div>
        </header>

        <div className="sa-day-row">{formatDateLabel(now)}</div>

        <div className="sa-clock-ring">
          <svg viewBox="0 0 240 160" role="img" aria-label={`Attendance ${attendancePct}%`}>
            <path className="ring-track" d="M30 130 A90 90 0 1 1 210 130" />
            <path className="ring-value" d="M30 130 A90 90 0 1 1 210 130" style={{ strokeDasharray: `${progressLength} ${arcLength}` }} />
            <circle className="ring-dot" cx={dot.cx} cy={dot.cy} r="9" />
          </svg>
          <div className="sa-clock-center">
            <strong>{formatClock(now)}</strong>
            <span>Today</span>
            <small>
              {todayStatus === 'present' ? `Check out at ${checkOut.slice(0, 5)}` : `Attendance ${attendancePct}%`}
            </small>
          </div>
        </div>

        <nav className="sa-bottom-nav" aria-label="Attendance quick navigation">
          <NavLink to="/student/overview" className="sa-nav-item">
            <svg viewBox="0 0 24 24"><path d="M3 10.5L12 3l9 7.5v9a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 19.5v-9Z" /></svg>
            <span>Home</span>
          </NavLink>
          <NavLink to="/student/attendance" className="sa-nav-center" aria-label="Attendance">
            <svg viewBox="0 0 24 24"><path d="M7 12h10M12 7v10" /></svg>
          </NavLink>
          <button
            type="button"
            className={`sa-nav-item sa-nav-button${isHistoryModalOpen ? ' active' : ''}`}
            onClick={() => setIsHistoryModalOpen(true)}
            aria-label="Open past month attendance history"
          >
            <svg viewBox="0 0 24 24"><path d="M6 4.5h12v15H6zM9 9h6M9 13h6M9 17h4" /></svg>
            <span>History</span>
          </button>
        </nav>
      </article>

      <article className="sa-phone-card sa-right">
        <header className="sa-panel-title">
          <h4>Attendance</h4>
        </header>

        <div className="sa-calendar-card">
          <div className="sa-week-labels">
            {WEEKDAY_LABELS.map((day) => (
              <span key={day}>{day}</span>
            ))}
          </div>
          <div className="sa-calendar-grid">
            {monthCells.map((cell) => (
              <div
                key={cell.key}
                className={`sa-calendar-day ${cell.muted ? 'muted' : ''} tone-${cell.tone} ${cell.today ? 'today' : ''}`}
              >
                {cell.label}
              </div>
            ))}
          </div>
        </div>

        <div className="sa-month-row">
          <button
            type="button"
            className="sa-arrow"
            aria-label="Previous month"
            onClick={() => setViewedMonth((prev) => new Date(prev.getFullYear(), prev.getMonth() - 1, 1))}
          >
            {'<'}
          </button>
          <strong className={`sa-month-label sa-month-label--${viewedMonthTone}`}>
            {MONTH_LABELS[viewedMonth.getMonth()]}, {viewedMonth.getFullYear()}
          </strong>
          <button
            type="button"
            className="sa-arrow"
            aria-label="Next month"
            onClick={() => setViewedMonth((prev) => new Date(prev.getFullYear(), prev.getMonth() + 1, 1))}
          >
            {'>'}
          </button>
        </div>

        <div className="sa-working-row">
          <span>Total Working Days</span>
          <b>{monthSummary.working || totalWorking} Days</b>
        </div>

        <div className="sa-summary-houses">
          <div className="sa-house absent">
            <p>Total Absent</p>
            <strong>{monthSummary.absent || totalAbsent}</strong>
            <span>days</span>
          </div>
          <div className="sa-house present">
            <p>Total Present</p>
            <strong>{(monthSummary.present + monthSummary.late) || totalPresent}</strong>
            <span>days</span>
          </div>
        </div>
      </article>

      {isHistoryModalOpen && (
        <div className="sa-history-modal-overlay" role="presentation" onClick={() => setIsHistoryModalOpen(false)}>
          <div
            className="sa-history-modal"
            role="dialog"
            aria-modal="true"
            aria-label="Past month attendance history"
            onClick={(event) => event.stopPropagation()}
          >
            <div className="sa-history-modal-head">
              <h5>Past Month Attendance History</h5>
              <button
                type="button"
                className="sa-history-close"
                onClick={() => setIsHistoryModalOpen(false)}
                aria-label="Close history popup"
              >
                x
              </button>
            </div>

            {pastMonthGroups.length > 0 ? (
              <>
                <div className="sa-history-overview">
                  <div>
                    <span>Months Analyzed</span>
                    <b>{overallPastSummary.months}</b>
                  </div>
                  <div>
                    <span>Total Class Days</span>
                    <b>{overallPastSummary.totalClassDays}</b>
                  </div>
                  <div>
                    <span>Average Attendance</span>
                    <b>{overallPastSummary.avgAttendance}%</b>
                  </div>
                </div>

                <div className="sa-history-legend">
                  <span><i className="legend-dot present" /> Present</span>
                  <span><i className="legend-dot absent" /> Absent</span>
                </div>

                <div className="sa-history-graph">
                  {pastMonthGroups.map((group) => (
                    <div key={group.key} className="sa-history-graph-row">
                      <div className="sa-history-graph-meta">
                        <strong>{group.title}</strong>
                        <small>{group.totalClassDays} classes</small>
                      </div>
                      <div className="sa-history-bar-wrap">
                        <div className="sa-history-bar present" style={{ width: `${group.attendanceRate}%` }} />
                        <div className="sa-history-bar absent" style={{ width: `${100 - group.attendanceRate}%` }} />
                      </div>
                      <div className="sa-history-graph-stats">
                        <span>{group.presentDays}P</span>
                        <span>{group.absentDays}A</span>
                        <b>{group.attendanceRate}%</b>
                      </div>
                    </div>
                  ))}
                </div>
              </>
            ) : (
              <div className="sa-history-empty">No past month records found.</div>
            )}
          </div>
        </div>
      )}
    </section>
  );
};

export default StudentAttendancePanel;
