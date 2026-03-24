import { useAuth } from '../context/AuthContext';
import StudentExamsResultsPanel from '../components/student/ExamsResultsPanel';

const StudentExamsPage = () => {
  const { user } = useAuth();

  return (
    <div className="container">
      <div className="form-box profile-shell dashboard-wide">
        <div className="profile-header">
          <div>
            <h2>Exams & Results</h2>
            <p className="profile-subtitle">
              Review your exam performance and average mark in one place.
            </p>
          </div>
        </div>
        <StudentExamsResultsPanel studentId={user?.id} />
      </div>
    </div>
  );
};

export default StudentExamsPage;
