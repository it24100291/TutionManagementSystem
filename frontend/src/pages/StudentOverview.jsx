import { useAuth } from '../context/AuthContext';
import OverviewPanel from '../components/OverviewPanel';

const StudentOverview = () => {
  const { user } = useAuth();

  return (
    <div className="container">
      <div className="form-box profile-shell">
        <div className="profile-header">
          <div>
            <h2>Overview</h2>
            <p className="profile-subtitle">
              Review your attendance, exam results, fees, and upcoming classes in one place.
            </p>
          </div>
        </div>
        <OverviewPanel studentId={user?.id} />
      </div>
    </div>
  );
};

export default StudentOverview;
