import { useAuth } from '../context/AuthContext';
import StudentList from '../components/tutor/StudentList';

const TutorStudentsPage = () => {
  const { user } = useAuth();

  return (
    <div className="container">
      <div className="form-box profile-shell">
        <div className="profile-header">
          <div>
            <h2>Students</h2>
            <p className="profile-subtitle">
              Browse all students assigned to your classes and review their attendance quickly.
            </p>
          </div>
        </div>
        <StudentList tutorId={user?.id} />
      </div>
    </div>
  );
};

export default TutorStudentsPage;
