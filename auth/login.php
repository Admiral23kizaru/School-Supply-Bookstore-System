  <div class="text-center mb-4">
    <div class="d-flex align-items-center justify-content-center gap-3 mb-4">
      <div class="text-dark-custom">
        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
          <polyline points="2 12 12 17 22 12"></polyline>
          <polyline points="2 17 12 22 22 17"></polyline>
        </svg>
      </div>
      <div class="text-start">
        <h2 class="mb-0 text-dark-custom" style="font-size: 22px; font-weight: 900; line-height: 1.1; letter-spacing:-0.5px;">School Supply<br/>Bookstore</h2>
        <p class="text-secondary mb-0 fw-bold text-uppercase mt-1" style="font-size: 10px; letter-spacing: 0.5px;">Inventory & sales management</p>
      </div>
    </div>
    <h1 class="h4 fw-bold text-dark mt-4 mb-1">Login</h1>
    <p class="text-secondary small mb-0">Sign in to your account.</p>
  </div>
  
  <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
  <div class="alert alert-success d-flex align-items-center py-3 rounded-xl mb-4" role="alert" style="background-color: #f0fdf4; border-color: #bbf7d0; color: #166534;">
    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
    <div>
      <strong class="d-block" style="line-height:1; font-size:14px;">Registration successful!</strong>
      <span style="font-size:13px;">You can now sign in using your new credentials.</span>
    </div>
  </div>
  <?php endif; ?>

  <?php if (isset($_GET['success']) && $_GET['success'] == 'registered_verify'): ?>
  <div class="alert alert-info d-flex align-items-center py-3 rounded-xl mb-4" role="alert" style="background-color: #e0f2fe; border-color: #bae6fd; color: #0369a1;">
    <i class="bi bi-envelope-check-fill me-2 fs-5"></i>
    <div>
      <strong class="d-block" style="line-height:1; font-size:14px;">Verify your email</strong>
      <span style="font-size:13px;">We've sent a verification link to your email. Please click it to verify.</span>
    </div>
  </div>
  <?php endif; ?>

  <?php if (isset($_GET['success']) && $_GET['success'] == 'verified'): ?>
  <div class="alert alert-success d-flex align-items-center py-3 rounded-xl mb-4" role="alert" style="background-color: #f0fdf4; border-color: #bbf7d0; color: #166534;">
    <i class="bi bi-shield-check me-2 fs-5"></i>
    <div>
      <strong class="d-block" style="line-height:1; font-size:14px;">Email Verified!</strong>
      <span style="font-size:13px;">Your email has been successfully verified. You can now login.</span>
    </div>
  </div>
  <?php endif; ?>

  <?php if (isset($_GET['success']) && $_GET['success'] == 'verified_pending'): ?>
  <div class="alert alert-info d-flex align-items-center py-3 rounded-xl mb-4" role="alert" style="background-color: #e0f2fe; border-color: #bae6fd; color: #0369a1;">
    <i class="bi bi-hourglass-split me-2 fs-5"></i>
    <div>
      <strong class="d-block" style="line-height:1; font-size:14px;">Email verified</strong>
      <span style="font-size:13px;">Your account is now waiting for admin approval.</span>
    </div>
  </div>
  <?php endif; ?>

  <?php if (isset($_GET['error']) && $_GET['error'] == 'invalid_token'): ?>
  <div class="alert alert-danger d-flex align-items-center py-3 rounded-xl mb-4" role="alert" style="background-color: #fef2f2; border-color: #fecaca; color: #991b1b;">
    <i class="bi bi-x-circle-fill me-2 fs-5"></i>
    <div>
      <strong class="d-block" style="line-height:1; font-size:14px;">Invalid Link</strong>
      <span style="font-size:13px;">The verification link is invalid or has already been used.</span>
    </div>
  </div>
  <?php endif; ?>

  <?php if (isset($_GET['reset']) && $_GET['reset'] == '1'): ?>
  <div class="alert alert-success d-flex align-items-center py-3 rounded-xl mb-4" role="alert" style="background-color: #f0fdf4; border-color: #bbf7d0; color: #166534;">
    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
    <div>
      <strong class="d-block" style="line-height:1; font-size:14px;">Password updated</strong>
      <span style="font-size:13px;">You can now log in with your new password.</span>
    </div>
  </div>
  <?php endif; ?>

  <?php if (isset($_GET['error']) && $_GET['error'] == 'invalid'): ?>
  <div class="alert alert-danger d-flex align-items-center py-3 rounded-xl mb-4" role="alert" style="background-color: #fef2f2; border-color: #fecaca; color: #991b1b;">
    <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
    <div>
      <strong class="d-block" style="line-height:1; font-size:14px;">Invalid credentials</strong>
      <span style="font-size:13px;">The email or password you entered is incorrect.</span>
    </div>
  </div>
  <?php endif; ?>

  <?php if (isset($_GET['error']) && $_GET['error'] == 'banned'): ?>
  <div class="alert alert-danger d-flex align-items-center py-3 rounded-xl mb-4" role="alert" style="background-color: #fef2f2; border-color: #fecaca; color: #991b1b;">
    <i class="bi bi-slash-circle-fill me-2 fs-5"></i>
    <div>
      <strong class="d-block" style="line-height:1; font-size:14px;">Account is banned</strong>
      <span style="font-size:13px;">Your account has been banned by the admin.</span>
    </div>
  </div>
  <?php endif; ?>

  <?php if (isset($_GET['error']) && $_GET['error'] == 'suspended'): ?>
  <div class="alert alert-warning d-flex align-items-center py-3 rounded-xl mb-4" role="alert" style="background-color: #fffbeb; border-color: #fcd34d; color: #92400e;">
    <i class="bi bi-pause-circle-fill me-2 fs-5"></i>
    <div>
      <strong class="d-block" style="line-height:1; font-size:14px;">Account is suspended</strong>
      <span style="font-size:13px;">Your account is temporarily suspended.</span>
    </div>
  </div>
  <?php endif; ?>

  <?php if (isset($_GET['error']) && $_GET['error'] == 'unverified'): ?>
  <div class="alert alert-warning d-flex align-items-center py-3 rounded-xl mb-4" role="alert" style="background-color: #fffbeb; border-color: #fcd34d; color: #92400e;">
    <i class="bi bi-envelope-exclamation-fill me-2 fs-5"></i>
    <div>
      <strong class="d-block" style="line-height:1; font-size:14px;">Email not verified</strong>
      <span style="font-size:13px;">Please verify your email first before signing in.</span>
    </div>
  </div>
  <?php endif; ?>

  <?php if (isset($_GET['error']) && $_GET['error'] == 'pending_approval'): ?>
  <div class="alert alert-info d-flex align-items-center py-3 rounded-xl mb-4" role="alert" style="background-color: #e0f2fe; border-color: #bae6fd; color: #0369a1;">
    <i class="bi bi-hourglass-split me-2 fs-5"></i>
    <div>
      <strong class="d-block" style="line-height:1; font-size:14px;">Pending admin approval</strong>
      <span style="font-size:13px;">Your account is verified but still awaiting admin approval.</span>
    </div>
  </div>
  <?php endif; ?>

  <?php if (isset($_GET['error']) && $_GET['error'] == 'approval_rejected'): ?>
  <div class="alert alert-danger d-flex align-items-center py-3 rounded-xl mb-4" role="alert" style="background-color: #fef2f2; border-color: #fecaca; color: #991b1b;">
    <i class="bi bi-x-octagon-fill me-2 fs-5"></i>
    <div>
      <strong class="d-block" style="line-height:1; font-size:14px;">Approval rejected</strong>
      <span style="font-size:13px;">Your account was rejected by the admin. Please contact support.</span>
    </div>
  </div>
  <?php endif; ?>
  
  <form method="POST" action="index.php?action=login">
      <div class="mb-3">
        <label for="email" class="form-label small fw-medium text-secondary mb-1">Email</label>
        <div class="input-group">
          <span class="input-group-text bg-white text-secondary border-end-0 py-2"><i class="bi bi-envelope"></i></span>
          <input type="email" class="form-control border-start-0 ps-0 bg-white shadow-none py-2" id="email" name="email" required autocomplete="email">
        </div>
      </div>

      <div class="mb-3">
        <label for="password" class="form-label small fw-medium text-secondary mb-1">Password</label>
        <div class="input-group">
          <span class="input-group-text bg-white text-secondary border-end-0 py-2"><i class="bi bi-lock"></i></span>
          <input type="password" class="form-control border-start-0 border-end-0 px-0 bg-white shadow-none py-2" id="password" name="password" required autocomplete="current-password">
          <button class="btn btn-outline-secondary border bg-white text-secondary border-start-0 py-2" type="button" id="togglePassword">
            <i class="bi bi-eye"></i>
          </button>
        </div>
        <div class="text-end mt-2">
          <a href="?action=forgot" class="small text-dark-custom fw-semibold text-decoration-none">Forgot password?</a>
        </div>
      </div>

      <div class="d-flex justify-content-between align-items-center mb-4 mt-4">
        <div class="form-check">
          <input type="checkbox" class="form-check-input shadow-none" id="remember" name="remember" style="cursor:pointer;">
          <label class="form-check-label small text-secondary" for="remember" style="cursor:pointer;">Remember me</label>
        </div>
        <button type="submit" class="btn bg-dark-custom rounded-xl px-4 py-2 text-white fw-medium shadow-sm">Sign in</button>
      </div>

      <p class="text-center small text-secondary mb-0">
        Don’t have an account? <a href="?action=register" class="text-dark-custom fw-semibold text-decoration-none">Register</a>
      </p>
  </form>

  <script>
    document.getElementById('togglePassword').addEventListener('click', function (e) {
      const password = document.getElementById('password');
      const icon = this.querySelector('i');
      if (password.type === 'password') {
        password.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
      } else {
        password.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
      }
    });
  </script>
