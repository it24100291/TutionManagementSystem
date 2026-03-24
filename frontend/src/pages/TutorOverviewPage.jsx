import { useAuth } from '../context/AuthContext';
import TutorOverviewPanel from '../components/tutor/OverviewPanel';

const TutorOverviewPage = () => {
  const { user } = useAuth();

  return (
    <div className="container">
      <div className="form-box profile-shell">
        <div className="profile-header">
          <div>
            <h2>Overview</h2>
            <p className="profile-subtitle">
              Review your classes, student reach, hours, and current salary status.
            </p>
          </div>
        </div>
        <TutorOverviewPanel tutorId={user?.id} />
      </div>
    </div>
  );
};

export default TutorOverviewPage;
