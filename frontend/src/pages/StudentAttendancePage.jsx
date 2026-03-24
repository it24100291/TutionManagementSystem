import { useAuth } from '../context/AuthContext';
import StudentAttendancePanel from '../components/student/AttendancePanel';

const StudentAttendancePage = () => {
  const { user } = useAuth();

  return (
    <div className="container">
      <div className="form-box profile-shell dashboard-wide">
        <div className="profile-header">
          <div>
            <h2>Attendance</h2>
            <p className="profile-subtitle">
              Review your class attendance summary and full attendance history.
            </p>
          </div>
        </div>
        <StudentAttendancePanel studentId={user?.id} />
      </div>
    </div>
  );
};

export default StudentAttendancePage;
