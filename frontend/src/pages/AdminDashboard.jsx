import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from '../api/axios';

const initialStats = {
  totalStudents: 0,
  totalTutors: 0,
  totalIncome: 0,
  pendingPayments: 0,
  activeUsers: 0,
  salaryDetails: 0,
  attendanceRecords: 0,
};

const statCards = [
  {
    key: 'totalStudents',
    title: 'Total Students',
    icon: 'ST',
    tone: 'students',
    route: '/admin/dashboard/students',
  },
  {
    key: 'totalTutors',
    title: 'Total Tutors',
    icon: 'TU',
    tone: 'tutors',
    route: '/admin/dashboard/tutors',
  },
  {
    key: 'totalIncome',
    title: 'Total Monthly Income',
    icon: 'RS',
    tone: 'income',
    route: '/admin/dashboard/paid-payments',
    formatter: (value) => new Intl.NumberFormat('en-LK', {
      style: 'currency',
      currency: 'LKR',
      maximumFractionDigits: 0,
    }).format(value || 0),
  },
  {
    key: 'pendingPayments',
    title: 'Pending Payments',
    icon: 'PD',
    tone: 'pending',
    route: '/admin/dashboard/pending-payments',
  },
  {
    key: 'activeUsers',
    title: 'Total Active Users',
    icon: 'AC',
    tone: 'active',
    route: '/admin/dashboard/active-users',
  },
  {
    key: 'attendanceRecords',
    title: 'All Class Attendance',
    icon: 'AT',
    tone: 'classes',
    route: '/admin/dashboard/attendance-records',
  },
];

const AdminDashboard = () => {
  const [stats, setStats] = useState(initialStats);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const navigate = useNavigate();

  useEffect(() => {
    const fetchSummary = async () => {
      try {
        setLoading(true);
        const res = await axios.get('/api/admin/dashboard.php');
        setStats({ ...initialStats, ...res.data.data });
        setError('');
      } catch (err) {
        setError(err.response?.data?.error || 'Failed to load dashboard summary');
      } finally {
        setLoading(false);
      }
    };

    fetchSummary();
  }, []);

  return (
    <div className="container">
      <div className="form-box dashboard-shell dashboard-xwide dashboard-dashboard">
        <div className="dashboard-header">
          <div>
            <h2>Admin Dashboard</h2>
            <p className="dashboard-subtitle">
              Track the institution overview, fee status, class activity, and user growth from one place.
            </p>
          </div>
        </div>

        {loading ? (
          <div className="dashboard-loading">
            <div className="dashboard-spinner" aria-hidden="true" />
            <span>Loading dashboard summary...</span>
          </div>
        ) : error ? (
          <div className="error">{error}</div>
        ) : (
          <div className="dashboard-stats-grid">
            {statCards.map((card) => {
              const value = stats[card.key];
              return (
                <button
                  type="button"
                  className={`dashboard-stat-card ${card.tone} clickable`}
                  key={card.key}
                  onClick={() => navigate(card.route)}
                >
                  <div className="dashboard-stat-top">
                    <div>
                      <span className="dashboard-stat-label">{card.title}</span>
                      <strong className="dashboard-stat-value">
                        {card.formatter ? card.formatter(value) : value}
                      </strong>
                    </div>
                    <span className="dashboard-stat-icon" aria-hidden="true">
                      {card.icon}
                    </span>
                  </div>
                </button>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
};

export default AdminDashboard;

