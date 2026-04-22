import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import MyClasses from '../components/tutor/MyClasses';

const actionButtonStyle = {
  border: 'none',
  borderRadius: '12px',
  padding: '12px 18px',
  background: '#2563eb',
  color: '#ffffff',
  fontWeight: 800,
  cursor: 'pointer',
  boxShadow: '0 10px 20px rgba(37, 99, 235, 0.22)',
  transition: 'transform 0.2s ease, box-shadow 0.2s ease',
};

const TutorMyClassesPage = () => {
  const { user } = useAuth();
  const navigate = useNavigate();

  return (
    <div className="container">
      <div className="form-box profile-shell">
        <div
          className="profile-header"
          style={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'flex-start',
            gap: '16px',
            flexWrap: 'wrap',
          }}
        >
          <div>
            <h2>My Classes</h2>
            <p className="profile-subtitle">
              Review all timetable periods assigned to you from the central timetable.
            </p>
          </div>
          <button
            type="button"
            style={actionButtonStyle}
            onClick={() => navigate('/tutor/today-schedule')}
            onMouseEnter={(event) => {
              event.currentTarget.style.transform = 'translateY(-1px)';
              event.currentTarget.style.boxShadow = '0 14px 26px rgba(37, 99, 235, 0.28)';
            }}
            onMouseLeave={(event) => {
              event.currentTarget.style.transform = 'translateY(0)';
              event.currentTarget.style.boxShadow = '0 10px 20px rgba(37, 99, 235, 0.22)';
            }}
          >
            View Today Schedule
          </button>
        </div>
        <MyClasses tutorId={user?.id} />
      </div>
    </div>
  );
};

export default TutorMyClassesPage;
