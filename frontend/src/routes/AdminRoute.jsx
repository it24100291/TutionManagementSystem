import { Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

const AdminRoute = ({ children }) => {
  const { isAuthenticated, isAdmin, loading } = useAuth();
  if (loading) return <div>Loading...</div>;
  if (!isAuthenticated()) return <Navigate to="/login" />;
  return isAdmin() ? children : <Navigate to="/profile" />;
};

export default AdminRoute;
