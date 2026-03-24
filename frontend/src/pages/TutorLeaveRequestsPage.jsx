import { useAuth } from '../context/AuthContext';
import LeaveRequests from '../components/tutor/LeaveRequests';

const TutorLeaveRequestsPage = () => {
  const { user } = useAuth();

  return (
    <div className="container">
      <div className="form-box profile-shell">
        <div className="profile-header">
          <div>
            <h2>Leave Requests</h2>
            <p className="profile-subtitle">
              Review parent-submitted absence requests and approve or deny them separately.
            </p>
          </div>
        </div>
        <LeaveRequests tutorId={user?.id} />
      </div>
    </div>
  );
};

export default TutorLeaveRequestsPage;
