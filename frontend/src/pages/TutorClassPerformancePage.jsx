import { useAuth } from '../context/AuthContext';
import ClassPerformance from '../components/tutor/ClassPerformance';

const TutorClassPerformancePage = () => {
  const { user } = useAuth();

  return (
    <div className="container">
      <div className="form-box profile-shell">
        <div className="profile-header">
          <div>
            <h2>Class Performance</h2>
            <p className="profile-subtitle">
              Track average exam scores across your classes with quick performance indicators.
            </p>
          </div>
        </div>
        <ClassPerformance tutorId={user?.id} />
      </div>
    </div>
  );
};

export default TutorClassPerformancePage;
