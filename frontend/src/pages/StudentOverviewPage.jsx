import { useAuth } from '../context/AuthContext';
import StudentOverviewPanel from '../components/student/OverviewPanel';

const StudentOverviewPage = () => {
  const { user } = useAuth();

  return (
    <div className="container">
      <div className="form-box profile-shell dashboard-wide">
        <div className="profile-header">
          <div>
            <h2>Overview</h2>
            <p className="profile-subtitle">
              Review your classes, attendance, payments, exams, and important updates in one place.
            </p>
          </div>
        </div>
        <StudentOverviewPanel studentId={user?.id} />
      </div>
    </div>
  );
};

export default StudentOverviewPage;
