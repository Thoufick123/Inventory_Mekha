<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Select Concern / Shop') ?> - GRM Inventory</title>
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
            padding: 2rem 1rem;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .select-card {
            background: #ffffff;
            border-radius: 1.25rem;
            width: 100%;
            max-width: 680px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45);
            overflow: hidden;
        }
        .select-header {
            background: #020617;
            padding: 2rem;
            text-align: center;
            color: #ffffff;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .concern-box {
            border: 2px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1.25rem;
            background: #ffffff;
            transition: all 0.2s ease-in-out;
            cursor: pointer;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .concern-box:hover {
            border-color: #2563eb;
            transform: translateY(-3px);
            box-shadow: 0 12px 20px -5px rgba(37, 99, 235, 0.15);
        }
        .concern-box.shop-box:hover {
            border-color: #16a34a;
            box-shadow: 0 12px 20px -5px rgba(22, 163, 74, 0.15);
        }
    </style>
</head>
<body>

<div class="select-card">
    <div class="select-header">
        <div class="d-inline-flex p-3 rounded-circle bg-primary bg-opacity-10 text-primary mb-2">
            <i class="bi bi-box-seam-fill fs-2"></i>
        </div>
        <h4 class="fw-bold mb-1 tracking-tight text-white">GRM Inventory</h4>
        <p class="text-secondary small mb-3">Multi-Concern Enterprise Inventory System</p>

        <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill bg-white bg-opacity-10 text-white-50 small">
            <i class="bi bi-person-circle text-info"></i>
            <span>Logged in as: <strong class="text-white"><?= htmlspecialchars($user['name'] ?? 'User') ?></strong> (<?= htmlspecialchars($user['email'] ?? '') ?>)</span>
        </div>
    </div>

    <div class="p-4 p-md-5">
        <!-- Flash notifications -->
        <?php if (!empty($flashSuccess)): ?>
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($flashSuccess) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($flashError)): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($flashError) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="text-center mb-4">
            <h5 class="fw-bold text-dark mb-1">Choose Location / Shop to Open</h5>
            <p class="text-secondary small mb-0">
                You have active access to <?= count($concerns) ?> business locations. Select where you want to work today:
            </p>
        </div>

        <div class="row g-3">
            <?php foreach ($concerns as $c): 
                $isShop = \App\Core\Auth::isShopConcern((int)$c['id']);
                $isActiveCid = ($currentCid == $c['id']);
            ?>
                <div class="col-md-6 col-12">
                    <form action="<?= \App\Core\Controller::url('/select-concern') ?>" method="POST" class="h-100">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                        <input type="hidden" name="concern_id" value="<?= (int)$c['id'] ?>">

                        <div class="concern-box <?= $isShop ? 'shop-box' : '' ?>" onclick="this.closest('form').submit();">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge font-monospace <?= $isShop ? 'bg-success' : 'bg-primary' ?>">
                                    <?= htmlspecialchars($c['code']) ?>
                                </span>
                                <?php if ($isShop): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                        <i class="bi bi-cart3 me-1"></i> Retail Shop
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
                                        <i class="bi bi-building-gear me-1"></i> Production / Unit
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex align-items-center gap-3 my-2">
                                <div class="rounded-3 p-2.5 <?= $isShop ? 'bg-success bg-opacity-10 text-success' : 'bg-primary bg-opacity-10 text-primary' ?>">
                                    <i class="bi <?= $isShop ? 'bi-shop fs-3' : 'bi-buildings fs-3' ?>"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-0 fs-6"><?= htmlspecialchars($c['name']) ?></h6>
                                    <small class="text-muted d-block" style="font-size: 0.78rem;">
                                        <i class="bi bi-speedometer2 text-primary me-1"></i>Opens Concern Dashboard
                                    </small>
                                </div>
                            </div>

                            <div class="mt-auto pt-3 border-top">
                                <button type="submit" class="btn btn-sm <?= $isShop ? 'btn-success' : 'btn-primary' ?> w-100 fw-semibold d-flex align-items-center justify-content-center gap-2 py-2">
                                    <span>Enter <?= htmlspecialchars($c['name']) ?></span>
                                    <i class="bi bi-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-4 pt-3 border-top">
            <a href="<?= \App\Core\Controller::url('/logout') ?>" class="text-secondary small text-decoration-none hover-dark">
                <i class="bi bi-box-arrow-left me-1"></i> Sign in as a different user
            </a>
        </div>
    </div>
</div>

<script src="<?= \App\Core\Controller::url('/assets/js/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
