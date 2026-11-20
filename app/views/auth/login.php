<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - GRM Inventory</title>
    <link rel="stylesheet" href="<?= \App\Core\Controller::url('/assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= \App\Core\Controller::url('/assets/css/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= \App\Core\Controller::url('/assets/css/style.css') ?>">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .login-card {
            background: #ffffff;
            border-radius: 1rem;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            overflow: hidden;
        }
        .login-header {
            background: #020617;
            padding: 2.25rem 2rem 1.75rem;
            text-align: center;
            color: #ffffff;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="d-inline-flex p-3 rounded-circle bg-primary bg-opacity-10 text-primary mb-2">
            <i class="bi bi-box-seam-fill fs-2"></i>
        </div>
        <h4 class="fw-bold mb-1 tracking-tight">GRM Inventory</h4>
        <p class="text-secondary small mb-0">Multi-Concern Enterprise Inventory System</p>
    </div>

    <div class="p-4 p-md-4">
        <?php if (!empty($flashError)): ?>
            <div class="alert alert-danger py-2 small d-flex align-items-center mb-3">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <div><?= htmlspecialchars($flashError) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($flashSuccess)): ?>
            <div class="alert alert-success py-2 small d-flex align-items-center mb-3">
                <i class="bi bi-check-circle-fill me-2"></i>
                <div><?= htmlspecialchars($flashSuccess) ?></div>
            </div>
        <?php endif; ?>

        <form action="<?= \App\Core\Controller::url('/login') ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="mb-3">
                <label for="email" class="form-label small fw-semibold text-secondary">Username or Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control border-start-0" id="email" name="email" required autocomplete="username" placeholder="Username or email (e.g. superadmin)" value="">
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label small fw-semibold text-secondary">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control border-start-0 border-end-0" id="password" name="password" required autocomplete="current-password" placeholder="Enter password">
                    <button type="button" class="btn btn-outline-secondary" id="togglePasswordVisibility" aria-label="Show password" aria-controls="password" aria-pressed="false">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
                Sign In to Workspace <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('togglePasswordVisibility').addEventListener('click', function () {
    const password = document.getElementById('password');
    const icon = this.querySelector('i');
    const isVisible = password.type === 'password';

    password.type = isVisible ? 'text' : 'password';
    this.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
    this.setAttribute('aria-pressed', String(isVisible));
    icon.classList.toggle('bi-eye', !isVisible);
    icon.classList.toggle('bi-eye-slash', isVisible);
    password.focus();
});
</script>

</body>
</html>
