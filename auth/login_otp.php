<?php
$error = $_GET['error'] ?? '';
$email = $_SESSION['login_otp']['email'] ?? 'your email';

if (!isset($_SESSION['login_otp'])) {
    // If no OTP session, redirect back to login
    header('Location: index.php?action=login');
    exit;
}
?>
<div class="text-center mb-4">
    <div style="width:60px; height:60px; border-radius:50%; background:#1a1a1a; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
        <i class="bi bi-shield-lock text-white fs-3"></i>
    </div>
    <h3 class="fw-bold fs-4 mb-2 text-dark mt-3">Two-Step Verification</h3>
    <p class="text-muted small">We've sent a 6-digit code to <strong class="text-dark"><?= htmlspecialchars($email) ?></strong>.<br>Please enter it to secure your login.</p>
</div>

<?php if ($error === 'invalid'): ?>
    <div class="alert alert-danger py-2 px-3 small border-0 mb-4" style="border-radius: 8px; font-weight: 500;">
        <i class="bi bi-exclamation-triangle-fill me-1"></i> Incorrect verification code. Please try again.
    </div>
<?php endif; ?>

<form method="POST" action="index.php">
    <input type="hidden" name="login_otp_action" value="verify">
    
    <div class="mb-4 text-start">
        <label class="form-label small fw-semibold text-muted mb-2 text-uppercase" style="letter-spacing: 0.5px;">Verification Code</label>
        <div class="input-group">
            <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-123"></i></span>
            <input type="text" name="otp" class="form-control border-start-0 ps-0 text-center fw-bold fs-4 tracking-widest" style="letter-spacing: 0.25em;" placeholder="------" maxlength="6" pattern="\d{6}" autocomplete="one-time-code" required autofocus>
        </div>
    </div>
    
    <button type="submit" class="btn btn-dark w-100 py-2 mb-3 fw-medium">
        Verify & Login <i class="bi bi-arrow-right ms-2"></i>
    </button>

    <div class="text-center">
        <a href="index.php?action=login" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left me-1"></i> Back to sign in</a>
    </div>
</form>
