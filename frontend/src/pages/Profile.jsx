import { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import axios from '../api/axios';

const sriLankanPhonePattern = /^07\d{8}$/;
const sriLankanNicPattern = /^(?:\d{9}[Vv]|\d{12})$/;

const Profile = () => {
  const [profile, setProfile] = useState(null);
  const [editing, setEditing] = useState(false);
  const [formData, setFormData] = useState({});
  const [fieldErrors, setFieldErrors] = useState({});
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(true);
  const { isAdmin } = useAuth();

  const profileItems = [
    { label: 'Name', value: profile?.full_name },
    { label: 'Email', value: profile?.email },
    { label: 'Role', value: profile?.role },
    { label: 'Status', value: profile?.status, badge: true },
    { label: 'Phone', value: profile?.phone || 'N/A' },
    { label: 'Date of Birth', value: profile?.dob || 'N/A' },
    { label: 'Gender', value: profile?.gender || 'N/A' },
    { label: 'Address', value: profile?.address || 'N/A' },
  ];

  const tutorItems = profile?.role === 'TUTOR'
    ? [
        { label: 'NIC Number', value: profile.nic_number || 'N/A' },
        { label: 'Subject', value: profile.subject || 'N/A' },
      ]
    : [];

  const studentItems = profile?.role === 'STUDENT'
    ? [
        { label: 'School Name', value: profile.school_name || 'N/A' },
        { label: 'Grade', value: profile.grade || 'N/A' },
        { label: 'Siblings in Tuition', value: profile.siblings_count ?? '0' },
        { label: 'Parent/Guardian Name', value: profile.guardian_name || 'N/A' },
        { label: 'Parent/Guardian Job', value: profile.guardian_job || 'N/A' },
        { label: 'Parent/Guardian NIC', value: profile.guardian_nic || 'N/A' },
      ]
    : [];

  const renderMetaItems = (items) =>
    items.map((item) => (
      <div className="profile-meta-item" key={item.label}>
        <span className="profile-meta-label">{item.label}</span>
        {item.badge ? (
          <span className={`profile-badge ${item.value?.toLowerCase()}`}>{item.value}</span>
        ) : (
          <span className="profile-meta-value">{item.value}</span>
        )}
      </div>
    ));

  useEffect(() => {
    fetchProfile();
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const fetchProfile = async () => {
    try {
      setLoading(true);
      const res = await axios.get('/me');
      setProfile(res.data.data);
      setFormData({
        full_name: res.data.data.full_name || '',
        phone: res.data.data.phone || '',
        dob: res.data.data.dob || '',
        gender: res.data.data.gender || '',
        address: res.data.data.address || '',
        avatar_url: res.data.data.avatar_url || '',
        nic_number: res.data.data.nic_number || '',
        subject: res.data.data.subject || '',
        school_name: res.data.data.school_name || '',
        grade: res.data.data.grade || '',
        siblings_count: res.data.data.siblings_count ?? '',
        guardian_name: res.data.data.guardian_name || '',
        guardian_job: res.data.data.guardian_job || '',
        guardian_nic: res.data.data.guardian_nic || ''
      });
    } catch {
      setError('Failed to load profile');
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    setFieldErrors({});

    if (formData.phone && !sriLankanPhonePattern.test(formData.phone.trim())) {
      setFieldErrors({ phone: 'Use 07XXXXXXXX' });
      return;
    }

    if (profile?.role === 'TUTOR' && formData.nic_number && !sriLankanNicPattern.test(formData.nic_number.trim())) {
      setFieldErrors({ nic_number: 'Use 9 digits + V/v or 12 digits' });
      return;
    }

    if (profile?.role === 'STUDENT' && formData.guardian_nic && !sriLankanNicPattern.test(formData.guardian_nic.trim())) {
      setFieldErrors({ guardian_nic: 'Use 9 digits + V/v or 12 digits' });
      return;
    }

    try {
      const res = await axios.put('/me', formData);
      setProfile(res.data.data);
      setEditing(false);
      setSuccess('Profile updated');
    } catch (err) {
      setError(err.response?.data?.error || 'Update failed');
    }
  };

  return (
    <div className="container">
      <div className="form-box profile-shell">
        <div className="profile-header">
          <div>
            <h2>Profile</h2>
            <p className="profile-subtitle">
              {isAdmin() ? 'Admin dashboard and account overview' : 'Manage your account details and activity'}
            </p>
          </div>
        </div>
        {error && <div className="error">{error}</div>}
        {success && <div className="success">{success}</div>}
        {loading ? (
          <div className="dashboard-empty">Loading profile...</div>
        ) : !profile ? (
          <div className="dashboard-empty">Profile not available.</div>
        ) : !editing ? (
          <div className="profile-content">
            <div className="profile-card">
              <div className="profile-meta-grid">
                {renderMetaItems(profileItems)}
                {renderMetaItems(tutorItems)}
                {renderMetaItems(studentItems)}
              </div>
              <div className="profile-footer">
                <button type="button" onClick={() => setEditing(true)}>Edit Profile</button>
              </div>
            </div>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="profile-form">
            <div className="form-group">
              <label htmlFor="full_name">Full Name</label>
              <input id="full_name" name="full_name" type="text" value={formData.full_name} onChange={(e) => setFormData({...formData, full_name: e.target.value})} required />
            </div>
            <div className="form-group">
              <label htmlFor="dob">Date of Birth</label>
              <input id="dob" name="dob" type="date" value={formData.dob || ''} onChange={(e) => setFormData({...formData, dob: e.target.value})} />
            </div>
            <div className="form-group">
              <label htmlFor="phone">Phone</label>
              <input
                id="phone"
                name="phone"
                type="tel"
                inputMode="numeric"
                maxLength={10}
                placeholder="07XXXXXXXX"
                value={formData.phone}
                onChange={(e) => {
                  setFormData({...formData, phone: e.target.value.replace(/\D/g, '').slice(0, 10)});
                  setFieldErrors((current) => ({ ...current, phone: '' }));
                }}
              />
              {fieldErrors.phone ? <div className="error">{fieldErrors.phone}</div> : null}
            </div>
            <div className="form-group">
              <label htmlFor="gender">Gender</label>
              <select id="gender" name="gender" value={formData.gender || ''} onChange={(e) => setFormData({...formData, gender: e.target.value})}>
                <option value="">Select Gender</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div className="form-group">
              <label htmlFor="address">Address</label>
              <textarea id="address" name="address" value={formData.address} onChange={(e) => setFormData({...formData, address: e.target.value})} />
            </div>
            <div className="form-group">
              <label htmlFor="avatar_url">Avatar URL</label>
              <input id="avatar_url" name="avatar_url" type="url" value={formData.avatar_url} onChange={(e) => setFormData({...formData, avatar_url: e.target.value})} />
            </div>
            {profile.role === 'TUTOR' && (
              <>
                <div className="form-group">
                  <label htmlFor="nic_number">NIC Number</label>
                  <input
                    id="nic_number"
                    name="nic_number"
                    type="text"
                    maxLength={12}
                    placeholder="123456789V or 200012345678"
                    value={formData.nic_number || ''}
                    onChange={(e) => {
                      setFormData({...formData, nic_number: e.target.value.replace(/[^0-9Vv]/g, '').slice(0, 12)});
                      setFieldErrors((current) => ({ ...current, nic_number: '' }));
                    }}
                  />
                  {fieldErrors.nic_number ? <div className="error">{fieldErrors.nic_number}</div> : null}
                </div>
                <div className="form-group">
                  <label htmlFor="subject">Subject</label>
                  <input id="subject" name="subject" type="text" value={formData.subject || ''} onChange={(e) => setFormData({...formData, subject: e.target.value})} />
                </div>
              </>
            )}
            {profile.role === 'STUDENT' && (
              <>
                <div className="form-group">
                  <label htmlFor="school_name">School Name</label>
                  <input id="school_name" name="school_name" type="text" value={formData.school_name || ''} onChange={(e) => setFormData({...formData, school_name: e.target.value})} />
                </div>
                <div className="form-group">
                  <label htmlFor="grade">Grade</label>
                  <input id="grade" name="grade" type="text" value={formData.grade || ''} onChange={(e) => setFormData({...formData, grade: e.target.value})} />
                </div>
                <div className="form-group">
                  <label htmlFor="siblings_count">Number of Siblings studying in our tuition</label>
                  <input id="siblings_count" name="siblings_count" type="number" min="0" value={formData.siblings_count ?? ''} onChange={(e) => setFormData({...formData, siblings_count: e.target.value})} />
                </div>
                <div className="form-group">
                  <label htmlFor="guardian_name">Parent/Guardian Name</label>
                  <input id="guardian_name" name="guardian_name" type="text" value={formData.guardian_name || ''} onChange={(e) => setFormData({...formData, guardian_name: e.target.value})} />
                </div>
                <div className="form-group">
                  <label htmlFor="guardian_job">Parent/Guardian Job</label>
                  <input id="guardian_job" name="guardian_job" type="text" value={formData.guardian_job || ''} onChange={(e) => setFormData({...formData, guardian_job: e.target.value})} />
                </div>
                <div className="form-group">
                  <label htmlFor="guardian_nic">Parent/Guardian NIC Number</label>
                  <input
                    id="guardian_nic"
                    name="guardian_nic"
                    type="text"
                    maxLength={12}
                    placeholder="123456789V or 200012345678"
                    value={formData.guardian_nic || ''}
                    onChange={(e) => {
                      setFormData({...formData, guardian_nic: e.target.value.replace(/[^0-9Vv]/g, '').slice(0, 12)});
                      setFieldErrors((current) => ({ ...current, guardian_nic: '' }));
                    }}
                  />
                  {fieldErrors.guardian_nic ? <div className="error">{fieldErrors.guardian_nic}</div> : null}
                </div>
              </>
            )}
            <div className="profile-footer">
              <button type="submit">Save</button>
              <button type="button" className="secondary-button" onClick={() => setEditing(false)}>Cancel</button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
};

export default Profile;
