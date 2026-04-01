<?php $roles = ['Admin', 'Seller', 'Customer']; ?>
<div class="text-center mb-4">
    <div class="d-flex align-items-center justify-content-center gap-3 mb-4 mt-3">
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
    <h1 class="h4 fw-bold text-dark mt-4 mb-1">Register</h1>
    <p class="text-secondary small mb-0">Create your account to continue.</p>
</div>

<?php if (isset($_GET['error']) && $_GET['error'] == 'duplicate'): ?>
<div class="alert alert-danger d-flex align-items-center py-3 rounded-xl mb-4" role="alert" style="background-color: #fef2f2; border-color: #fecaca; color: #991b1b;">
  <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
  <div>
    <strong class="d-block" style="line-height:1; font-size:14px;">Registration failed</strong>
    <span style="font-size:13px;">That email address is already registered.</span>
  </div>
</div>
<?php endif; ?>

<form method="POST" action="index.php?action=register">
  <div class="mb-3">
    <label for="username" class="form-label small fw-medium text-secondary mb-1">Username</label>
    <input type="text" class="form-control rounded-xl py-2 shadow-none" id="username" name="username" required autocomplete="username">
  </div>

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
      <input type="password" class="form-control border-start-0 border-end-0 px-0 bg-white shadow-none py-2" id="password" name="password" required autocomplete="new-password">
      <button class="btn btn-outline-secondary border bg-white text-secondary border-start-0 py-2" type="button" id="togglePassword">
        <i class="bi bi-eye"></i>
      </button>
    </div>
    <div class="form-text mt-1 text-secondary" style="font-size: 11px;">Use at least 8 characters for better security.</div>
  </div>

  <div class="mb-4">
    <label for="role" class="form-label small fw-medium text-secondary mb-1">Role</label>
    <select class="form-select rounded-xl py-2 shadow-none" id="role" name="role" required>
      <option value="" disabled selected>Select a role</option>
      <?php foreach ($roles as $role): ?>
        <option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="form-text mt-1 text-secondary" style="font-size: 11px;">Tip: choose <span class="fw-bold">Customer</span> for normal shoppers.</div>
  </div>

  <button type="submit" class="btn bg-dark-custom rounded-xl w-100 py-2 text-white fw-medium mb-3 shadow-sm" style="padding-top:10px; padding-bottom:10px;">Create account</button>

  <p class="text-center small text-secondary mb-0">
    Already have an account? <a href="?action=login" class="text-dark-custom fw-semibold text-decoration-none">Sign in</a>
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
