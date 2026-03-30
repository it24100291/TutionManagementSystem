import { useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

const Login = () => {
  const location = useLocation();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState(location.state?.registrationSuccess || '');
  const { login } = useAuth();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    try {
      const loggedInUser = await login(email, password);
      if (loggedInUser?.role === 'ADMIN') {
        navigate('/admin/dashboard');
      } else if (loggedInUser?.role === 'TUTOR') {
        navigate('/tutor/dashboard');
      } else if (loggedInUser?.role === 'STUDENT') {
        navigate('/student/dashboard');
      } else {
        navigate('/profile');
      }
    } catch (err) {
      if (err.response?.status === 403 && err.response?.data?.error === 'Account pending approval') {
        navigate('/pending-approval');
      } else {
        setError(err.response?.data?.error || 'Login failed');
      }
    }
  };

  const isSlidingFromRegister = location.state?.authTransition === 'from-register';

  return (
    <div className="register-shell login-shell">
      <div className="register-backdrop register-backdrop-one" />
      <div className="register-backdrop register-backdrop-two" />
      <div className="container register-container">
        <div className={`form-box register-card register-card-simple login-card${isSlidingFromRegister ? ' auth-slide-from-register' : ''}`}>
          <div className="register-form-panel register-form-panel-simple login-form-panel">
            <h3 className="register-form-title">Login</h3>
            {success && <div className="success">{success}</div>}
            {error && <div className="error">{error}</div>}
            <form className="register-form-grid login-form-grid" onSubmit={handleSubmit}>
              <div className="form-group register-form-span">
                <label htmlFor="login-email">Email</label>
                <input id="login-email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
              </div>
              <div className="form-group register-form-span">
                <label htmlFor="login-password">Password</label>
                <input id="login-password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
              </div>
              <button className="register-submit" type="submit">Login</button>
            </form>
            <p className="register-login-copy">
              Don&apos;t have an account?{' '}
              <button
                type="button"
                className="auth-switch-link"
                onClick={() => navigate('/register')}
              >
                Register
              </button>
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Login;
