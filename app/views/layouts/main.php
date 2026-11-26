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
        #sidebar .nav-sublink {
            color: #94a3b8;
            padding: 0.45rem 1.5rem 0.45rem 3.1rem;
            display: flex;
            align-items: center;
            font-size: 0.84rem;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
        }
        #sidebar .nav-sublink:hover {
            color: #ffffff;
            background: var(--sidebar-hover);
        }
        #sidebar .nav-sublink.active {
            color: #ffffff;
            background: var(--sidebar-active);
            font-weight: 600;
        }
        #sidebar .nav-group-toggle {
            cursor: pointer;
            user-select: none;
            width: 100%;
            border: none;
            background: transparent;
            text-align: left;
        }
        #sidebar .nav-group-toggle .arrow-icon {
            transition: transform 0.2s ease;
            font-size: 0.8rem;
            margin-left: auto;
        }
        #sidebar .nav-group-toggle[aria-expanded="true"] .arrow-icon,
        #sidebar .nav-group-toggle:not(.collapsed) .arrow-icon {
            transform: rotate(180deg);
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
            z-index: 1030;
            gap: 1rem;
            overflow: visible !important;
        }
        .navbar-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 0;
            flex: 0 1 auto;
            overflow: visible !important;
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
            $activeRoute = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            ?>
            <?php if (!$isSuperAdmin): ?>
                <div class="nav-group-title">Dashboard</div>
                <a href="<?= \App\Core\Controller::url('/dashboard') ?>" class="nav-link <?= (in_array($activeRoute, ['/dashboard', '/']) || (isset($currentModule) && $currentModule === 'concern_dashboard')) ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            <?php endif; ?>
            <?php
            $grouped = [];
            $combinedSidebarGroups = ['Stock', 'Shop', 'Sales', 'Finance'];
            foreach ($sidebarModules as $m) {
                if ($m['code'] === 'variants' || $m['code'] === 'suppliers' || $m['code'] === 'catalog_mapping' || $m['code'] === 'purchase_damage' || $m['code'] === 'concern_dashboard' || $m['code'] === 'admin_concerns' || $m['code'] === 'dispatch' || $m['group_name'] === 'Masters') continue;
                $groupName = $m['code'] === 'catalog_mapping' ? 'Purchase' : $m['group_name'];
                if (in_array($groupName, $combinedSidebarGroups, true)) {
                    $groupName = 'Modules';
                }
                $grouped[$groupName][] = $m;
            }

            foreach ($grouped as $groupName => $mods):
                if ($groupName === 'Staff' || $groupName === 'Masters') continue;
                // If Finance/Modules group, merge 'cash' and 'cash_flow' into a single 'Cash & Cash Flow' module
                $financeExpenseModule = null;
                $hasFinanceCashFlow = false;
                if ($groupName === 'Finance' || $groupName === 'Modules') {
                    $hasCashOrFlow = false;
                    $mergedMods = [];
                    foreach ($mods as $m) {
                        if ($m['code'] === 'expense') {
                            $financeExpenseModule = $m;
                            continue;
                        }
                        if ($m['code'] === 'cash') {
                            $hasCashOrFlow = true;
                            continue; // skip separate cash entry
                        }
                        if ($m['code'] === 'cash_flow') {
                            $hasCashOrFlow = true;
                            $hasFinanceCashFlow = true;
                            $m['name'] = 'Cash & Cash Flow';
                            $m['icon'] = 'bi-cash-coin';
                            $mergedMods[] = $m;
                            continue;
                        }
                        $mergedMods[] = $m;
                    }
                    if ($financeExpenseModule !== null && !$hasFinanceCashFlow) {
                        $mergedMods[] = $financeExpenseModule;
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
                    if ($m['code'] === 'qc_stocks') $href = \App\Core\Controller::url('/production/qc');
                    if ($m['code'] === 'return_exchange') $href = \App\Core\Controller::url('/production/returns');
                    if ($m['code'] === 'request') $href = \App\Core\Controller::url('/production/stock-requests');
                    if ($m['code'] === 'outward') $href = \App\Core\Controller::url('/production/outward');

                    if ($m['code'] === 'cash_flow') {
                        $isActive = (isset($currentModule) && in_array($currentModule, ['cash_flow', 'cash'])) || 
                                    (str_contains($activeRoute, 'cash'));
                    } elseif ($m['code'] === 'stitching_wastage') {
                        $isActive = (isset($currentModule) && in_array($currentModule, ['stitching_wastage', 'stitching'])) || 
                                    (str_contains($activeRoute, 'stitching'));
                    } elseif ($m['code'] === 'qc_stocks') {
                        $isActive = (isset($currentModule) && in_array($currentModule, ['qc_stocks', 'qc'])) || 
                                    (str_contains($activeRoute, 'qc'));
                    } else {
                        $isActive = (isset($currentModule) && $currentModule === $m['code']) || 
                                    (bool)preg_match('#(?:^|/m/)' . preg_quote($m['code'], '#') . '(?:/|$)#', $activeRoute);
                    }
                ?>
                    <?php if ($m['code'] === 'cash_flow' && $hasFinanceCashFlow && $financeExpenseModule !== null):
                        $isCashFlowGroupActive = (isset($currentModule) && in_array($currentModule, ['cash_flow', 'cash', 'expense'], true))
                            || str_contains($activeRoute, 'cash')
                            || str_contains($activeRoute, '/m/expense');
                        $isCashFlowOverviewActive = (isset($currentModule) && in_array($currentModule, ['cash_flow', 'cash'], true))
                            || (str_contains($activeRoute, 'cash') && !str_contains($activeRoute, '/m/expense'));
                        $isExpenseActive = (isset($currentModule) && $currentModule === 'expense')
                            || str_contains($activeRoute, '/m/expense');
                    ?>
                        <button class="nav-link nav-group-toggle <?= $isCashFlowGroupActive ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#submenuCashFlow" aria-expanded="<?= $isCashFlowGroupActive ? 'true' : 'false' ?>" aria-controls="submenuCashFlow">
                            <i class="bi <?= htmlspecialchars($m['icon']) ?>"></i>
                            <span><?= htmlspecialchars($m['name']) ?></span>
                            <i class="bi bi-chevron-down arrow-icon"></i>
                        </button>
                        <div class="collapse <?= $isCashFlowGroupActive ? 'show' : '' ?>" id="submenuCashFlow">
                            <a href="<?= \App\Core\Controller::url('/m/cash_flow') ?>" class="nav-sublink <?= $isCashFlowOverviewActive ? 'active' : '' ?>">
                                <span>Cash Flow Overview</span>
                            </a>
                            <a href="<?= \App\Core\Controller::url('/m/expense') ?>" class="nav-sublink <?= $isExpenseActive ? 'active' : '' ?>">
                                <span>Expenses</span>
                            </a>
                        </div>
                    <?php else: ?>
                        <a href="<?= $href ?>" class="nav-link <?= $isActive ? 'active' : '' ?>">
                            <i class="bi <?= htmlspecialchars($m['icon']) ?>"></i>
                            <span><?= htmlspecialchars($m['name']) ?></span>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <?php if ($isSuperAdmin || \App\Core\Auth::can('staff', 'view')): 
                $isStaffActive = (isset($currentModule) && $currentModule === 'staff') || str_contains($activeRoute, '/m/staff');
                $isAddStaffActive = (isset($currentModule) && $currentModule === 'staff' && isset($currentSubModule) && $currentSubModule === 'create') || str_contains($activeRoute, '/m/staff/create');
                $isTotalStaffActive = (isset($currentModule) && $currentModule === 'staff' && (empty($currentSubModule) || $currentSubModule === 'all')) && !str_contains($activeRoute, '/m/staff/create') && !str_contains($activeRoute, '/m/staff/roles');
                $isRolesActive = (isset($currentModule) && $currentModule === 'staff' && isset($currentSubModule) && $currentSubModule === 'roles') || str_contains($activeRoute, '/m/staff/roles');
            ?>
            <div class="nav-group-title">Staff</div>
            <button class="nav-link nav-group-toggle <?= $isStaffActive ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#submenuStaff" aria-expanded="<?= $isStaffActive ? 'true' : 'false' ?>" aria-controls="submenuStaff">
                <i class="bi bi-people"></i>
                <span>Staff</span>
                <i class="bi bi-chevron-down arrow-icon"></i>
            </button>
            <div class="collapse <?= $isStaffActive ? 'show' : '' ?>" id="submenuStaff">
                <?php if ($isSuperAdmin || \App\Core\Auth::can('staff', 'add')): ?>
                <a href="<?= \App\Core\Controller::url('/m/staff/create') ?>" class="nav-sublink <?= $isAddStaffActive ? 'active' : '' ?>">
                    <span>Add Staff</span>
                </a>
                <?php endif; ?>
                <a href="<?= \App\Core\Controller::url('/m/staff') ?>" class="nav-sublink <?= $isTotalStaffActive ? 'active' : '' ?>">
                    <span>Total Staffs</span>
                </a>
                <?php if ($isSuperAdmin || \App\Core\Auth::can('staff', 'view')): ?>
                <a href="<?= \App\Core\Controller::url('/m/staff/roles') ?>" class="nav-sublink <?= $isRolesActive ? 'active' : '' ?>">
                    <span>Role Assign / Permissions</span>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
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
                    <?php
                        $switcherConcerns = \App\Core\Auth::allConcerns();
                        $isConsolidated = ($currentConcern === null || (isset($_SESSION['super_admin_concern_id']) && (int)$_SESSION['super_admin_concern_id'] === 0));
                        $displayConcernName = $isConsolidated || empty($currentConcern) ? 'ALL CONCERNS' : $currentConcern['name'];
                    ?>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-primary dropdown-toggle d-flex align-items-center gap-1.5 fw-semibold shadow-sm concern-switcher-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="<?= htmlspecialchars($displayConcernName) ?>">
                            <i class="bi bi-building"></i>
                            <span class="text-truncate" style="max-width: 155px;"><?= htmlspecialchars($displayConcernName) ?></span>
                        </button>
                        <ul class="dropdown-menu shadow" style="max-height: 420px; overflow-y: auto;">
                            <li>
                                <a class="dropdown-item d-flex align-items-center justify-content-between <?= $isConsolidated ? 'active fw-bold' : '' ?>" href="<?= \App\Core\Controller::url('/admin/switch-concern?id=0') ?>">
                                    <span><i class="bi bi-globe me-2 <?= $isConsolidated ? 'text-white' : 'text-primary' ?>"></i> ALL CONCERNS</span>
                                    <?php if ($isConsolidated): ?><i class="bi bi-check-lg"></i><?php endif; ?>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li class="dropdown-header text-uppercase small fw-bold d-flex justify-content-between align-items-center" style="font-size: 0.72rem; color: #64748b;">
                                <span>Concerns</span>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= count($switcherConcerns) ?></span>
                            </li>
                            <?php foreach ($switcherConcerns as $con): 
                                $isSel = !$isConsolidated && $currentConcern && ((int)$currentConcern['id'] === (int)$con['id']);
                            ?>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center justify-content-between <?= $isSel ? 'active fw-bold' : '' ?>" href="<?= \App\Core\Controller::url('/admin/switch-concern?id=' . $con['id']) ?>">
                                        <span class="d-flex align-items-center gap-2">
                                            <i class="bi bi-building <?= $isSel ? 'text-white' : 'text-secondary' ?>"></i>
                                            <span class="fw-semibold"><?= htmlspecialchars($con['name']) ?></span>
                                            <small class="<?= $isSel ? 'text-white-50' : 'text-muted' ?> font-monospace">(<?= htmlspecialchars($con['code']) ?>)</small>
                                        </span>
                                        <?php if ($isSel): ?><i class="bi bi-check-lg ms-2"></i><?php endif; ?>
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
                        <div class="d-flex align-items-center gap-1.5">
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
                                        <a class="dropdown-item small text-primary fw-semibold d-flex align-items-center gap-2" href="#" data-bs-toggle="modal" data-bs-target="#modalStaffLocationPopup">
                                            <i class="bi bi-grid-fill"></i> Switch Location Popup
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1 d-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#modalStaffLocationPopup" title="Open Switch Location Dialog">
                                <i class="bi bi-arrow-left-right text-primary"></i>
                                <span class="d-none d-md-inline small fw-semibold">Switch</span>
                            </button>
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

    <?php 
    $modalConcerns = \App\Core\Auth::check() ? \App\Core\Auth::getUserConcerns(\App\Core\Auth::id()) : [];
    if (count($modalConcerns) > 1): 
    ?>
    <!-- LOCATION / CONCERN SWITCHER POPUP MODAL -->
    <div class="modal fade" id="modalStaffLocationPopup" tabindex="-1" aria-labelledby="modalStaffLocationPopupLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content shadow-lg border-0" style="border-radius: 1.25rem; overflow: hidden;">
                <div class="modal-header bg-dark text-white p-3 px-4 border-0">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="rounded-circle bg-primary bg-opacity-25 p-2 text-primary d-flex align-items-center justify-content-center">
                            <i class="bi bi-buildings-fill fs-5"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-white mb-0" id="modalStaffLocationPopupLabel">Select Authorized Concern</h5>
                            <small class="text-secondary">Switch workspace to view dashboard and permitted modules</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <p class="text-secondary small mb-3">
                        You have authorized access to <strong><?= count($modalConcerns) ?></strong> business concerns. Click on any concern to load its dashboard:
                    </p>
                    <div class="row g-3">
                        <?php foreach ($modalConcerns as $con): 
                            $isCur = ($currentConcern && $currentConcern['id'] == $con['id']);
                            $conIsShop = \App\Core\Auth::isShopConcern((int)$con['id']);
                        ?>
                            <div class="col-md-6 col-12">
                                <a href="<?= \App\Core\Controller::url('/switch-concern?id=' . $con['id']) ?>" class="text-decoration-none d-block h-100">
                                    <div class="card h-100 border-2 <?= $isCur ? 'border-primary shadow-sm bg-white' : 'border-light-subtle bg-white' ?> rounded-4 p-3.5 transition-all hover-shadow">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span class="badge font-monospace <?= $conIsShop ? 'bg-success' : 'bg-primary' ?>">
                                                <?= htmlspecialchars($con['code']) ?>
                                            </span>
                                            <?php if ($isCur): ?>
                                                <span class="badge bg-primary text-white"><i class="bi bi-check-circle-fill me-1"></i> Current Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-secondary border">Switch <i class="bi bi-arrow-right ms-1"></i></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex align-items-center gap-3 my-2">
                                            <div class="rounded-3 p-2.5 <?= $conIsShop ? 'bg-success bg-opacity-10 text-success' : 'bg-primary bg-opacity-10 text-primary' ?>">
                                                <i class="bi <?= $conIsShop ? 'bi-shop fs-3' : 'bi-building-gear fs-3' ?>"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($con['name']) ?></h6>
                                                <small class="text-muted d-block" style="font-size: 0.76rem;">
                                                    <i class="bi bi-speedometer2 text-primary me-1"></i> Open Concern Dashboard
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer bg-white py-2.5 px-4 justify-content-between border-top">
                    <small class="text-muted">Logged in as <?= htmlspecialchars(\App\Core\Auth::user()['name'] ?? '') ?></small>
                    <button type="button" class="btn btn-secondary rounded-pill px-4 btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>
