const loginBtn = document.getElementById('loginBtn');
const loginAlert = document.getElementById('loginAlert');
const signupAlert = document.getElementById('signupAlert');
const signupProgress = document.getElementById('signupProgress');
const signupSteps = document.getElementById('signupSteps');
const tabButtons = Array.from(document.querySelectorAll('.tab-btn'));
const forms = Array.from(document.querySelectorAll('.form'));
const openSignupLink = document.getElementById('openSignupLink');
const backToLoginLink = document.getElementById('backToLoginLink');
const appBaseUrl = window.appBaseUrl || `${window.location.origin}/`;
const signupOptions = window.signupOptions || {};
const psgcApiUrls = {
  provinces: 'https://psgc.gitlab.io/api/provinces.json',
  municipalities: 'https://psgc.gitlab.io/api/municipalities.json',
  cities: 'https://psgc.gitlab.io/api/cities.json',
  barangays: 'https://psgc.gitlab.io/api/barangays.json',
};
const psgcData = {
  provinces: [],
  municipalities: [],
  cities: [],
  barangays: [],
};
const signupState = {
  firstName: '',
  lastName: '',
  college: '',
  course: '',
  graduationYear: '',
  provinceCode: '',
  municipalityCode: '',
  barangayCode: '',
  province: '',
  municipality: '',
  barangay: '',
  contactNumber: '',
  email: '',
  password: '',
  confirmPassword: '',
  isEmailVerified: false,
  otpSent: false,
  otpCooldownSeconds: 0,
  otpCode: '',
  devOtp: null,
  mailSent: false,
};
let signupStep = 1;
let countdownTimer = null;
let otpCooldownTimer = null;
let psgcLoaded = false;
let psgcLoading = false;

function appUrl(path) {
  return new URL(path.replace(/^\/+/, ''), appBaseUrl).toString();
}

function postForm(url, payload) {
  const formData = new FormData();

  Object.entries(payload).forEach(([key, value]) => {
    formData.append(key, value);
  });

  return fetch(appUrl(url), {
    method: 'POST',
    body: formData,
    credentials: 'same-origin',
  });
}

function showAlert(element, message, type, duration = 3000) {
  if (!element) return;

  element.textContent = message;
  element.className = `alert ${type}`;
  element.style.display = 'block';

  if (duration > 0) {
    setTimeout(() => {
      element.style.display = 'none';
    }, duration);
  }
}

function hideSignupAlert() {
  if (!signupAlert) return;
  signupAlert.textContent = '';
  signupAlert.className = 'alert';
  signupAlert.style.display = 'none';
}

function switchTab(targetId) {
  tabButtons.forEach((button) => {
    button.classList.toggle('active', button.dataset.target === targetId);
  });

  forms.forEach((form) => {
    form.classList.toggle('active', form.id === targetId);
  });
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function renderOptions(items, selectedValue, placeholder) {
  const options = [`<option value="">${escapeHtml(placeholder)}</option>`];

  items.forEach((item) => {
    const selected = item === selectedValue ? ' selected' : '';
    options.push(`<option value="${escapeHtml(item)}"${selected}>${escapeHtml(item)}</option>`);
  });

  return options.join('');
}

function renderObjectOptions(items, selectedValue, placeholder) {
  const options = [`<option value="">${escapeHtml(placeholder)}</option>`];

  items.forEach((item) => {
    const selected = item.value === selectedValue ? ' selected' : '';
    options.push(`<option value="${escapeHtml(item.value)}"${selected}>${escapeHtml(item.label)}</option>`);
  });

  return options.join('');
}

function getCourses(college) {
  return Array.isArray(signupOptions[college]) ? signupOptions[college] : [];
}

function sortByName(items) {
  return [...items].sort((first, second) => String(first.name || '').localeCompare(String(second.name || '')));
}

function toAddressOption(item) {
  return {
    value: item.code,
    label: item.name,
  };
}

function getProvinces() {
  return sortByName(psgcData.provinces).map(toAddressOption);
}

function getLocalities() {
  if (!signupState.provinceCode) return [];

  const localities = [...psgcData.municipalities, ...psgcData.cities]
    .filter((locality) => locality.provinceCode === signupState.provinceCode);

  return sortByName(localities).map(toAddressOption);
}

function getBarangays() {
  if (!signupState.municipalityCode) return [];

  return sortByName(psgcData.barangays)
    .filter((barangay) => {
      const localityCode = barangay.cityCode || barangay.municipalityCode;
      return localityCode === signupState.municipalityCode;
    })
    .map(toAddressOption);
}

function findAddressName(collectionName, code) {
  return psgcData[collectionName].find((item) => item.code === code)?.name || '';
}

function syncAddressNames() {
  signupState.province = findAddressName('provinces', signupState.provinceCode);
  signupState.municipality = findAddressName('municipalities', signupState.municipalityCode)
    || findAddressName('cities', signupState.municipalityCode);
  signupState.barangay = findAddressName('barangays', signupState.barangayCode);
}

async function fetchPsgcCollection(key) {
  const response = await fetch(psgcApiUrls[key], { cache: 'force-cache' });

  if (!response.ok) {
    throw new Error(`Unable to load ${key}.`);
  }

  return response.json();
}

async function loadPsgcAddressData() {
  if (psgcLoaded || psgcLoading) return;

  psgcLoading = true;
  try {
    const [provinces, municipalities, cities, barangays] = await Promise.all([
      fetchPsgcCollection('provinces'),
      fetchPsgcCollection('municipalities'),
      fetchPsgcCollection('cities'),
      fetchPsgcCollection('barangays'),
    ]);

    psgcData.provinces = provinces;
    psgcData.municipalities = municipalities;
    psgcData.cities = cities;
    psgcData.barangays = barangays;
    psgcLoaded = true;
    syncAddressNames();
    renderStep();
  } catch (error) {
    showAlert(signupAlert, 'Unable to load address options. Please refresh and try again.', 'error', 0);
  } finally {
    psgcLoading = false;
  }
}

function syncSignupState() {
  Object.keys(signupState).forEach((key) => {
    const field = document.querySelector(`[data-signup-field="${key}"]`);
    if (field) signupState[key] = field.value.trim();
  });
}

function getPasswordRules() {
  return {
    length: signupState.password.length >= 8,
    uppercase: /[A-Z]/.test(signupState.password),
    number: /\d/.test(signupState.password),
    match: signupState.password.length > 0 && signupState.password === signupState.confirmPassword,
  };
}

function renderProgress() {
  if (!signupProgress) return;

  signupProgress.innerHTML = [1, 2, 3, 4].map((step) => {
    const stateClass = step < signupStep ? 'completed' : step === signupStep ? 'active' : '';
    const label = step < signupStep ? '&#10003;' : step;
    return `<div class="progress-step ${stateClass}"><span>${label}</span></div>`;
  }).join('');
}

function renderFieldError(fieldName, errors) {
  return errors[fieldName] ? `<div class="field-error">${escapeHtml(errors[fieldName])}</div>` : '';
}

function invalidClass(fieldName, errors) {
  return errors[fieldName] ? ' is-invalid' : '';
}

function renderPasswordChecklist() {
  const rules = getPasswordRules();
  const items = [
    ['length', 'At least 8 characters'],
    ['uppercase', 'At least 1 uppercase letter'],
    ['number', 'At least 1 number'],
    ['match', 'Passwords match'],
  ];

  return `
    <ul class="password-checklist">
      ${items.map(([key, label]) => `
        <li class="${rules[key] ? 'checked' : ''}">
          <span>${rules[key] ? '&#10003;' : '&#9675;'}</span>${label}
        </li>
      `).join('')}
    </ul>
  `;
}

function renderStep(errors = {}) {
  if (!signupSteps) return;

  renderProgress();

  if (signupStep === 1) {
    signupSteps.innerHTML = `
      <div class="signup-step">
        <div class="form-group">
          <label>First Name</label>
          <input type="text" id="signupFirstName" data-signup-field="firstName" value="${escapeHtml(signupState.firstName)}" placeholder="Enter your first name" autocomplete="given-name" class="${invalidClass('firstName', errors)}">
          ${renderFieldError('firstName', errors)}
        </div>
        <div class="form-group">
          <label>Last Name</label>
          <input type="text" id="signupLastName" data-signup-field="lastName" value="${escapeHtml(signupState.lastName)}" placeholder="Enter your last name" autocomplete="family-name" class="${invalidClass('lastName', errors)}">
          ${renderFieldError('lastName', errors)}
        </div>
        <div class="form-group">
          <label>College</label>
          <select id="signupCollege" data-signup-field="college" autocomplete="organization" class="${invalidClass('college', errors)}">
            ${renderOptions(Object.keys(signupOptions), signupState.college, 'Select your college')}
          </select>
          ${renderFieldError('college', errors)}
        </div>
        <div class="form-group">
          <label>Course</label>
          <select id="signupCourse" data-signup-field="course" autocomplete="organization-title" ${getCourses(signupState.college).length ? '' : 'disabled'} class="${invalidClass('course', errors)}">
            ${renderOptions(getCourses(signupState.college), signupState.course, 'Select your course')}
          </select>
          ${renderFieldError('course', errors)}
        </div>
        <div class="form-group">
          <label>Year Graduated</label>
          <input type="number" id="signupGraduationYear" data-signup-field="graduationYear" value="${escapeHtml(signupState.graduationYear)}" placeholder="Enter your graduation year" min="1900" max="2099" inputmode="numeric" class="${invalidClass('graduationYear', errors)}">
          ${renderFieldError('graduationYear', errors)}
        </div>
        <button class="btn-signup" id="signupBtn" type="button">Next</button>
      </div>
    `;
  }

  if (signupStep === 2) {
    signupSteps.innerHTML = `
      <div class="signup-step">
        <div class="form-group">
          <label>Province</label>
          <select id="signupProvince" data-signup-field="provinceCode" ${psgcLoaded ? '' : 'disabled'} class="${invalidClass('province', errors)}">
            ${renderObjectOptions(getProvinces(), signupState.provinceCode, psgcLoaded ? 'Select your province' : 'Loading address options...')}
          </select>
          ${renderFieldError('province', errors)}
        </div>
        <div class="form-group">
          <label>Municipality / City</label>
          <select id="signupMunicipality" data-signup-field="municipalityCode" ${getLocalities().length ? '' : 'disabled'} class="${invalidClass('municipality', errors)}">
            ${renderObjectOptions(getLocalities(), signupState.municipalityCode, 'Select your municipality or city')}
          </select>
          ${renderFieldError('municipality', errors)}
        </div>
        <div class="form-group">
          <label>Barangay</label>
          <select id="signupBarangay" data-signup-field="barangayCode" ${getBarangays().length ? '' : 'disabled'} class="${invalidClass('barangay', errors)}">
            ${renderObjectOptions(getBarangays(), signupState.barangayCode, 'Select your barangay')}
          </select>
          ${renderFieldError('barangay', errors)}
        </div>
        <div class="form-group">
          <label>Contact Number</label>
          <input type="text" id="signupContactNumber" data-signup-field="contactNumber" value="${escapeHtml(signupState.contactNumber)}" placeholder="09XXXXXXXXX" maxlength="11" inputmode="numeric" class="${invalidClass('contactNumber', errors)}">
          ${renderFieldError('contactNumber', errors)}
        </div>
        <div class="wizard-actions">
          <button class="btn-back" id="signupBackBtn" type="button">Back</button>
          <button class="btn-signup" id="signupBtn" type="button">Next</button>
        </div>
      </div>
    `;
  }

  if (signupStep === 3) {
    const isVerified = signupState.isEmailVerified;
    const isOtpSent = signupState.otpSent;
    const cooldown = signupState.otpCooldownSeconds;

    signupSteps.innerHTML = `
      <div class="signup-step">
        <div class="form-group">
          <div class="email-label-row">
            <label for="signupEmail">Email Address</label>
            ${isVerified ? `
              <span class="email-verified-badge">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg>
                Email Verified
              </span>
            ` : ''}
          </div>
          <div class="email-input-row">
            <input type="email" id="signupEmail" data-signup-field="email" value="${escapeHtml(signupState.email)}" placeholder="Enter your Gmail address" autocomplete="email" class="${invalidClass('email', errors)}" ${isVerified ? 'readonly style="background:#f8fafc; color:#1e293b; font-weight:600;"' : ''}>
            ${isVerified ? `
              <button type="button" class="btn-edit-email" id="editEmailBtn" title="Use a different email">Change</button>
            ` : `
              <button type="button" class="btn-send-otp" id="sendOtpBtn" ${cooldown > 0 ? 'disabled' : ''}>
                ${cooldown > 0 ? `Resend in ${cooldown}s` : (isOtpSent ? 'Resend Code' : 'Send Code')}
              </button>
            `}
          </div>
          ${renderFieldError('email', errors)}

          ${!isVerified && isOtpSent ? `
            <div class="otp-box">
              <div class="otp-box-header">
                <div class="otp-box-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </div>
                <div class="otp-box-text">
                  <h4>${signupState.mailSent ? 'Verification Code Sent to Email' : 'Email Verification Code (Local Mode)'}</h4>
                  <p>${signupState.mailSent
                    ? `A 6-digit code has been delivered to <strong>${escapeHtml(signupState.email)}</strong>. Please check your inbox (and spam folder).`
                    : `SMTP is not yet configured in <code>.env</code> on your local server. For testing right now, use the code below (or click <strong>Quick Fill</strong>):`
                  }</p>
                </div>
              </div>

              <div class="otp-input-row">
                <input type="text" id="signupOtpCode" class="otp-input" maxlength="6" value="${escapeHtml(signupState.otpCode)}" placeholder="••••••" inputmode="numeric" autocomplete="one-time-code">
                <button type="button" class="btn-verify-otp" id="verifyOtpBtn">Verify</button>
              </div>

              <div class="otp-footer">
                <span>Didn't receive code?</span>
                ${cooldown > 0 ? `
                  <span style="color:#64748b;">Resend in <strong>${cooldown}s</strong></span>
                ` : `
                  <button type="button" class="btn-resend-link" id="resendOtpLink">Resend Code</button>
                `}
              </div>

              ${signupState.devOtp ? `
                <div class="otp-dev-hint">
                  <span>Dev OTP: <strong>${escapeHtml(signupState.devOtp)}</strong></span>
                  <button type="button" class="btn-fill-dev-otp" id="fillDevOtpBtn">Quick Fill</button>
                </div>
              ` : ''}
            </div>
          ` : ''}
        </div>

        <div class="form-group">
          <label>Password</label>
          <input type="password" id="signupPassword" data-signup-field="password" value="${escapeHtml(signupState.password)}" placeholder="Create a password" autocomplete="new-password" class="${invalidClass('password', errors)}">
          ${renderFieldError('password', errors)}
        </div>
        <div class="form-group">
          <label>Confirm Password</label>
          <input type="password" id="signupConfirmPassword" data-signup-field="confirmPassword" value="${escapeHtml(signupState.confirmPassword)}" placeholder="Confirm your password" autocomplete="new-password" class="${invalidClass('confirmPassword', errors)}">
          ${renderFieldError('confirmPassword', errors)}
          ${renderPasswordChecklist()}
        </div>
        <div class="wizard-actions">
          <button class="btn-back" id="signupBackBtn" type="button">Back</button>
          <button class="btn-signup" id="signupBtn" type="button">Create Account</button>
        </div>
      </div>
    `;
  }

  if (signupStep === 4) {
    signupSteps.innerHTML = `
      <div class="signup-step signup-confirmation">
        <h2>Account created successfully!</h2>
        <p>Welcome, ${escapeHtml(`${signupState.firstName} ${signupState.lastName}`.trim())}.</p>
        <p id="signupCountdown">You will be redirected to the login page in 5 seconds...</p>
      </div>
    `;
  }

  attachSignupStepEvents();
}

function validateStep(step) {
  const errors = {};

  if (step === 1) {
    const year = Number(signupState.graduationYear);
    if (!signupState.firstName) errors.firstName = 'First name is required.';
    if (!signupState.lastName) errors.lastName = 'Last name is required.';
    if (!signupState.college) errors.college = 'College is required.';
    if (!signupState.course) errors.course = 'Course is required.';
    if (!signupState.graduationYear) {
      errors.graduationYear = 'Year graduated is required.';
    } else if (!Number.isInteger(year) || year < 1900 || year > 2099) {
      errors.graduationYear = 'Enter a year from 1900 to 2099.';
    }
  }

  if (step === 2) {
    syncAddressNames();
    if (!psgcLoaded) errors.province = 'Address options are still loading.';
    if (!signupState.provinceCode) errors.province = 'Province is required.';
    if (!signupState.municipalityCode) errors.municipality = 'Municipality or city is required.';
    if (!signupState.barangayCode) errors.barangay = 'Barangay is required.';
    if (!signupState.contactNumber) {
      errors.contactNumber = 'Contact number is required.';
    } else if (!/^09\d{9}$/.test(signupState.contactNumber)) {
      errors.contactNumber = 'Enter exactly 11 digits starting with 09.';
    }
  }

  if (step === 3) {
    const rules = getPasswordRules();
    if (!signupState.email) {
      errors.email = 'Email address is required.';
    } else if (!signupState.email.toLowerCase().endsWith('@gmail.com')) {
      errors.email = 'Email address must end with @gmail.com.';
    } else if (!signupState.isEmailVerified) {
      errors.email = 'Please verify your email address with the 6-digit code first.';
    }
    if (!signupState.password) {
      errors.password = 'Password is required.';
    } else if (!rules.length || !rules.uppercase || !rules.number) {
      errors.password = 'Password does not meet all requirements.';
    }
    if (!signupState.confirmPassword) {
      errors.confirmPassword = 'Confirm password is required.';
    } else if (!rules.match) {
      errors.confirmPassword = 'Passwords must match.';
    }
  }

  return errors;
}

function hasErrors(errors) {
  return Object.keys(errors).length > 0;
}

async function checkSignupRecordExists() {
  try {
    const response = await postForm('auth/check-signup-record', {
      first_name: signupState.firstName,
      last_name: signupState.lastName,
      college: signupState.college,
      course: signupState.course,
      graduation_year: signupState.graduationYear,
    });
    const result = await response.json();

    if (!response.ok || !result.success) {
      showAlert(signupAlert, result.message || 'No matching alumni record was found.', 'error', 0);
      return false;
    }

    return true;
  } catch (error) {
    showAlert(signupAlert, 'Unable to verify your alumni record right now. Please try again.', 'error', 0);
    return false;
  }
}

async function handleSignup() {
  syncSignupState();
  hideSignupAlert();

  const errors = validateStep(3);
  if (hasErrors(errors)) {
    renderStep(errors);
    return;
  }

  try {
    const response = await postForm('auth/signup', {
      first_name: signupState.firstName,
      last_name: signupState.lastName,
      college: signupState.college,
      course: signupState.course,
      graduation_year: signupState.graduationYear,
      province: signupState.province,
      municipality: signupState.municipality,
      barangay: signupState.barangay,
      contact_number: signupState.contactNumber,
      email: signupState.email,
      password: signupState.password,
      confirm_password: signupState.confirmPassword,
    });
    const result = await response.json();

    if (!response.ok || !result.success) {
      showAlert(signupAlert, result.message || 'Unable to create your account right now.', 'error', 0);
      return;
    }

    showConfirmation();
  } catch (error) {
    showAlert(signupAlert, 'Unable to create your account right now. Please try again.', 'error', 0);
  }
}

async function goToNextSignupStep() {
  syncSignupState();
  hideSignupAlert();

  const errors = validateStep(signupStep);
  if (hasErrors(errors)) {
    renderStep(errors);
    return;
  }

  if (signupStep === 1 && !await checkSignupRecordExists()) {
    return;
  }

  if (signupStep === 3) {
    if (!signupState.isEmailVerified) {
      showAlert(signupAlert, 'Please verify your email address with the 6-digit code first.', 'error');
      renderStep({ email: 'Email verification required.' });
      return;
    }
    handleSignup();
    return;
  }

  signupStep += 1;
  renderStep();
}

function goToPreviousSignupStep() {
  syncSignupState();
  hideSignupAlert();
  signupStep = Math.max(1, signupStep - 1);
  renderStep();
}

function showConfirmation() {
  signupStep = 4;
  renderStep();

  let seconds = 5;
  const countdown = document.getElementById('signupCountdown');
  clearInterval(countdownTimer);

  countdownTimer = setInterval(() => {
    seconds -= 1;

    if (seconds <= 0) {
      clearInterval(countdownTimer);
      document.getElementById('loginIdentifier').value = signupState.email;
      document.getElementById('loginPassword').value = '';
      switchTab('loginForm');
      return;
    }

    if (countdown) {
      countdown.textContent = `You will be redirected to the login page in ${seconds} seconds...`;
    }
  }, 1000);
}

function startOtpCooldown(seconds = 60) {
  clearInterval(otpCooldownTimer);
  signupState.otpCooldownSeconds = seconds;

  otpCooldownTimer = setInterval(() => {
    signupState.otpCooldownSeconds -= 1;
    if (signupState.otpCooldownSeconds <= 0) {
      clearInterval(otpCooldownTimer);
      signupState.otpCooldownSeconds = 0;
    }

    if (signupStep === 3) {
      const sendBtn = document.getElementById('sendOtpBtn');
      if (sendBtn) {
        if (signupState.otpCooldownSeconds > 0) {
          sendBtn.textContent = `Resend in ${signupState.otpCooldownSeconds}s`;
          sendBtn.disabled = true;
        } else {
          sendBtn.textContent = 'Resend Code';
          sendBtn.disabled = false;
        }
      }

      const resendFooter = document.querySelector('.otp-footer');
      if (resendFooter) {
        resendFooter.innerHTML = `
          <span>Didn't receive code?</span>
          ${signupState.otpCooldownSeconds > 0 ? `
            <span style="color:#64748b;">Resend in <strong>${signupState.otpCooldownSeconds}s</strong></span>
          ` : `
            <button type="button" class="btn-resend-link" id="resendOtpLink">Resend Code</button>
          `}
        `;
        document.getElementById('resendOtpLink')?.addEventListener('click', handleSendOtp);
      }
    }
  }, 1000);
}

async function checkServerOtpStatus() {
  try {
    const response = await fetch(appUrl('auth/otp-status'), { credentials: 'same-origin' });
    if (!response.ok) return;
    const data = await response.json();

    if (data.verified_email) {
      signupState.email = data.verified_email;
      signupState.isEmailVerified = true;
      signupState.otpSent = false;
    } else if (data.has_pending_otp && data.pending_email) {
      signupState.email = data.pending_email;
      signupState.otpSent = true;
      signupState.isEmailVerified = false;
      if (data.cooldown_remaining > 0) {
        startOtpCooldown(data.cooldown_remaining);
      }
    }

    if (signupStep === 3) {
      renderStep();
    }
  } catch (err) {
    // Non-blocking status check
  }
}

async function handleSendOtp() {
  syncSignupState();
  hideSignupAlert();

  const email = signupState.email.trim();
  if (!email) {
    showAlert(signupAlert, 'Please enter your Gmail address first.', 'error');
    document.getElementById('signupEmail')?.focus();
    return;
  }

  if (!email.toLowerCase().endsWith('@gmail.com')) {
    showAlert(signupAlert, 'Email address must end with @gmail.com.', 'error');
    document.getElementById('signupEmail')?.focus();
    return;
  }

  const sendBtn = document.getElementById('sendOtpBtn');
  const originalText = sendBtn ? sendBtn.textContent : 'Send Code';
  if (sendBtn) {
    sendBtn.disabled = true;
    sendBtn.textContent = 'Sending...';
  }

  try {
    const response = await postForm('auth/send-otp', { email });
    const result = await response.json();

    if (!response.ok || !result.success) {
      showAlert(signupAlert, result.message || 'Unable to send verification code.', 'error', 5000);
      if (sendBtn) {
        sendBtn.disabled = false;
        sendBtn.textContent = originalText;
      }
      return;
    }

    signupState.otpSent = true;
    signupState.isEmailVerified = false;
    signupState.devOtp = result.dev_otp || null;
    signupState.mailSent = Boolean(result.mail_sent);
    startOtpCooldown(result.cooldown || 60);

    showAlert(signupAlert, result.message || `Verification code sent to ${email}`, 'success', 4000);
    renderStep();

    setTimeout(() => {
      document.getElementById('signupOtpCode')?.focus();
    }, 100);
  } catch (err) {
    showAlert(signupAlert, 'Network error sending verification code. Please try again.', 'error', 4000);
    if (sendBtn) {
      sendBtn.disabled = false;
      sendBtn.textContent = originalText;
    }
  }
}

async function handleVerifyOtp() {
  syncSignupState();
  hideSignupAlert();

  const otpInput = document.getElementById('signupOtpCode');
  const otpCode = (otpInput ? otpInput.value : signupState.otpCode).trim();

  if (!otpCode || !/^\d{6}$/.test(otpCode)) {
    showAlert(signupAlert, 'Please enter the valid 6-digit verification code.', 'error');
    otpInput?.focus();
    return;
  }

  const verifyBtn = document.getElementById('verifyOtpBtn');
  if (verifyBtn) {
    verifyBtn.disabled = true;
    verifyBtn.textContent = 'Verifying...';
  }

  try {
    const response = await postForm('auth/verify-otp', {
      email: signupState.email,
      otp: otpCode,
    });
    const result = await response.json();

    if (!response.ok || !result.success) {
      showAlert(signupAlert, result.message || 'Invalid verification code.', 'error', 4000);
      if (verifyBtn) {
        verifyBtn.disabled = false;
        verifyBtn.textContent = 'Verify';
      }
      return;
    }

    signupState.isEmailVerified = true;
    signupState.otpSent = false;
    signupState.otpCode = '';
    signupState.devOtp = null;
    clearInterval(otpCooldownTimer);
    signupState.otpCooldownSeconds = 0;

    showAlert(signupAlert, 'Email address verified successfully!', 'success', 3000);
    renderStep();
  } catch (err) {
    showAlert(signupAlert, 'Unable to verify code right now. Please try again.', 'error', 4000);
    if (verifyBtn) {
      verifyBtn.disabled = false;
      verifyBtn.textContent = 'Verify';
    }
  }
}

function handleEditEmail() {
  signupState.isEmailVerified = false;
  signupState.otpSent = false;
  signupState.otpCode = '';
  signupState.devOtp = null;
  renderStep();
  setTimeout(() => {
    document.getElementById('signupEmail')?.focus();
  }, 50);
}

function attachSignupStepEvents() {
  document.getElementById('signupBtn')?.addEventListener('click', goToNextSignupStep);
  document.getElementById('signupBackBtn')?.addEventListener('click', goToPreviousSignupStep);
  document.getElementById('sendOtpBtn')?.addEventListener('click', handleSendOtp);
  document.getElementById('verifyOtpBtn')?.addEventListener('click', handleVerifyOtp);
  document.getElementById('editEmailBtn')?.addEventListener('click', handleEditEmail);
  document.getElementById('resendOtpLink')?.addEventListener('click', handleSendOtp);

  const fillDevBtn = document.getElementById('fillDevOtpBtn');
  if (fillDevBtn) {
    fillDevBtn.addEventListener('click', () => {
      if (signupState.devOtp) {
        const otpInput = document.getElementById('signupOtpCode');
        if (otpInput) otpInput.value = signupState.devOtp;
        signupState.otpCode = signupState.devOtp;
        handleVerifyOtp();
      }
    });
  }

  const otpInput = document.getElementById('signupOtpCode');
  if (otpInput) {
    otpInput.addEventListener('input', (e) => {
      e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
      signupState.otpCode = e.target.value;
    });
    otpInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') handleVerifyOtp();
    });
  }

  signupSteps?.querySelectorAll('[data-signup-field]').forEach((field) => {
    field.addEventListener('input', (event) => {
      if (event.target.dataset.signupField === 'contactNumber') {
        event.target.value = event.target.value.replace(/\D/g, '').slice(0, 11);
      }

      syncSignupState();

      if (event.target.dataset.signupField === 'email') {
        if (signupState.isEmailVerified) {
          signupState.isEmailVerified = false;
        }
      }

      if (event.target.dataset.signupField === 'password' || event.target.dataset.signupField === 'confirmPassword') {
        const checklist = document.querySelector('.password-checklist');
        if (checklist) checklist.outerHTML = renderPasswordChecklist();
      }
    });

    field.addEventListener('change', (event) => {
      syncSignupState();

      if (event.target.dataset.signupField === 'college') {
        signupState.course = '';
        renderStep();
      }

      if (event.target.dataset.signupField === 'provinceCode') {
        signupState.municipalityCode = '';
        signupState.barangayCode = '';
        syncAddressNames();
        renderStep();
      }

      if (event.target.dataset.signupField === 'municipalityCode') {
        signupState.barangayCode = '';
        syncAddressNames();
        renderStep();
      }

      if (event.target.dataset.signupField === 'barangayCode') {
        syncAddressNames();
      }
    });

    field.addEventListener('keypress', (event) => {
      if (event.key === 'Enter') goToNextSignupStep();
    });
  });
}

let isLoggingIn = false;

function setLoginButtonLoading(loading, message = 'Signing In...') {
  if (!loginBtn) return;

  if (loading) {
    loginBtn.disabled = true;
    loginBtn.classList.add('btn-loading');
    loginBtn.setAttribute('aria-busy', 'true');
    loginBtn.innerHTML = `
      <span class="btn-spinner" aria-hidden="true"></span>
      <span>${message}</span>
    `;
  } else {
    loginBtn.disabled = false;
    loginBtn.classList.remove('btn-loading');
    loginBtn.removeAttribute('aria-busy');
    loginBtn.innerHTML = `
      <span>Sign In to Account</span>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="5" y1="12" x2="19" y2="12"/>
        <polyline points="12 5 19 12 12 19"/>
      </svg>
    `;
  }
}

async function handleLogin() {
  if (isLoggingIn) return;

  const identifier = document.getElementById('loginIdentifier').value.trim();
  const password = document.getElementById('loginPassword').value;

  if (!identifier || !password) {
    showAlert(loginAlert, 'Please enter both identifier and password.', 'error');
    return;
  }

  isLoggingIn = true;
  setLoginButtonLoading(true, 'Signing In...');

  try {
    const response = await postForm('auth/login', { identifier, password });
    const result = await response.json();

    if (!response.ok || !result.success) {
      setLoginButtonLoading(false);
      isLoggingIn = false;
      showAlert(loginAlert, result.message || 'Invalid credentials. Please try again.', 'error');
      return;
    }

    setLoginButtonLoading(true, 'Redirecting...');
    showAlert(loginAlert, 'Welcome back! Redirecting to dashboard...', 'success');

    setTimeout(() => {
      window.location.replace(result.redirect || 'staff/Dashboard.html');
    }, 1000);
  } catch (error) {
    setLoginButtonLoading(false);
    isLoggingIn = false;
    showAlert(loginAlert, 'Unable to log in right now. Please try again.', 'error');
  }
}

function setupEvents() {
  if (loginBtn) {
    loginBtn.addEventListener('click', handleLogin);
  }

  tabButtons.forEach((button) => {
    button.addEventListener('click', () => switchTab(button.dataset.target));
  });

  if (openSignupLink) {
    openSignupLink.addEventListener('click', (event) => {
      event.preventDefault();
      loadPsgcAddressData();
      switchTab('signupForm');
    });
  }

  if (backToLoginLink) {
    backToLoginLink.addEventListener('click', (event) => {
      event.preventDefault();
      switchTab('loginForm');
    });
  }

  document.getElementById('loginIdentifier')?.addEventListener('keypress', (event) => {
    if (event.key === 'Enter') handleLogin();
  });

  document.getElementById('loginPassword')?.addEventListener('keypress', (event) => {
    if (event.key === 'Enter') handleLogin();
  });
}

renderStep();
loadPsgcAddressData();
checkServerOtpStatus();
setupEvents();
