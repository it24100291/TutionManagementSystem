import { useAuth } from '../context/AuthContext';
import StudentTimetablePanel from '../components/student/TimetablePanel';

const StudentTimetablePage = () => {
  const { user } = useAuth();

  return (
    <div className="container">
      <div className="form-box profile-shell dashboard-xwide">
        <div className="profile-header">
          <div>
            <h2>Timetable</h2>
            <p className="profile-subtitle">
              Review your weekly class schedule with subject, tutor, and time slot details.
            </p>
          </div>
        </div>
        <StudentTimetablePanel studentId={user?.id} />
      </div>
    </div>
  );
};

export default StudentTimetablePage;
