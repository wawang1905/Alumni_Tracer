<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Alumni Tracker - Portal Login & Registration</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= base_url('CSS/Main.css') ?>?v=<?= time() ?>">
</head>
<body>
  <!-- Ambient background lighting effects -->
  <div class="bg-ambient-orb bg-orb-1"></div>
  <div class="bg-ambient-orb bg-orb-2"></div>

  <div class="auth-page-wrapper">
    <div class="container">
      
      <!-- Minimalist Card Header -->
      <div class="auth-card-header">
        <div class="brand-logo-wrap">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
            <path d="M6 12v5c3 3 9 3 12 0v-5"/>
          </svg>
        </div>
        <div class="brand-title">Alumni Tracer</div>
        <h2 id="authHeading" class="auth-heading">Welcome Back</h2>
        <p id="authSubheading" class="auth-subheading">Please enter your credentials to access your account</p>
      </div>

      <!-- Modern Tabs -->
      <div class="tabs">
        <button type="button" class="tab-btn active" data-target="loginForm">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
            <polyline points="10 17 15 12 10 7"/>
            <line x1="15" y1="12" x2="3" y2="12"/>
          </svg>
          Sign In
        </button>
        <button type="button" class="tab-btn" data-target="signupForm">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="8.5" cy="7" r="4"/>
            <line x1="20" y1="8" x2="20" y2="14"/>
            <line x1="23" y1="11" x2="17" y2="11"/>
          </svg>
          Register
        </button>
      </div>

      <div class="forms-container">

        <!-- Login Form -->
        <div class="form active" id="loginForm">
          <div id="loginAlert" class="alert"></div>

          <div class="form-group">
            <label for="loginIdentifier">Email or Account ID</label>
            <div class="input-with-icon">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
              </svg>
              <input type="text" id="loginIdentifier" placeholder="e.g. alumni@example.com or ALUMNI001" autocomplete="username">
            </div>
          </div>

          <div class="form-group">
            <label for="loginPassword">Password</label>
            <div class="input-with-icon">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
              <input type="password" id="loginPassword" placeholder="Enter your password" autocomplete="current-password">
              <button type="button" class="password-toggle-btn" id="toggleLoginPasswordBtn" aria-label="Toggle password visibility">
                <svg id="pwdEyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
            </div>
          </div>

          <button class="btn-login" id="loginBtn" type="button">
            <span>Sign In to Account</span>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="5" y1="12" x2="19" y2="12"/>
              <polyline points="12 5 19 12 12 19"/>
            </svg>
          </button>

          <div class="footer-links">
            <a href="#" id="openSignupLink">Need an alumni account? <strong>Register now</strong></a>
          </div>
        </div>

        <!-- Sign Up Form (Multi-step Wizard) -->
        <div class="form" id="signupForm">
          <div id="signupAlert" class="alert"></div>
          <div id="signupProgress" class="signup-progress"></div>
          <div id="signupSteps"></div>

          <div class="footer-links">
            <a href="#" id="backToLoginLink">Already have an account? <strong>Sign in</strong></a>
          </div>
        </div>


      </div>
    </div>
  </div>

  <script>
    window.appBaseUrl = <?= json_encode(base_url('/'), JSON_UNESCAPED_SLASHES) ?>;
    window.signupOptions = <?= json_encode($signupOptions ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;


    // Password visibility toggle
    document.addEventListener('DOMContentLoaded', () => {
      const toggleBtn = document.getElementById('toggleLoginPasswordBtn');
      const pwdInput = document.getElementById('loginPassword');
      if (toggleBtn && pwdInput) {
        toggleBtn.addEventListener('click', () => {
          const isPassword = pwdInput.type === 'password';
          pwdInput.type = isPassword ? 'text' : 'password';
          toggleBtn.innerHTML = isPassword 
            ? `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`
            : `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
        });
      }

      // Update heading dynamically on tab switch
      const tabBtns = document.querySelectorAll('.tab-btn');
      const heading = document.getElementById('authHeading');
      const subheading = document.getElementById('authSubheading');
      
      tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
          if (btn.dataset.target === 'signupForm') {
            if (heading) heading.textContent = 'Create Account';
            if (subheading) subheading.textContent = 'Join the official alumni community in 4 quick steps';
          } else {
            if (heading) heading.textContent = 'Welcome Back';
            if (subheading) subheading.textContent = 'Please enter your credentials to access your account';
          }
        });
      });

      const openSignup = document.getElementById('openSignupLink');
      const backLogin = document.getElementById('backToLoginLink');
      if (openSignup) {
        openSignup.addEventListener('click', () => {
          if (heading) heading.textContent = 'Create Account';
          if (subheading) subheading.textContent = 'Join the official alumni community in 4 quick steps';
        });
      }
      if (backLogin) {
        backLogin.addEventListener('click', () => {
          if (heading) heading.textContent = 'Welcome Back';
          if (subheading) subheading.textContent = 'Please enter your credentials to access your account';
        });
      }
    });
  </script>
  <script src="<?= base_url('JS/Main.js') ?>?v=<?= time() ?>"></script>
</body>
</html>
