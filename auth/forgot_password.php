<?php $step = $_GET['step'] ?? 'request'; ?>
<div class="text-center mb-4">
  <h1 class="h4 fw-bold text-dark mt-2 mb-1">Forgot Password</h1>
  <p class="text-secondary small mb-0">Recover your account in 3 quick steps.</p>
</div>

<?php if (isset($_GET['error']) && $_GET['error'] === 'email_not_found'): ?>
<div class="alert alert-danger py-2 rounded-xl mb-3">Email is not registered.</div>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'mail_failed'): ?>
<div class="alert alert-danger py-2 rounded-xl mb-3">Failed to send OTP email. Check SMTP settings.</div>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'invalid_otp'): ?>
<div class="alert alert-danger py-2 rounded-xl mb-3">Invalid OTP. Please try again.</div>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'otp_expired'): ?>
<div class="alert alert-warning py-2 rounded-xl mb-3">OTP expired. Request a new one.</div>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'session_expired'): ?>
<div class="alert alert-warning py-2 rounded-xl mb-3">Session expired. Start again.</div>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'weak_password'): ?>
<div class="alert alert-warning py-2 rounded-xl mb-3">Password must be at least 8 characters.</div>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'password_mismatch'): ?>
<div class="alert alert-danger py-2 rounded-xl mb-3">Passwords do not match.</div>
<?php endif; ?>
<?php if (isset($_GET['sent']) && $_GET['sent'] === '1'): ?>
<div class="alert alert-success py-2 rounded-xl mb-3">OTP sent to your email.</div>
<?php endif; ?>

<?php if ($step === 'verify'): ?>
<form method="POST" action="index.php?action=forgot&step=verify">
  <input type="hidden" name="forgot_action" value="verify_otp">
  <input type="hidden" name="email" value="<?= htmlspecialchars($_SESSION['pw_reset']['email'] ?? '') ?>">
  <div class="mb-3">
    <label class="form-label small fw-medium text-secondary mb-1">Enter OTP</label>
    <input type="text" name="otp" maxlength="6" class="form-control rounded-xl py-2 shadow-none" required>
  </div>
  <button type="submit" class="btn bg-dark-custom rounded-xl w-100 py-2 text-white fw-medium shadow-sm">Verify OTP</button>
</form>
<?php elseif ($step === 'reset'): ?>
<form method="POST" action="index.php?action=forgot&step=reset">
  <input type="hidden" name="forgot_action" value="reset_password">
  <input type="hidden" name="email" value="<?= htmlspecialchars($_SESSION['pw_reset']['email'] ?? '') ?>">
  <div class="mb-3">
    <label class="form-label small fw-medium text-secondary mb-1">New Password</label>
    <input type="password" name="new_password" class="form-control rounded-xl py-2 shadow-none" required>
  </div>
  <div class="mb-3">
    <label class="form-label small fw-medium text-secondary mb-1">Confirm Password</label>
    <input type="password" name="confirm_password" class="form-control rounded-xl py-2 shadow-none" required>
  </div>
  <button type="submit" class="btn bg-dark-custom rounded-xl w-100 py-2 text-white fw-medium shadow-sm">Reset Password</button>
</form>
<?php else: ?>
<form method="POST" action="index.php?action=forgot">
  <input type="hidden" name="forgot_action" value="send_otp">
  <div class="mb-3">
    <label class="form-label small fw-medium text-secondary mb-1">Registered Email</label>
    <input type="email" name="email" class="form-control rounded-xl py-2 shadow-none" required autocomplete="email">
  </div>
  <button type="submit" class="btn bg-dark-custom rounded-xl w-100 py-2 text-white fw-medium shadow-sm">Send OTP</button>
</form>
<?php endif; ?>

<p class="text-center small text-secondary mt-3 mb-0">
  Back to <a href="?action=login" class="text-dark-custom fw-semibold text-decoration-none">Login</a>
</p>
