import { useAuth } from '../context/AuthContext';
import SalarySummary from '../components/tutor/SalarySummary';

const TutorSalarySummaryPage = () => {
  const { user } = useAuth();

  return (
    <div className="container">
      <div className="form-box profile-shell">
        <div className="profile-header">
          <div>
            <h2>Salary Summary</h2>
            <p className="profile-subtitle">
              Review your working hours, salary calculation, and recent payment history.
            </p>
          </div>
        </div>
        <SalarySummary tutorId={user?.id} />
      </div>
    </div>
  );
};

export default TutorSalarySummaryPage;
