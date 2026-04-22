import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from '../api/axios';

const tutorSubjects = ['Tamil', 'Mathematics', 'Science', 'Religion', 'English', 'Civics', 'History', 'Geography', 'ICT', 'Health Science', 'Sinhala', 'Commerce'];
const studentGrades = ['Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11'];
const sriLankanPhonePattern = /^07\d{8}$/;
const sriLankanNicPattern = /^(?:\d{9}[Vv]|\d{12})$/;
const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const studentDobMaxDate = () => {
  const today = new Date();
  today.setFullYear(today.getFullYear() - 10);
  return today.toISOString().split('T')[0];
};

const tutorDobMaxDate = () => {
  const today = new Date();
  today.setFullYear(today.getFullYear() - 20);
  return today.toISOString().split('T')[0];
};

const initialFormData = {
  full_name: '',
  email: '',
  password: '',
  phone: '',
  dob: '',
  gender: '',
  address: '',
  role: '',
  nic_number: '',
  subject: '',
  school_name: '',
  grade: '',
  siblings_count: '',
  guardian_name: '',
  guardian_job: '',
  guardian_nic: ''
};

const roleCards = [
  {
    key: 'STUDENT',
    title: 'Student',
    icon: 'Graduation',
    description: 'Register as a student to manage your profile and tuition-related details.'
  },
  {
    key: 'TUTOR',
    title: 'Tutor',
    icon: 'Teaching',
    description: 'Register as a tutor to provide subject details and join the institution dashboard.'
  }
];

const Register = () => {
  const [formData, setFormData] = useState(initialFormData);
  const [selectedRole, setSelectedRole] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(false);
  const [otpStep, setOtpStep] = useState(false);
  const [otpVerified, setOtpVerified] = useState(false);
  const [otpCode, setOtpCode] = useState('');
  const [otpTimer, setOtpTimer] = useState(0);
  const [otpEmail, setOtpEmail] = useState('');
  const navigate = useNavigate();

  const isTutor = selectedRole === 'TUTOR';
  const isStudent = selectedRole === 'STUDENT';

  const visiblePayload = useMemo(() => {
    if (isTutor) {
      return {
        ...formData,
        role: 'TUTOR',
        school_name: '',
        grade: '',
        siblings_count: '',
        guardian_name: '',
        guardian_job: '',
        guardian_nic: ''
      };
    }

    if (isStudent) {
      return {
        ...formData,
        role: 'STUDENT',
        nic_number: '',
        subject: ''
      };
    }

    return formData;
  }, [formData, isStudent, isTutor]);

  const updateField = (field, value) => {
    setFormData((current) => ({ ...current, [field]: value }));
    setFieldErrors((current) => ({ ...current, [field]: '' }));
    setError('');

    if (field === 'email') {
      setOtpStep(false);
      setOtpVerified(false);
      setOtpCode('');
      setOtpTimer(0);
      setOtpEmail('');
      setSuccess('');
    }
  };

  const getFieldClassName = (field) => {
    const value = String(formData[field] ?? '').trim();

    if (fieldErrors[field]) {
      return 'field-invalid';
    }

    if (value) {
      return 'field-valid';
    }

    return '';
  };

  const handleRoleSelect = (role) => {
    setSelectedRole(role);
    setFormData((current) => ({
      ...current,
      role,
      nic_number: role === 'TUTOR' ? current.nic_number : '',
      subject: role === 'TUTOR' ? current.subject : '',
      school_name: role === 'STUDENT' ? current.school_name : '',
      grade: role === 'STUDENT' ? current.grade : '',
      siblings_count: role === 'STUDENT' ? current.siblings_count : '',
      guardian_name: role === 'STUDENT' ? current.guardian_name : '',
      guardian_job: role === 'STUDENT' ? current.guardian_job : '',
      guardian_nic: role === 'STUDENT' ? current.guardian_nic : ''
    }));
    setError('');
    setSuccess('');
    setFieldErrors({});
    setOtpVerified(false);
  };

  const handleBackToRoleSelection = () => {
    setSelectedRole('');
    setOtpStep(false);
    setOtpCode('');
    setOtpTimer(0);
    setOtpEmail('');
    setOtpVerified(false);
    setError('');
    setSuccess('');
    setFieldErrors({});
  };

  const validateForm = () => {
    const errors = {};

    if (!selectedRole) {
      return { role: 'Please select a role first.' };
    }

    if (!formData.full_name.trim() || formData.full_name.trim().length < 3) {
      errors.full_name = 'Please enter your full name (minimum 3 characters).';
    }

    if (!formData.email.trim() || !emailPattern.test(formData.email.trim())) {
      errors.email = 'Please enter a valid email address.';
    }

    if (!otpVerified) {
      return errors;
    }

    if (!formData.password || formData.password.length < 6) {
      errors.password = 'Password must be at least 6 characters long.';
    }

    if (!formData.phone.trim() || !sriLankanPhonePattern.test(formData.phone.trim())) {
      errors.phone = 'Please enter a valid 10-digit phone number.';
    }

    if (!formData.dob) {
      errors.dob = 'Please select your date of birth.';
    } else if (isStudent && formData.dob > studentDobMaxDate()) {
      errors.dob = 'Student must be at least 10 years old.';
    } else if (isTutor && formData.dob > tutorDobMaxDate()) {
      errors.dob = 'Tutor must be at least 20 years old.';
    }

    if (!formData.gender) {
      errors.gender = 'Please select your gender.';
    }

    if (!formData.address.trim() || formData.address.trim().length < 5) {
      errors.address = 'Please enter your address.';
    }

    if (isTutor) {
      if (!formData.nic_number.trim()) {
        errors.nic_number = 'Please enter your NIC number.';
      } else if (!sriLankanNicPattern.test(formData.nic_number.trim())) {
        errors.nic_number = 'Invalid NIC';
      }

      if (!formData.subject.trim()) {
        errors.subject = 'Please select your teaching subject.';
      }
    }

    if (isStudent) {
      if (!formData.school_name.trim()) {
        errors.school_name = 'Please enter your school name.';
      }

      if (!formData.grade.trim()) {
        errors.grade = 'Please select your grade.';
      }

      if (String(formData.siblings_count).trim() === '') {
        errors.siblings_count = 'Please enter siblings count.';
      } else if (!/^\d+$/.test(String(formData.siblings_count).trim())) {
        errors.siblings_count = 'Siblings count must be 0 or more.';
      }

      if (!formData.guardian_name.trim()) {
        errors.guardian_name = 'Please enter parent/guardian name.';
      }

      if (!formData.guardian_job.trim()) {
        errors.guardian_job = 'Please enter parent/guardian job.';
      }

      if (!formData.guardian_nic.trim()) {
        errors.guardian_nic = 'Please enter parent/guardian NIC number.';
      } else if (!sriLankanNicPattern.test(formData.guardian_nic.trim())) {
        errors.guardian_nic = 'Invalid NIC';
      }
    }

    return errors;
  };

  useEffect(() => {
    if (!otpStep || otpTimer <= 0) {
      return undefined;
    }

    const timerId = window.setInterval(() => {
      setOtpTimer((current) => {
        if (current <= 1) {
          window.clearInterval(timerId);
          return 0;
        }
        return current - 1;
      });
    }, 1000);

    return () => window.clearInterval(timerId);
  }, [otpStep, otpTimer]);

  const formatOtpTimer = () => {
    const minutes = Math.floor(otpTimer / 60);
    const seconds = otpTimer % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
  };

  const sendOtpRequest = async () => {
    await axios.post('/auth/send-otp', {
      full_name: formData.full_name,
      email: formData.email,
      role: selectedRole,
    });
    setOtpStep(true);
    setOtpEmail(visiblePayload.email);
    setOtpCode('');
    setOtpTimer(300);
    setOtpVerified(false);
  };

  const handleSubmit = async (e) => {
    e?.preventDefault?.();
    setError('');
    setSuccess('');

    const validationErrors = validateForm();
    setFieldErrors(validationErrors);
    if (Object.keys(validationErrors).length > 0) {
      if (validationErrors.role) {
        setError(validationErrors.role);
      }
      return;
    }

    if (!otpVerified) {
      setLoading(true);

      try {
        await sendOtpRequest();
        setSuccess(`Verification code sent to ${formData.email.trim()}.`);
      } catch (err) {
        const message = err.response?.data?.error || err.message || 'Registration failed';
        if (message.toLowerCase().includes('email is already') || message.toLowerCase().includes('separate email')) {
          setFieldErrors((current) => ({
            ...current,
            email: message,
          }));
          setError('');
        } else {
          setError(message);
        }
      } finally {
        setLoading(false);
      }
      return;
    }

    setLoading(true);
    try {
      await axios.post('/auth/register', visiblePayload);
      navigate('/login', {
        state: {
          registrationSuccess: 'Registration successful. Waiting for admin approval.',
          authTransition: 'from-register',
        },
      });
    } catch (err) {
      const message = err.response?.data?.error || err.message || 'Registration failed';
      if (message.toLowerCase().includes('nic')) {
        setFieldErrors((current) => ({
          ...current,
          [isTutor ? 'nic_number' : 'guardian_nic']: 'Invalid NIC'
        }));
        setError('');
      } else if (message.toLowerCase().includes('email is already') || message.toLowerCase().includes('separate email')) {
        setFieldErrors((current) => ({
          ...current,
          email: message,
        }));
        setError('');
      } else {
        setError(message);
      }
    } finally {
      setLoading(false);
    }
  };

  const handleVerifyOtp = async (e) => {
    e?.preventDefault?.();
    setError('');
    setSuccess('');

    if (!/^\d{6}$/.test(otpCode.trim())) {
      setError('Invalid verification code.');
      return;
    }

    setLoading(true);

    try {
      await axios.post('/auth/verify-otp', {
        otp: otpCode.trim(),
        registration: {
          full_name: formData.full_name,
          email: formData.email,
          role: selectedRole,
        },
      });
      setOtpVerified(true);
      setOtpStep(false);
      setSuccess('Email verified. Complete the remaining details and register.');
    } catch (err) {
      setError(err.response?.data?.error || 'Verification failed');
    } finally {
      setLoading(false);
    }
  };

  const handleResendOtp = async () => {
    setError('');
    setSuccess('');
    setLoading(true);

    try {
      await sendOtpRequest();
      setSuccess(`Verification code sent to ${formData.email.trim()}.`);
    } catch (err) {
      const message = err.response?.data?.error || 'Failed to resend verification code';
      if (message.toLowerCase().includes('email is already') || message.toLowerCase().includes('separate email')) {
        setFieldErrors((current) => ({
          ...current,
          email: message,
        }));
        setError('');
      } else {
        setError(message);
      }
    } finally {
      setLoading(false);
    }
  };

  const handleBackToForm = () => {
    setOtpStep(false);
    setOtpCode('');
    setOtpTimer(0);
    setOtpVerified(false);
    setError('');
    setSuccess('');
  };

  return (
    <div className="register-shell">
      <div className="register-backdrop register-backdrop-one" />
      <div className="register-backdrop register-backdrop-two" />
      <div className="container register-container">
        <div className="form-box register-card register-card-simple register-card-wide">
          <div className="register-form-panel register-form-panel-simple">
            {!selectedRole ? (
              <div className="register-role-screen register-screen-enter">
                <h3 className="register-form-title">Choose Your Role</h3>
                <p className="register-form-note">Select how you want to register before continuing to the full form.</p>
                <div className="register-role-card-grid">
                  {roleCards.map((role) => (
                    <button
                      key={role.key}
                      type="button"
                      className="register-role-card"
                      onClick={() => handleRoleSelect(role.key)}
                    >
                      <span className="register-role-card-icon">{role.icon}</span>
                      <span className="register-role-card-title">{role.title}</span>
                      <span className="register-role-card-copy">{role.description}</span>
                    </button>
                  ))}
                </div>
                <p className="register-login-copy">
                  Already have an account?{' '}
                  <button
                    type="button"
                    className="auth-switch-link"
                    onClick={() => navigate('/login', { state: { authTransition: 'from-register' } })}
                  >
                    Login
                  </button>
                </p>
              </div>
            ) : (
              <div className="register-screen-enter">
                <div className="register-form-header">
                  <div>
                    <h3 className="register-form-title">{isStudent ? 'Student Registration' : 'Tutor Registration'}</h3>
                    <p className="register-form-note">Complete the required information below to create your {isStudent ? 'student' : 'tutor'} account.</p>
                  </div>
                  <button type="button" className="secondary-button register-back-button" onClick={handleBackToRoleSelection}>
                    Back
                  </button>
                </div>

                {error && <div className="error">{error}</div>}
                {success && <div className="success">{success}</div>}

                <form className="register-form-grid register-form-grid-wide" onSubmit={(event) => event.preventDefault()}>
                  <div className="register-section-heading register-form-span">Common Information</div>

                  <div className="form-group">
                    <label htmlFor="register-full-name">Full Name</label>
                    <input id="register-full-name" className={getFieldClassName('full_name')} type="text" value={formData.full_name} onChange={(e) => updateField('full_name', e.target.value)} required />
                    {fieldErrors.full_name ? <div className="field-error-message">{fieldErrors.full_name}</div> : null}
                  </div>

                  <div className="form-group">
                    <label htmlFor="register-email">Email Address</label>
                    <div className="register-inline-action">
                      <input id="register-email" className={getFieldClassName('email')} type="email" value={formData.email} onChange={(e) => updateField('email', e.target.value)} required disabled={otpStep || otpVerified} />
                      <button
                        type="button"
                        className="secondary-button register-inline-button"
                        onClick={handleSubmit}
                        disabled={loading || otpVerified}
                      >
                        {otpVerified ? 'Verified' : loading && !otpStep ? 'Sending...' : 'Verify'}
                      </button>
                    </div>
                    <small className="leave-request-note">
                      Testing mode: the same email can be used for multiple student or tutor registrations. Use a different password for each account.
                    </small>
                    {otpEmail ? (
                      <small className="leave-request-note">
                        OTP destination: {otpEmail}
                      </small>
                    ) : null}
                    {fieldErrors.email ? <div className="field-error-message">{fieldErrors.email}</div> : null}
                  </div>

                  {otpStep ? (
                    <div className="form-group">
                      <label htmlFor="register-otp">OTP Code</label>
                      <div className="register-inline-action">
                        <input
                          id="register-otp"
                          type="text"
                          inputMode="numeric"
                          maxLength={6}
                          value={otpCode}
                          onChange={(event) => {
                            setOtpCode(event.target.value.replace(/\D/g, '').slice(0, 6));
                            setError('');
                          }}
                          placeholder="Enter 6-digit OTP"
                          required
                        />
                        <button
                          type="button"
                          className="register-inline-button"
                          onClick={handleVerifyOtp}
                          disabled={loading}
                        >
                          {loading ? 'Verifying...' : 'Verify OTP'}
                        </button>
                      </div>
                      <div className="register-otp-meta">
                        <span>Sent to {otpEmail || formData.email}</span>
                        <span>Expires in: {formatOtpTimer()}</span>
                        <button
                          type="button"
                          className="auth-switch-link"
                          onClick={handleResendOtp}
                          disabled={loading}
                        >
                          Resend OTP
                        </button>
                      </div>
                    </div>
                  ) : null}

                  <div className="form-group">
                    <label htmlFor="register-password">Password</label>
                    <input id="register-password" className={getFieldClassName('password')} type="password" value={formData.password} onChange={(e) => updateField('password', e.target.value)} required minLength={6} disabled={!otpVerified} />
                    {fieldErrors.password ? <div className="field-error-message">{fieldErrors.password}</div> : null}
                  </div>

                  <div className="form-group">
                    <label htmlFor="register-phone">Phone Number</label>
                    <input
                      id="register-phone"
                      className={getFieldClassName('phone')}
                      type="tel"
                      inputMode="numeric"
                      maxLength={10}
                      placeholder="07XXXXXXXX"
                      value={formData.phone}
                      onChange={(e) => updateField('phone', e.target.value.replace(/\D/g, '').slice(0, 10))}
                      required
                      disabled={!otpVerified}
                    />
                    {fieldErrors.phone ? <div className="field-error-message">{fieldErrors.phone}</div> : null}
                  </div>

                  <div className="form-group">
                    <label htmlFor="register-dob">Date of Birth</label>
                    <input id="register-dob" className={getFieldClassName('dob')} type="date" value={formData.dob} onChange={(e) => updateField('dob', e.target.value)} required disabled={!otpVerified} max={isStudent ? studentDobMaxDate() : isTutor ? tutorDobMaxDate() : undefined} />
                    {fieldErrors.dob ? <div className="field-error-message">{fieldErrors.dob}</div> : null}
                  </div>

                  <div className="form-group">
                    <label htmlFor="register-gender">Gender</label>
                    <select id="register-gender" className={getFieldClassName('gender')} value={formData.gender} onChange={(e) => updateField('gender', e.target.value)} required disabled={!otpVerified}>
                      <option value="">Select Gender</option>
                      <option value="Male">Male</option>
                      <option value="Female">Female</option>
                      <option value="Other">Other</option>
                    </select>
                    {fieldErrors.gender ? <div className="field-error-message">{fieldErrors.gender}</div> : null}
                  </div>

                  <div className="form-group register-form-span">
                    <label htmlFor="register-address">Address</label>
                    <textarea id="register-address" className={getFieldClassName('address')} rows="3" value={formData.address} onChange={(e) => updateField('address', e.target.value)} required disabled={!otpVerified} />
                    {fieldErrors.address ? <div className="field-error-message">{fieldErrors.address}</div> : null}
                  </div>

                  {isTutor && (
                    <>
                      <div className="register-section-heading register-form-span">Tutor Information</div>
                      <div className="form-group">
                        <label htmlFor="register-nic">NIC Number</label>
                        <input id="register-nic" className={getFieldClassName('nic_number')} type="text" value={formData.nic_number} onChange={(e) => updateField('nic_number', e.target.value)} required disabled={!otpVerified} />
                        {fieldErrors.nic_number ? <div className="field-error-message">{fieldErrors.nic_number}</div> : null}
                      </div>
                      <div className="form-group">
                        <label htmlFor="register-subject">Subject(s)</label>
                        <select id="register-subject" className={getFieldClassName('subject')} value={formData.subject} onChange={(e) => updateField('subject', e.target.value)} required disabled={!otpVerified}>
                          <option value="">Select Subject</option>
                          {tutorSubjects.map((subject) => (
                            <option key={subject} value={subject}>{subject}</option>
                          ))}
                        </select>
                        {fieldErrors.subject ? <div className="field-error-message">{fieldErrors.subject}</div> : null}
                      </div>
                    </>
                  )}

                  {isStudent && (
                    <>
                      <div className="register-section-heading register-form-span">Student Information</div>
                      <div className="form-group">
                        <label htmlFor="register-school">School Name</label>
                        <input id="register-school" className={getFieldClassName('school_name')} type="text" value={formData.school_name} onChange={(e) => updateField('school_name', e.target.value)} required disabled={!otpVerified} />
                        {fieldErrors.school_name ? <div className="field-error-message">{fieldErrors.school_name}</div> : null}
                      </div>
                      <div className="form-group">
                        <label htmlFor="register-grade">Grade</label>
                        <select id="register-grade" className={getFieldClassName('grade')} value={formData.grade} onChange={(e) => updateField('grade', e.target.value)} required disabled={!otpVerified}>
                          <option value="">Select Grade</option>
                          {studentGrades.map((grade) => (
                            <option key={grade} value={grade}>{grade}</option>
                          ))}
                        </select>
                        {fieldErrors.grade ? <div className="field-error-message">{fieldErrors.grade}</div> : null}
                      </div>
                      <div className="form-group">
                        <label htmlFor="register-siblings">Number of Siblings studying in our tuition</label>
                        <input id="register-siblings" className={getFieldClassName('siblings_count')} type="number" min="0" value={formData.siblings_count} onChange={(e) => updateField('siblings_count', e.target.value)} required disabled={!otpVerified} />
                        {fieldErrors.siblings_count ? <div className="field-error-message">{fieldErrors.siblings_count}</div> : null}
                      </div>
                      <div className="form-group">
                        <label htmlFor="register-guardian-name">Parent/Guardian Name</label>
                        <input id="register-guardian-name" className={getFieldClassName('guardian_name')} type="text" value={formData.guardian_name} onChange={(e) => updateField('guardian_name', e.target.value)} required disabled={!otpVerified} />
                        {fieldErrors.guardian_name ? <div className="field-error-message">{fieldErrors.guardian_name}</div> : null}
                      </div>
                      <div className="form-group">
                        <label htmlFor="register-guardian-job">Parent/Guardian Job</label>
                        <input id="register-guardian-job" className={getFieldClassName('guardian_job')} type="text" value={formData.guardian_job} onChange={(e) => updateField('guardian_job', e.target.value)} required disabled={!otpVerified} />
                        {fieldErrors.guardian_job ? <div className="field-error-message">{fieldErrors.guardian_job}</div> : null}
                      </div>
                      <div className="form-group">
                        <label htmlFor="register-guardian-nic">Parent/Guardian NIC Number</label>
                        <input id="register-guardian-nic" className={getFieldClassName('guardian_nic')} type="text" value={formData.guardian_nic} onChange={(e) => updateField('guardian_nic', e.target.value)} required disabled={!otpVerified} />
                        {fieldErrors.guardian_nic ? <div className="field-error-message">{fieldErrors.guardian_nic}</div> : null}
                      </div>
                    </>
                  )}

                  <div className="register-form-span profile-footer">
                    {!otpStep ? (
                      <button
                        className="register-submit"
                        type="button"
                        onClick={handleSubmit}
                        disabled={loading || !otpVerified}
                      >
                        {loading ? 'Registering...' : `Register as ${isStudent ? 'Student' : 'Tutor'}`}
                      </button>
                    ) : (
                      <button
                        className="register-submit"
                        type="button"
                        onClick={handleVerifyOtp}
                        disabled={loading}
                      >
                        {loading ? 'Verifying...' : 'Register'}
                      </button>
                    )}
                  </div>
                </form>
                {otpStep ? (
                  <div className="profile-footer">
                    <button
                      type="button"
                      className="secondary-button"
                      onClick={handleBackToForm}
                      disabled={loading}
                    >
                      Back
                    </button>
                  </div>
                ) : null}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default Register;

