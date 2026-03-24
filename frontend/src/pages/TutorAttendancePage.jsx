import { useAuth } from '../context/AuthContext';
import AttendanceMarking from '../components/tutor/AttendanceMarking';

const TutorAttendancePage = () => {
  const { user } = useAuth();

  return (
    <div className="container">
      <div className="form-box profile-shell">
        <div className="profile-header">
          <div>
            <h2>Attendance Marking</h2>
            <p className="profile-subtitle">
              Mark present and absent students for your next upcoming class in one place.
            </p>
          </div>
        </div>
        <AttendanceMarking tutorId={user?.id} />
      </div>
    </div>
  );
};

export default TutorAttendancePage;
