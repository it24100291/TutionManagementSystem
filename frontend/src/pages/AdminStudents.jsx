import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from '../api/axios';

const AdminStudents = () => {
  const fixedGradeOptions = ['Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11'];
  const [students, setStudents] = useState([]);
  const [selectedGrade, setSelectedGrade] = useState('ALL');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const navigate = useNavigate();

  useEffect(() => {
    const fetchStudents = async () => {
      try {
        setLoading(true);
        const res = await axios.get('/admin/students');
        setStudents(res.data.data || []);
        setError('');
      } catch (err) {
        setError(err.response?.data?.error || 'Failed to load registered students');
      } finally {
        setLoading(false);
      }
    };

    fetchStudents();
  }, []);

  const normalizeGrade = (grade) => {
    const value = String(grade || '').trim();
    if (!value) return '';
    return value.toLowerCase().startsWith('grade ') ? value : `Grade ${value}`;
  };

  const gradeOptions = ['ALL', ...fixedGradeOptions];

  const filteredStudents = selectedGrade === 'ALL'
    ? students
    : students.filter((student) => normalizeGrade(student.grade) === selectedGrade);

  if (loading) {
    return (
      <div className="container">
        <div className="form-box dashboard-shell dashboard-xwide">
          <div className="dashboard-empty">Loading registered students...</div>
        </div>
      </div>
    );
  }

  return (
    <div className="container">
      <div className="form-box dashboard-shell dashboard-xwide">
        <div className="dashboard-header">
          <div>
            <h2>Registered Students</h2>
            <p className="dashboard-subtitle">
              Review all student registrations and filter them class by class.
            </p>
          </div>
          <button type="button" className="secondary-button" onClick={() => navigate('/admin/dashboard')}>
            Back to Dashboard
          </button>
        </div>

        {error && <div className="error">{error}</div>}

        {!error && students.length > 0 && (
          <div className="filter-card">
            <div className="form-group" style={{ minWidth: 220, marginBottom: 0 }}>
              <label htmlFor="student-grade-filter">Filter By Class</label>
              <select
                id="student-grade-filter"
                value={selectedGrade}
                onChange={(e) => setSelectedGrade(e.target.value)}
              >
                {gradeOptions.map((grade) => (
                  <option key={grade} value={grade}>
                    {grade === 'ALL' ? 'All Classes' : grade}
                  </option>
                ))}
              </select>
            </div>

            <div className="form-group" style={{ minWidth: 180, marginBottom: 0 }}>
              <label>Total Students</label>
              <input
                type="text"
                value={`${filteredStudents.length} shown`}
                readOnly
              />
            </div>
          </div>
        )}

        {!error && students.length === 0 ? (
          <div className="dashboard-empty">No registered students found.</div>
        ) : !error && filteredStudents.length === 0 ? (
          <div className="dashboard-empty">No students found for the selected class.</div>
        ) : (
          <div className="table-card">
            <table className="dashboard-table">
              <thead>
                <tr>
                  <th>Full Name</th>
                  <th>Email</th>
                  <th>Grade</th>
                  <th>School</th>
                  <th>Phone</th>
                  <th>Status</th>
                  <th>Guardian</th>
                  <th>Created At</th>
                </tr>
              </thead>
              <tbody>
                {filteredStudents.map((student) => (
                  <tr key={student.id}>
                    <td>{student.full_name}</td>
                    <td className="truncate-cell">{student.email}</td>
                    <td>{normalizeGrade(student.grade) || 'N/A'}</td>
                    <td>{student.school_name || 'N/A'}</td>
                    <td>{student.phone || 'N/A'}</td>
                    <td>
                      <span className={`profile-badge ${student.status?.toLowerCase()}`}>{student.status}</span>
                    </td>
                    <td>{student.guardian_name || 'N/A'}</td>
                    <td>{new Date(student.created_at).toLocaleDateString()}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
};

export default AdminStudents;
