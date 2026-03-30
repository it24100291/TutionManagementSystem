import { useAuth } from '../context/AuthContext';
import ExaminationPanel from '../components/tutor/ExaminationPanel';

const TutorClassPerformancePage = () => {
  const { user } = useAuth();

  return (
    <div className="container">
      <div className="form-box profile-shell">
        <div className="profile-header">
          <div>
            <h2>Exam & Results</h2>
            <p className="profile-subtitle">
              Enter your subject marks for each term and class. Only your own subject marks can be updated.
            </p>
          </div>
        </div>
        <ExaminationPanel tutorId={user?.id} />
      </div>
    </div>
  );
};

export default TutorClassPerformancePage;

