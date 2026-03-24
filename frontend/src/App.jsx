import { BrowserRouter, Routes, Route, Navigate, NavLink, useLocation, useNavigate } from 'react-router-dom';
import { useEffect, useRef, useState } from 'react';
import { AuthProvider, useAuth } from './context/AuthContext';
import ProtectedRoute from './routes/ProtectedRoute';
import AdminRoute from './routes/AdminRoute';
import Login from './pages/Login';
import Register from './pages/Register';
import LandingPage from './pages/LandingPage';
import PendingApproval from './pages/PendingApproval';
import Profile from './pages/Profile';
import StudentOverviewPage from './pages/StudentOverviewPage';
import StudentAttendancePage from './pages/StudentAttendancePage';
import StudentPaymentsPage from './pages/StudentPaymentsPage';
import StudentExamsPage from './pages/StudentExamsPage';
import StudentTimetablePage from './pages/StudentTimetablePage';
import SuggestionForm from './pages/SuggestionForm';
import MySuggestions from './pages/MySuggestions';
import TutorOverviewPage from './pages/TutorOverviewPage';
import TutorTodaySchedulePage from './pages/TutorTodaySchedulePage';
import TutorClassPerformancePage from './pages/TutorClassPerformancePage';
import TutorAttendancePage from './pages/TutorAttendancePage';
import TutorSalarySummaryPage from './pages/TutorSalarySummaryPage';
import TutorStudentsPage from './pages/TutorStudentsPage';
import TutorLeaveRequestsPage from './pages/TutorLeaveRequestsPage';
import AdminPendingUsers from './pages/AdminPendingUsers';
import AdminSuggestions from './pages/AdminSuggestions';
import AdminTimetable from './pages/AdminTimetable';
import AdminDashboard from './pages/AdminDashboard';
import AdminStudents from './pages/AdminStudents';
import AdminDashboardDetail from './pages/AdminDashboardDetail';
import './App.css';

function ProfileIcon() {
  return (
    <svg
      width="20"
      height="20"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M20 21a8 8 0 0 0-16 0" />
      <circle cx="12" cy="8" r="4" />
    </svg>
  );
}

function TutorSidebar({ items, user, isOpen, onToggle }) {
  return (
    <>
      <button
        type="button"
        className="tutor-sidebar-toggle"
        onClick={onToggle}
        aria-label={isOpen ? 'Close sidebar menu' : 'Open sidebar menu'}
        aria-expanded={isOpen}
      >
        <span />
        <span />
        <span />
      </button>
      <div
        className={`tutor-sidebar-overlay${isOpen ? ' open' : ''}`}
        onClick={onToggle}
        aria-hidden={!isOpen}
      />
      <aside className={`tutor-sidebar${isOpen ? ' open' : ''}`}>
        <nav className="tutor-sidebar-nav" aria-label="Tutor dashboard navigation">
          {items.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              onClick={() => {
                if (isOpen) onToggle();
              }}
              className={({ isActive }) => `tutor-sidebar-link${isActive ? ' active' : ''}`}
            >
              <span>{item.label}</span>
            </NavLink>
          ))}
        </nav>

        <div className="tutor-sidebar-footer">
          <div className="tutor-sidebar-user">
            <strong>{user?.full_name || 'Tutor'}</strong>
            <span>{user?.email}</span>
          </div>
        </div>
      </aside>
    </>
  );
}

function StudentSidebar({ items }) {
  return (
    <aside className="student-sidebar">
      <nav className="student-sidebar-nav" aria-label="Student dashboard navigation">
        {items.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            className={({ isActive }) => `student-sidebar-link${isActive ? ' active' : ''}`}
          >
            <span>{item.label}</span>
          </NavLink>
        ))}
      </nav>
    </aside>
  );
}

function AdminSidebar({ items }) {
  return (
    <aside className="admin-sidebar">
      <nav className="admin-sidebar-nav" aria-label="Admin dashboard navigation">
        {items.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            className={({ isActive }) => `admin-sidebar-link${isActive ? ' active' : ''}`}
          >
            <span>{item.label}</span>
          </NavLink>
        ))}
      </nav>
    </aside>
  );
}

function DashboardLayout({ children }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [isTutorSidebarOpen, setIsTutorSidebarOpen] = useState(false);
  const [tutorTopbarHeight, setTutorTopbarHeight] = useState(0);
  const tutorTopbarRef = useRef(null);
  const roleName = user?.role || '';
  const isTutorLayout = roleName === 'TUTOR';
  const isAdminLayout = roleName === 'ADMIN';
  const isStudentLayout = roleName === 'STUDENT';

  const navItems = [
    { to: '/admin/dashboard', label: 'Dashboard', roles: ['ADMIN'] },
    { to: '/profile', label: 'Profile', roles: ['STUDENT', 'TUTOR', 'PARENT', 'ADMIN'] },
      { to: '/tutor/overview', label: 'Overview', roles: ['TUTOR'] },
      { to: '/tutor/students', label: 'Students', roles: ['TUTOR'] },
      { to: '/tutor/leave-requests', label: 'Leave Requests', roles: ['TUTOR'] },
      { to: '/tutor/today-schedule', label: 'Today Schedule', roles: ['TUTOR'] },
      { to: '/tutor/attendance', label: 'Attendance Marking', roles: ['TUTOR'] },
    { to: '/tutor/class-performance', label: 'Class Performance', roles: ['TUTOR'] },
    { to: '/tutor/salary-summary', label: 'Salary Summary', roles: ['TUTOR'] },
    { to: '/student/overview', label: 'Overview', roles: ['STUDENT'] },
      { to: '/student/attendance', label: 'Attendance', roles: ['STUDENT'] },
      { to: '/student/payments', label: 'Payments', roles: ['STUDENT'] },
      { to: '/student/exams-results', label: 'Exams & Results', roles: ['STUDENT'] },
      { to: '/student/timetable', label: 'Timetable', roles: ['STUDENT'] },
      { to: '/suggestions/new', label: 'New Suggestion', roles: ['STUDENT', 'TUTOR', 'PARENT', 'ADMIN'] },
      { to: '/suggestions/mine', label: 'My Suggestions', roles: ['STUDENT', 'TUTOR', 'PARENT', 'ADMIN'] },
      { to: '/admin/pending-users', label: 'Pending Users', roles: ['ADMIN'] },
    { to: '/admin/suggestions', label: 'All Suggestions', roles: ['ADMIN'] },
      { to: '/admin/timetable', label: 'Timetable', roles: ['ADMIN'] }
    ];
  const visibleNavItems = navItems.filter((item) => item.roles.includes(roleName));
  const adminNavItems = visibleNavItems.filter((item) => !(isAdminLayout && item.to === '/profile'));
  const tutorNavItems = visibleNavItems.filter((item) => item.roles.includes('TUTOR') && item.to !== '/profile');
  const studentNavItems = visibleNavItems.filter((item) => item.to !== '/profile');

  useEffect(() => {
    setIsTutorSidebarOpen(false);
  }, [location.pathname]);

  useEffect(() => {
    if (!isTutorLayout) {
      return undefined;
    }

    const updateTutorTopbarHeight = () => {
      const nextHeight = tutorTopbarRef.current?.offsetHeight || 0;
      setTutorTopbarHeight(nextHeight);
    };

    updateTutorTopbarHeight();
    window.addEventListener('resize', updateTutorTopbarHeight);

    return () => {
      window.removeEventListener('resize', updateTutorTopbarHeight);
    };
  }, [isTutorLayout, user?.full_name, user?.email]);

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  if (isTutorLayout) {
    return (
      <div
        className="dashboard-layout tutor-dashboard-layout"
        style={{ '--tutor-topbar-offset': `${tutorTopbarHeight}px` }}
      >
        <header ref={tutorTopbarRef} className="tutor-dashboard-topbar">
            <div className="tutor-dashboard-topbar-copy">
              <div className="tutor-dashboard-topbar-brandline">
                <img src="/Logo.jpeg" alt="Tuition Management System logo" className="tutor-dashboard-topbar-logo" />
                <div>
                  <p className="tutor-dashboard-brand-title">Tutor Dashboard</p>
                  <p className="tutor-dashboard-brand-subtitle">NEW KRISHNA EDUCATION CENTER-KODIKAMAM</p>
                </div>
              </div>
            <h1 className="tutor-dashboard-title">Welcome, {user?.full_name || 'Tutor'}!</h1>
          </div>
          <div className="tutor-dashboard-topbar-actions">
            <button
              type="button"
              className="tutor-dashboard-profile-button"
              onClick={() => navigate('/tutor/profile')}
              aria-label="Open profile"
              title="Profile"
            >
              <ProfileIcon />
            </button>
            <button
              type="button"
              className="danger-button tutor-dashboard-topbar-logout"
              onClick={handleLogout}
            >
              Logout
            </button>
          </div>
        </header>
        <div className="tutor-dashboard-shell">
          <TutorSidebar
            items={tutorNavItems}
            user={user}
            isOpen={isTutorSidebarOpen}
            onToggle={() => setIsTutorSidebarOpen((current) => !current)}
          />
          <div className="tutor-dashboard-main">
          <main className="dashboard-content tutor-dashboard-content">
            {children}
          </main>
          </div>
        </div>
      </div>
    );
  }

  if (isStudentLayout) {
    return (
      <div className="dashboard-layout student-dashboard-layout">
        <header className="student-dashboard-topbar">
          <div className="student-dashboard-topbar-main">
            <div className="dashboard-topbar-breadcrumbs">
              <div className="dashboard-topbar-brand">
                <img src="/Logo.jpeg" alt="Tuition Management System logo" className="dashboard-topbar-logo" />
              </div>
              <div className="dashboard-topbar-welcome">
                <h1 className="dashboard-page-title">Welcome, {user?.full_name || 'Student'}!</h1>
                <p className="dashboard-page-subtitle"><strong>NEW KRISHNA EDUCATION CENTER-KODIKAMAM</strong></p>
              </div>
            </div>
          </div>
          <div className="student-dashboard-topbar-actions">
            <button
              type="button"
              className="student-dashboard-profile-button"
              onClick={() => navigate('/student/profile')}
              aria-label="Open profile"
              title="Profile"
            >
              <ProfileIcon />
            </button>
            <button
              type="button"
              className="danger-button dashboard-topbar-logout"
              onClick={handleLogout}
            >
              Logout
            </button>
          </div>
        </header>
        <div className="student-dashboard-shell">
          <main className="dashboard-content student-dashboard-content">
            {children}
          </main>
          <StudentSidebar items={studentNavItems} />
        </div>
      </div>
    );
  }

  if (isAdminLayout) {
    return (
      <div className="dashboard-layout admin-dashboard-layout">
        <div className="dashboard-main dashboard-main-full admin-dashboard-main">
          <header className="dashboard-topbar admin-dashboard-topbar">
            <div className="dashboard-topbar-main">
              <div className="dashboard-topbar-breadcrumbs">
                <div className="dashboard-topbar-brand">
                  <img src="/Logo.jpeg" alt="Tuition Management System logo" className="dashboard-topbar-logo" />
                </div>
                <div className="dashboard-topbar-welcome">
                  <h1 className="dashboard-page-title">Welcome, {user?.full_name || 'User'}!</h1>
                  <p className="dashboard-page-subtitle"><strong>NEW KRISHNA EDUCATION CENTER-KODIKAMAM</strong></p>
                </div>
              </div>
            </div>
            <div className="dashboard-topbar-actions">
              <button
                type="button"
                className="admin-dashboard-profile-button"
                onClick={() => navigate('/profile')}
                aria-label="Open profile"
                title="Profile"
              >
                <ProfileIcon />
              </button>
              <button
                type="button"
                className="danger-button dashboard-topbar-logout"
                onClick={handleLogout}
              >
                Logout
              </button>
            </div>
          </header>

          <div className="admin-dashboard-shell">
            <AdminSidebar items={adminNavItems} />
            <main className="dashboard-content admin-dashboard-content">
              {children}
            </main>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="dashboard-layout">
      <div className="dashboard-main dashboard-main-full">
        <header className="dashboard-topbar">
          <div className="dashboard-topbar-main">
            <div className="dashboard-topbar-breadcrumbs">
              <div className="dashboard-topbar-brand">
                <img src="/Logo.jpeg" alt="Tuition Management System logo" className="dashboard-topbar-logo" />
              </div>
              <div className="dashboard-topbar-welcome">
                <h1 className="dashboard-page-title">Welcome, {user?.full_name || 'User'}!</h1>
                <p className="dashboard-page-subtitle"><strong>NEW KRISHNA EDUCATION CENTER-KODIKAMAM</strong></p>
              </div>
            </div>
          </div>
          <div className="dashboard-topbar-actions">
            <div className="dashboard-topbar-panel">
              <span className="dashboard-status-dot" />
              <div>
                <strong>{user?.role}</strong>
                <small>{`Signed in as ${user?.email}`}</small>
              </div>
            </div>
            <button
              type="button"
              className="danger-button dashboard-topbar-logout"
              onClick={handleLogout}
            >
              Logout
            </button>
          </div>
        </header>

        <div className={`dashboard-nav-shell${isStudentLayout ? ' student-dashboard-nav-shell' : ''}`}>
          <nav className={`dashboard-topnav${isStudentLayout ? ' student-dashboard-topnav' : ''}`} aria-label="Dashboard navigation">
            {visibleNavItems.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                className={({ isActive }) => `dashboard-topnav-link${isActive ? ' active' : ''}${isStudentLayout ? ' student-dashboard-topnav-link' : ''}`}
              >
                {item.label}
              </NavLink>
            ))}
          </nav>
        </div>

        <main className="dashboard-content">
          {children}
        </main>
      </div>
    </div>
  );
}

function HomeRedirect() {
  const { user, isAuthenticated, loading } = useAuth();

  if (loading) {
    return <div>Loading...</div>;
  }

  if (!isAuthenticated()) {
    return <Navigate to="/login" />;
  }

  if (user?.role === 'ADMIN') {
    return <Navigate to="/admin/dashboard" replace />;
  }

  if (user?.role === 'TUTOR') {
    return <Navigate to="/tutor/dashboard" replace />;
  }

  if (user?.role === 'STUDENT') {
    return <Navigate to="/student/dashboard" replace />;
  }

  return <Navigate to="/profile" replace />;
}

function AppShell() {
  return (
    <Routes>
      <Route path="/" element={<LandingPage />} />
      <Route path="/login" element={<Login />} />
      <Route path="/register" element={<Register />} />
      <Route path="/pending-approval" element={<PendingApproval />} />
      <Route path="/admin/dashboard" element={<AdminRoute><DashboardLayout><AdminDashboard /></DashboardLayout></AdminRoute>} />
      <Route path="/admin/students" element={<AdminRoute><DashboardLayout><AdminStudents /></DashboardLayout></AdminRoute>} />
      <Route path="/admin/dashboard/:metric" element={<AdminRoute><DashboardLayout><AdminDashboardDetail /></DashboardLayout></AdminRoute>} />
      <Route path="/tutor/dashboard" element={<ProtectedRoute><Navigate to="/tutor/overview" replace /></ProtectedRoute>} />
      <Route path="/tutor/overview" element={<ProtectedRoute><DashboardLayout><TutorOverviewPage /></DashboardLayout></ProtectedRoute>} />
      <Route path="/tutor/today-schedule" element={<ProtectedRoute><DashboardLayout><TutorTodaySchedulePage /></DashboardLayout></ProtectedRoute>} />
      <Route path="/tutor/attendance" element={<ProtectedRoute><DashboardLayout><TutorAttendancePage /></DashboardLayout></ProtectedRoute>} />
      <Route path="/tutor/class-performance" element={<ProtectedRoute><DashboardLayout><TutorClassPerformancePage /></DashboardLayout></ProtectedRoute>} />
      <Route path="/tutor/salary-summary" element={<ProtectedRoute><DashboardLayout><TutorSalarySummaryPage /></DashboardLayout></ProtectedRoute>} />
      <Route path="/tutor/students" element={<ProtectedRoute><DashboardLayout><TutorStudentsPage /></DashboardLayout></ProtectedRoute>} />
      <Route path="/tutor/leave-requests" element={<ProtectedRoute><DashboardLayout><TutorLeaveRequestsPage /></DashboardLayout></ProtectedRoute>} />
      <Route path="/tutor/profile" element={<ProtectedRoute><DashboardLayout><Profile /></DashboardLayout></ProtectedRoute>} />
      <Route path="/student/overview" element={<ProtectedRoute><DashboardLayout><StudentOverviewPage /></DashboardLayout></ProtectedRoute>} />
      <Route path="/student/dashboard" element={<ProtectedRoute><Navigate to="/student/overview" replace /></ProtectedRoute>} />
      <Route path="/student/attendance" element={<ProtectedRoute><DashboardLayout><StudentAttendancePage /></DashboardLayout></ProtectedRoute>} />
      <Route path="/student/payments" element={<ProtectedRoute><DashboardLayout><StudentPaymentsPage /></DashboardLayout></ProtectedRoute>} />
      <Route path="/student/exams-results" element={<ProtectedRoute><DashboardLayout><StudentExamsPage /></DashboardLayout></ProtectedRoute>} />
      <Route path="/student/timetable" element={<ProtectedRoute><DashboardLayout><StudentTimetablePage /></DashboardLayout></ProtectedRoute>} />
      <Route path="/student/profile" element={<ProtectedRoute><DashboardLayout><Profile /></DashboardLayout></ProtectedRoute>} />
      <Route path="/profile" element={<ProtectedRoute><DashboardLayout><Profile /></DashboardLayout></ProtectedRoute>} />
      <Route path="/suggestions/new" element={<ProtectedRoute><DashboardLayout><SuggestionForm /></DashboardLayout></ProtectedRoute>} />
      <Route path="/suggestions/mine" element={<ProtectedRoute><DashboardLayout><MySuggestions /></DashboardLayout></ProtectedRoute>} />
      <Route path="/admin/pending-users" element={<AdminRoute><DashboardLayout><AdminPendingUsers /></DashboardLayout></AdminRoute>} />
      <Route path="/admin/suggestions" element={<AdminRoute><DashboardLayout><AdminSuggestions /></DashboardLayout></AdminRoute>} />
      <Route path="/admin/timetable" element={<AdminRoute><DashboardLayout><AdminTimetable /></DashboardLayout></AdminRoute>} />
    </Routes>
  );
}

function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <AppShell />
      </BrowserRouter>
    </AuthProvider>
  );
}

export default App;
