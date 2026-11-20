<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> - GRM Inventory</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="<?= \App\Core\Controller::url('/assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= \App\Core\Controller::url('/assets/css/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= \App\Core\Controller::url('/assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= \App\Core\Controller::url('/assets/css/reports.css') ?>">

    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --sidebar-bg: #0f172a;
            --sidebar-hover: #1e293b;
            --sidebar-active: #2563eb;
            --sidebar-width: 260px;
        }
        body {
            background-color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        #sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 1000;
            overflow-y: auto;
            transition: all 0.3s;
        }
        #sidebar .brand-header {
            padding: 1.25rem 1.5rem;
            background: #020617;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        #sidebar .nav-group-title {
            padding: 0.75rem 1.5rem 0.25rem;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
            font-weight: 700;
        }
        #sidebar .nav-link {
            color: #94a3b8;
            padding: 0.55rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.88rem;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
        }
        #sidebar .nav-link:hover {
            color: #ffffff;
            background: var(--sidebar-hover);
        }
        #sidebar .nav-link.active {
            color: #ffffff;
            background: var(--sidebar-active);
            font-weight: 600;
        }
        #sidebar .nav-link i {
            font-size: 1.1rem;
        }
        #main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .top-navbar {
            height: 60px;
            min-height: 60px;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.25rem;
            position: sticky;
            top: 0;
            z-index: 999;
            gap: 1rem;
        }
        .navbar-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 0;
            flex: 0 1 auto;
            overflow: hidden;
        }
        .navbar-page-title {
            font-size: 1.12rem;
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 250px;
            margin: 0;
            line-height: 1.2;
            letter-spacing: -0.01em;
            flex-shrink: 1;
        }
        @media (min-width: 1366px) {
            .navbar-page-title {
                max-width: 380px;
            }
        }
        @media (min-width: 1600px) {
            .navbar-page-title {
                max-width: 520px;
            }
        }
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            flex-shrink: 0;
            margin-left: auto;
        }
        .content-body {
            padding: 1.75rem;
            flex: 1;
        }
        .concern-pill, .super-pill {
            padding: 0.28rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.76rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            white-space: nowrap;
            flex-shrink: 0;
            line-height: 1.2;
        }
        .concern-pill {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }
        .super-pill {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        .concern-switcher-btn {
            font-size: 0.82rem;
            padding: 0.28rem 0.75rem;
            border-radius: 8px;
            white-space: nowrap;
            max-width: 180px;
            height: 32px;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            border: 1px solid rgba(0,0,0,0.15);
        }
        .concern-switcher-btn:hover {
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
        .navbar-export-group {
            flex-shrink: 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .card-stat {
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            background: #ffffff;
            padding: 1.25rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card-stat:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);
        }
        .stat-icon-box {
            width: 48px;
            height: 48px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }
        .table-custom th {
            background: #f8fafc;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <nav id="sidebar">
        <div class="brand-header d-flex align-items-center justify-content-between">
            <a href="<?= \App\Core\Controller::url('/') ?>" class="text-white text-decoration-none d-flex align-items-center gap-2">
                <i class="bi bi-box-seam-fill text-primary fs-4"></i>
                <div>
                    <div class="fw-bold tracking-tight lh-1 text-white">GRM Inventory</div>
                    <small class="text-secondary" style="font-size: 0.68rem; letter-spacing: 0.05em;">MULTI-CONCERN ERP</small>
                </div>
            </a>
        </div>

        <div class="py-2">
            <?php
            $activeRoute = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            $grouped = [];
            foreach ($sidebarModules as $m) {
                if ($m['code'] === 'variants' || $m['code'] === 'concern_dashboard' || $m['code'] === 'admin_concerns' || $m['code'] === 'dispatch' || $m['group_name'] === 'Masters') continue;
                $groupName = $m['code'] === 'catalog_mapping' ? 'Purchase' : $m['group_name'];
                $grouped[$groupName][] = $m;
            }

            foreach ($grouped as $groupName => $mods):
                if ($groupName === 'Staff' || $groupName === 'Masters') continue;
                // If Finance group, merge 'cash' and 'cash_flow' into a single 'Cash & Cash Flow' module
                if ($groupName === 'Finance') {
                    $hasCashOrFlow = false;
                    $mergedMods = [];
                    foreach ($mods as $m) {
                        if ($m['code'] === 'cash') {
                            $hasCashOrFlow = true;
                            continue; // skip separate cash entry
                        }
                        if ($m['code'] === 'cash_flow') {
                            $hasCashOrFlow = true;
                            $m['name'] = 'Cash & Cash Flow';
                            $m['icon'] = 'bi-cash-coin';
                            $mergedMods[] = $m;
                            continue;
                        }
                        $mergedMods[] = $m;
                    }
                    if ($hasCashOrFlow && empty(array_filter($mergedMods, fn($item) => $item['code'] === 'cash_flow'))) {
                        $mergedMods[] = [
                            'code' => 'cash_flow',
                            'name' => 'Cash & Cash Flow',
                            'icon' => 'bi-cash-coin',
                            'group_name' => 'Finance'
                        ];
                    }
                    $mods = $mergedMods;
                }
            ?>
                <div class="nav-group-title"><?= htmlspecialchars($groupName) ?></div>
                <?php foreach ($mods as $m): 
                    $href = \App\Core\Controller::url('/m/' . $m['code']);
                    if ($m['code'] === 'admin_dashboard') $href = \App\Core\Controller::url('/admin/dashboard');
                    if ($m['code'] === 'admin_concerns') $href = \App\Core\Controller::url('/admin/concerns');
                    if ($m['code'] === 'admin_assets') $href = \App\Core\Controller::url('/admin/assets');
                    if ($m['code'] === 'admin_reports') $href = \App\Core\Controller::url('/admin/reports');
                    if ($m['code'] === 'concern_dashboard') $href = \App\Core\Controller::url('/dashboard');
                    if ($m['code'] === 'catalog_program') $href = \App\Core\Controller::url('/production/catalog');
                    if ($m['code'] === 'catalog_mapping') $href = \App\Core\Controller::url('/production/catalog-mapping');
                    if ($m['code'] === 'cutting_wastage') $href = \App\Core\Controller::url('/production/cutting');
                    if ($m['code'] === 'stitching_wastage') $href = \App\Core\Controller::url('/production/stitching');
                    if ($m['code'] === 'batch') $href = \App\Core\Controller::url('/production/batches');

                    if ($m['code'] === 'cash_flow') {
                        $isActive = (isset($currentModule) && in_array($currentModule, ['cash_flow', 'cash'])) || 
                                    (str_contains($activeRoute, 'cash'));
                    } elseif ($m['code'] === 'stitching_wastage') {
                        $isActive = (isset($currentModule) && in_array($currentModule, ['stitching_wastage', 'stitching'])) || 
                                    (str_contains($activeRoute, 'stitching'));
                    } else {
                        $isActive = (isset($currentModule) && $currentModule === $m['code']) || 
                                    (str_contains($activeRoute, $m['code']));
                    }
                ?>
                    <a href="<?= $href ?>" class="nav-link <?= $isActive ? 'active' : '' ?>">
                        <i class="bi <?= htmlspecialchars($m['icon']) ?>"></i>
                        <span><?= htmlspecialchars($m['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <div class="nav-group-title">Staff</div>
            <a href="<?= \App\Core\Controller::url('/m/staff') ?>" class="nav-link <?= (isset($currentModule) && $currentModule === 'staff' && (empty($currentSubModule) || $currentSubModule === 'all')) ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i>
                <span>All Staff</span>
            </a>
            <a href="<?= \App\Core\Controller::url('/m/staff/create') ?>" class="nav-link <?= (isset($currentModule) && $currentModule === 'staff' && isset($currentSubModule) && $currentSubModule === 'create') ? 'active' : '' ?>">
                <i class="bi bi-person-plus-fill"></i>
                <span>Add New Staff</span>
            </a>
            <a href="<?= \App\Core\Controller::url('/m/staff/roles') ?>" class="nav-link <?= (isset($currentSubModule) && $currentSubModule === 'roles') ? 'active' : '' ?>">
                <i class="bi bi-person-badge-fill"></i>
                <span>Role Assign</span>
            </a>
            <?php if ($isSuperAdmin): ?>
            <a href="<?= \App\Core\Controller::url('/admin/concerns') ?>" class="nav-link <?= (str_contains($activeRoute, 'admin/concerns') || (isset($currentModule) && $currentModule === 'admin_concerns')) ? 'active' : '' ?>">
                <i class="bi bi-buildings"></i>
                <span>Concern Management</span>
            </a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- MAIN WRAPPER -->
    <div id="main-content">
        <!-- TOP NAVBAR -->
        <header class="top-navbar">
            <div class="navbar-left">
                <span class="navbar-page-title" title="<?= htmlspecialchars($pageTitle ?? 'Inventory') ?>"><?= htmlspecialchars($pageTitle ?? 'Inventory') ?></span>
                
                <?php if ($isSuperAdmin): ?>
                    <span class="super-pill">
                        <i class="bi bi-shield-check"></i> Super Admin
                    </span>

                    <!-- CONCERN SWITCHER FOR SUPER ADMIN -->
                    <div class="dropdown">
                        <button class="btn btn-sm <?= $currentConcern ? 'btn-primary' : 'btn-outline-dark' ?> dropdown-toggle d-flex align-items-center gap-1.5 fw-semibold shadow-sm concern-switcher-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="<?= $currentConcern ? htmlspecialchars($currentConcern['name']) . ' (' . htmlspecialchars($currentConcern['code']) . ')' : 'All Concerns (Consolidated View)' ?>">
                            <i class="bi bi-building"></i>
                            <span class="text-truncate" style="max-width: 155px;"><?= $currentConcern ? htmlspecialchars($currentConcern['name']) : 'All Concerns' ?></span>
                        </button>
                        <ul class="dropdown-menu shadow">
                            <li class="dropdown-header text-uppercase small fw-bold">Select Active Concern Context</li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center justify-content-between <?= !$currentConcern ? 'active fw-bold' : '' ?>" href="<?= \App\Core\Controller::url('/admin/switch-concern?id=0') ?>">
                                    <span><i class="bi bi-globe me-2 text-primary"></i> All Concerns (Consolidated)</span>
                                    <?php if (!$currentConcern): ?><i class="bi bi-check-lg"></i><?php endif; ?>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <?php foreach ($allConcerns as $con): 
                                $isSel = ($currentConcern && $currentConcern['id'] == $con['id']);
                            ?>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center justify-content-between <?= $isSel ? 'active fw-bold' : '' ?>" href="<?= \App\Core\Controller::url('/admin/switch-concern?id=' . $con['id']) ?>">
                                        <span><i class="bi bi-building me-2 text-secondary"></i> <?= htmlspecialchars($con['name']) ?> (<?= htmlspecialchars($con['code']) ?>)</span>
                                        <?php if ($isSel): ?><i class="bi bi-check-lg"></i><?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php else: ?>
                    <?php 
                        $userConcerns = \App\Core\Auth::check() ? \App\Core\Auth::getUserConcerns(\App\Core\Auth::id()) : [];
                    ?>
                    <?php if (count($userConcerns) > 1): ?>
                        <!-- CONCERN SWITCHER FOR MULTI-CONCERN STAFF / USERS -->
                        <div class="dropdown">
                            <button class="btn btn-sm <?= $currentConcern && \App\Core\Auth::isShopConcern($currentConcern['id']) ? 'btn-success' : 'btn-primary' ?> dropdown-toggle d-flex align-items-center gap-1.5 fw-semibold shadow-sm concern-switcher-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Switch Location / Shop">
                                <i class="bi <?= $currentConcern && \App\Core\Auth::isShopConcern($currentConcern['id']) ? 'bi-shop' : 'bi-building' ?>"></i>
                                <span class="text-truncate" style="max-width: 155px;"><?= $currentConcern ? htmlspecialchars($currentConcern['name']) : 'Select Concern' ?></span>
                            </button>
                            <ul class="dropdown-menu shadow">
                                <li class="dropdown-header text-uppercase small fw-bold">Switch Location / Shop</li>
                                <?php foreach ($userConcerns as $con): 
                                    $isSel = ($currentConcern && $currentConcern['id'] == $con['id']);
                                    $conIsShop = \App\Core\Auth::isShopConcern((int)$con['id']);
                                ?>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center justify-content-between <?= $isSel ? 'active fw-bold' : '' ?>" href="<?= \App\Core\Controller::url('/switch-concern?id=' . $con['id']) ?>">
                                            <span>
                                                <i class="bi <?= $conIsShop ? 'bi-shop' : 'bi-building' ?> me-2 <?= $isSel ? 'text-white' : ($conIsShop ? 'text-success' : 'text-primary') ?>"></i> 
                                                <?= htmlspecialchars($con['name']) ?> 
                                                <small class="<?= $isSel ? 'text-white-50' : 'text-muted' ?>">(<?= htmlspecialchars($con['code']) ?>)</small>
                                            </span>
                                            <?php if ($isSel): ?><i class="bi bi-check-lg ms-2"></i><?php endif; ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item small text-secondary d-flex align-items-center gap-2" href="<?= \App\Core\Controller::url('/select-concern?switch=1') ?>">
                                        <i class="bi bi-grid"></i> View All Locations Card
                                    </a>
                                </li>
                            </ul>
                        </div>
                    <?php elseif ($currentConcern): ?>
                        <span class="concern-pill" title="Concern GSTIN: <?= htmlspecialchars($currentConcern['code']) ?>">
                            <i class="bi bi-building"></i> <span class="text-truncate" style="max-width: 160px;"><?= htmlspecialchars($currentConcern['name']) ?></span>
                        </span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="navbar-right">
                <div class="dropdown">
                    <button class="btn btn-sm btn-light border dropdown-toggle d-flex align-items-center gap-1.5 shadow-sm px-2.5" type="button" data-bs-toggle="dropdown" style="height: 32px; font-size: 0.82rem; border-radius: 8px;">
                        <i class="bi bi-person-circle fs-6 text-secondary"></i>
                        <span class="fw-medium text-truncate" style="max-width: 140px;"><?= htmlspecialchars($currentUser['name'] ?? 'User') ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li class="dropdown-header text-muted small"><?= htmlspecialchars($currentUser['email'] ?? '') ?></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger d-flex align-items-center gap-2" href="<?= \App\Core\Controller::url('/logout') ?>">
                                <i class="bi bi-box-arrow-right"></i> Sign Out
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- FLASH NOTIFICATIONS -->
        <div class="px-4 pt-3">
            <?php if (!empty($flashSuccess)): ?>
                <div class="alert alert-success alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
                    <i class="bi bi-check-circle-fill fs-5 me-2"></i>
                    <div><?= nl2br(htmlspecialchars($flashSuccess)) ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($flashWarning)): ?>
                <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                    <div><?= nl2br(htmlspecialchars($flashWarning)) ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($flashError)): ?>
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                    <div><?= nl2br(htmlspecialchars($flashError)) ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>

        <!-- CONTENT BODY -->
        <main class="content-body">
            <?php require $contentView; ?>
        </main>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="<?= \App\Core\Controller::url('/assets/js/bootstrap.bundle.min.js') ?>"></script>
    <script>window.hsnSearchEndpoint = <?= json_encode(\App\Core\Controller::url('/api/hsn/search'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;</script>
    <script src="<?= \App\Core\Controller::url('/assets/js/hsn-selector.js') ?>"></script>
    <script src="<?= \App\Core\Controller::url('/assets/js/qrcode.min.js') ?>"></script>
    <script src="<?= \App\Core\Controller::url('/assets/js/html5-qrcode.min.js') ?>"></script>

    <?php if (!empty($flashError) || !empty($flashWarning) || !empty($flashSuccess)): ?>
        <!-- FLASH ALERT POPUP MODAL -->
        <div class="modal fade" id="modalFlashAlertPopup" tabindex="-1" aria-hidden="true" style="z-index: 1090;">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
                <div class="modal-content border-0 shadow-lg rounded-4 text-center p-4">
                    <div class="modal-body p-0">
                        <div class="mb-3">
                            <?php if (!empty($flashError)): ?>
                                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger" style="width: 64px; height: 64px;">
                                    <i class="bi bi-exclamation-octagon-fill fs-1"></i>
                                </span>
                            <?php elseif (!empty($flashWarning)): ?>
                                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning-subtle text-warning-emphasis" style="width: 64px; height: 64px;">
                                    <i class="bi bi-exclamation-triangle-fill fs-1"></i>
                                </span>
                            <?php else: ?>
                                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle text-success" style="width: 64px; height: 64px;">
                                    <i class="bi bi-check-circle-fill fs-1"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">
                            <?= !empty($flashError) ? 'Notice' : (!empty($flashWarning) ? 'Warning' : 'Success') ?>
                        </h5>
                        <p class="text-secondary small mb-4">
                            <?= nl2br(htmlspecialchars($flashError ?: ($flashWarning ?: $flashSuccess))) ?>
                        </p>
                        <button type="button" class="btn <?= !empty($flashError) ? 'btn-danger' : (!empty($flashWarning) ? 'btn-warning text-dark' : 'btn-success') ?> w-100 rounded-pill py-2 fw-semibold shadow-sm" data-bs-dismiss="modal">
                            OK
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var el = document.getElementById('modalFlashAlertPopup');
                if (el && typeof bootstrap !== 'undefined') {
                    new bootstrap.Modal(el).show();
                }
            });
        </script>
    <?php endif; ?>
</body>
</html>
