import { useAuth } from '../context/AuthContext';
import StudentPaymentsPanel from '../components/student/PaymentsPanel';

const StudentPaymentsPage = () => {
  const { user } = useAuth();

  return (
    <div className="container">
      <div className="form-box profile-shell dashboard-wide">
        <div className="profile-header">
          <div>
            <h2>Payments</h2>
            <p className="profile-subtitle">
              Review your payment summary, outstanding amounts, and full payment history.
            </p>
          </div>
        </div>
        <StudentPaymentsPanel studentId={user?.id} />
      </div>
    </div>
  );
};

export default StudentPaymentsPage;
