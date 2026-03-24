import { Link } from 'react-router-dom';

const PendingApproval = () => {
  return (
    <div className="container">
      <div className="form-box">
        <h2>Account Pending Approval</h2>
        <p>Your account is waiting for admin approval. You will be able to login once approved.</p>
        <Link to="/login">Back to Login</Link>
      </div>
    </div>
  );
};

export default PendingApproval;
