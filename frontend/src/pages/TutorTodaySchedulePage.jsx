import { useAuth } from '../context/AuthContext';
import TodaySchedule from '../components/tutor/TodaySchedule';

const TutorTodaySchedulePage = () => {
  const { user } = useAuth();

  return (
    <div className="container">
      <div className="form-box profile-shell">
        <div className="profile-header">
          <div>
            <h2>Today Schedule</h2>
            <p className="profile-subtitle">
              Check today&apos;s classes, rooms, timing, and report an absence when needed.
            </p>
          </div>
        </div>
        <TodaySchedule tutorId={user?.id} />
      </div>
    </div>
  );
};

export default TutorTodaySchedulePage;
