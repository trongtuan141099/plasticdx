<?php
/**
 * Main Entry Point: System Management Portal (PlasticDX)
 * Complete Flow: Registration -> Login -> Default User View -> RBAC -> Admin Workspace
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/config/database.php';

$conn = getPortalDbConnection();
$currentUser = getPortalUser();
$isLogged = !empty($currentUser['is_logged']);
$isAdmin = isPortalAdmin();

// Pre-load initial systems for immediate first-paint rendering
$initialSystems = [];
$initialServerCount = 0;
$initialDevCount = 0;
$sysQuery = $conn->query("
    SELECT id, system_code, name, description, icon, icon_color, url, group_type, is_active, access_role, display_order
    FROM portal_systems
    ORDER BY display_order ASC, id ASC
");
if ($sysQuery) {
    while ($sRow = $sysQuery->fetch_assoc()) {
        $sActive = (int)($sRow['is_active'] ?? 1);
        $sRole = !empty($sRow['access_role']) ? (string)$sRow['access_role'] : 'all';
        if (!$isAdmin) {
            if ($sActive !== 1 || !canAccessPortalItem($sRole)) {
                continue;
            }
        }

        // Parse separate Server and Development URLs for SSR
        $rawU = (string)($sRow['url'] ?? '');
        $uServer = '';
        $uDev = '';
        if (strpos($rawU, '|') !== false) {
            $parts = explode('|', $rawU, 2);
            $uServer = trim($parts[0] ?? '');
            $uDev = trim($parts[1] ?? '');
        } else {
            if (($sRow['group_type'] ?? '') === 'DEVELOPMENT') {
                $uDev = trim($rawU);
            } else {
                $uServer = trim($rawU);
            }
        }
        $sRow['url_server'] = $uServer;
        $sRow['url_dev'] = $uDev;

        $hasServer = ($uServer !== '');
        $hasDev = ($uDev !== '');

        if ($hasServer && !$hasDev) {
            $initialServerCount++;
        } elseif (!$hasServer && $hasDev) {
            $initialDevCount++;
        } else {
            // Configured for both Server and Dev
            $initialServerCount++;
            $initialDevCount++;
        }

        $initialSystems[] = $sRow;
    }
}
$initialTotalCount = count($initialSystems);
$cssVersion = @filemtime(__DIR__ . '/assets/css/portal.css') ?: time();
$jsVersion = @filemtime(__DIR__ . '/assets/js/portal.js') ?: time();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Quản lý Hệ thống - DX Plastic</title>
    <link rel="stylesheet" href="assets/css/portal.css?v=<?= (int)$cssVersion ?>">
    <script>
        // Initialize saved theme immediately to prevent screen flash
        (function () {
            var savedTheme = localStorage.getItem('plasticdx_theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>
<body>

    <!-- ====================================================================
         MINIMAL TOP HEADER
         ==================================================================== -->
    <header class="portal-header">
        <div class="header-inner">
            <a href="index.php" class="brand-logo">
                <div class="brand-icon">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="7" height="7" x="3" y="3" rx="1.5"></rect>
                        <rect width="7" height="7" x="14" y="3" rx="1.5"></rect>
                        <rect width="7" height="7" x="14" y="14" rx="1.5"></rect>
                        <rect width="7" height="7" x="3" y="14" rx="1.5"></rect>
                    </svg>
                </div>
                <div>
                    <div class="brand-title">Portal Hệ Thống</div>
                    <div class="brand-subtitle">Trung tâm truy cập ứng dụng & tài liệu nội bộ</div>
                </div>
            </a>

            <div class="header-actions">
                <!-- Admin Workspace Toggle (Strictly hidden for regular logged-in Users) -->
                <button type="button" class="btn btn-outline btn-sm" id="btnToggleAdminView"
                        style="display: <?= $isAdmin ? 'inline-flex' : 'none' ?>;">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <span>Quản trị</span>
                </button>

                <!-- User Profile Trigger Pill (Visible when logged in) -->
                <button type="button" class="user-profile-trigger" id="btnUserProfileTrigger"
                        style="display: <?= $isLogged ? 'inline-flex' : 'none' ?>;"
                        title="Xem & cập nhật Hồ sơ cá nhân">
                    <span class="user-avatar-sm" id="headerUserAvatar">
                        <?= htmlspecialchars(mb_strtoupper(mb_substr($currentUser['full_name'] ?? 'U', 0, 1))) ?>
                    </span>
                    <span id="headerUserName"><?= htmlspecialchars($currentUser['full_name'] ?? 'Người dùng') ?></span>
                    <span class="role-tag <?= $isAdmin ? 'tag-admin' : '' ?>" id="headerUserRoleTag">
                        <?= $isAdmin ? 'Admin' : 'User' ?>
                    </span>
                </button>

                <button type="button" class="btn btn-outline btn-icon" id="btnThemeToggle" title="Chuyển chế độ Sáng / Tối">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="4"></circle>
                        <path d="M12 2v2"></path>
                        <path d="M12 20v2"></path>
                        <path d="m4.93 4.93 1.41 1.41"></path>
                        <path d="m17.66 17.66 1.41 1.41"></path>
                        <path d="M2 12h2"></path>
                        <path d="M20 12h2"></path>
                        <path d="m6.34 17.66-1.41 1.41"></path>
                        <path d="m19.07 4.93-1.41 1.41"></path>
                    </svg>
                </button>

                <!-- Guest Login & Register Buttons -->
                <button type="button" class="btn btn-outline btn-sm" id="btnOpenLoginModal"
                        style="display: <?= $isLogged ? 'none' : 'inline-flex' ?>;">
                    <span>Đăng nhập</span>
                </button>

                <button type="button" class="btn btn-primary btn-sm" id="btnOpenRegisterModal"
                        style="display: <?= $isLogged ? 'none' : 'inline-flex' ?>;">
                    <span>Đăng ký</span>
                </button>

                <!-- Logout Button -->
                <button type="button" class="btn btn-danger-soft btn-sm" id="btnLogout"
                        style="display: <?= $isLogged ? 'inline-flex' : 'none' ?>;">
                    <span>Đăng xuất</span>
                </button>
            </div>
        </div>
    </header>

    <!-- ====================================================================
         MAIN PORTAL CONTAINER
         ==================================================================== -->
    <main class="portal-container">

        <!-- ================================================================
             DEFAULT USER PORTAL VIEW (Always shown by default after login)
             ================================================================ -->
        <div id="viewPortalMain">

            <!-- TOP COMMAND BAR: SEARCH & SEGMENTED FILTERS -->
            <div class="minimal-top-bar">
                <div class="minimal-search-wrap">
                    <svg class="search-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" x2="16.65" y1="21" y2="16.65"></line>
                    </svg>
                    <input type="text" id="systemSearchInput" class="search-input"
                           placeholder="Tìm nhanh hệ thống...">
                    <span class="search-kbd" id="systemSearchKbd">Ctrl + K</span>
                    <button type="button" id="systemSearchClear" class="search-clear-btn" title="Xóa">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" x2="6" y1="6" y2="18"></line>
                            <line x1="6" x2="18" y1="6" y2="18"></line>
                        </svg>
                    </button>
                </div>

                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <div class="segmented-filters" role="tablist" aria-label="Lọc nhóm hệ thống">
                        <button type="button" class="filter-pill active" data-sys-filter="ALL">
                            <span>Tất cả</span>
                            <span class="pill-count" id="countFilterAll"><?= (int)$initialTotalCount ?></span>
                        </button>
                        <button type="button" class="filter-pill" data-sys-filter="SERVER">
                            <span>Server</span>
                            <span class="pill-count" id="countFilterServer"><?= (int)$initialServerCount ?></span>
                        </button>
                        <button type="button" class="filter-pill" data-sys-filter="DEVELOPMENT">
                            <span>Development</span>
                            <span class="pill-count" id="countFilterDev"><?= (int)$initialDevCount ?></span>
                        </button>
                    </div>

                    <button type="button" class="btn btn-primary" id="btnQuickAddSystem"
                            style="display: <?= $isAdmin ? 'inline-flex' : 'none' ?>;"
                            onclick="window.PortalApp.openAddSystemModal('SERVER')">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" x2="12" y1="5" y2="19"></line>
                            <line x1="5" x2="19" y1="12" y2="12"></line>
                        </svg>
                        <span>Thêm hệ thống</span>
                    </button>
                </div>
            </div>

            <!-- UNIFIED MINIMAL SYSTEM CARDS GRID -->
            <div class="systems-grid" id="gridUnifiedSystems">
                <?php if (empty($initialSystems)): ?>
                    <div class="empty-state" style="grid-column: 1 / -1;">
                        Chưa có hệ thống nào được cấu hình
                    </div>
                <?php else: ?>
                    <?php foreach ($initialSystems as $initSys):
                        $initId = (int)($initSys['id'] ?? 0);
                        $initName = htmlspecialchars((string)($initSys['name'] ?? 'Hệ thống'), ENT_QUOTES, 'UTF-8');
                        $initDesc = htmlspecialchars((string)($initSys['description'] ?? 'Hệ thống thành phần trong Portal.'), ENT_QUOTES, 'UTF-8');
                        $initActive = ((int)($initSys['is_active'] ?? 1)) === 1;
                        $hasSrv = !empty($initSys['url_server']);
                        $hasDev = !empty($initSys['url_dev']);

                        if ($hasSrv && !$hasDev) {
                            $cardTitle = "Nhấn để truy cập trực tiếp {$initName} trên Server";
                            $badgeHtml = '<span class="card-env-pill pill-server">Server</span>';
                            $launchText = 'Mở Server';
                        } elseif (!$hasSrv && $hasDev) {
                            $cardTitle = "Nhấn để truy cập trực tiếp {$initName} trên Development";
                            $badgeHtml = '<span class="card-env-pill pill-dev">Dev</span>';
                            $launchText = 'Mở Dev';
                        } else {
                            $cardTitle = "Nhấn để chọn môi trường truy cập {$initName}";
                            $badgeHtml = '<span class="card-env-pill pill-dual">Server • Dev</span>';
                            $launchText = 'Truy cập';
                        }
                    ?>
                        <article class="system-card <?= !$initActive ? 'card-inactive' : '' ?>"
                                 onclick="window.PortalApp && window.PortalApp.handleSystemClick(<?= $initId ?>)"
                                 title="<?= $cardTitle ?>">
                            <div class="card-main">
                                <div class="system-icon-wrap">
                                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <rect width="7" height="7" x="3" y="3" rx="1"></rect>
                                        <rect width="7" height="7" x="14" y="3" rx="1"></rect>
                                        <rect width="7" height="7" x="14" y="14" rx="1"></rect>
                                        <rect width="7" height="7" x="3" y="14" rx="1"></rect>
                                    </svg>
                                </div>
                                <div class="card-info">
                                    <div style="display:flex; align-items:center; justify-content:space-between; gap:6px; margin-bottom:4px;">
                                        <h3 class="system-title" style="margin-bottom:0;"><?= $initName ?></h3>
                                        <?= $badgeHtml ?>
                                    </div>
                                    <p class="system-desc"><?= $initDesc ?></p>
                                </div>
                            </div>
                            <div class="card-actions-bar">
                                <button type="button" class="btn-card-doc"
                                        onclick="window.PortalApp && window.PortalApp.openSystemDocsModal(<?= $initId ?>, event)"
                                        title="Xem tài liệu hướng dẫn của <?= $initName ?>">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"></path>
                                    </svg>
                                    <span>Tài liệu</span>
                                </button>
                                <span class="card-launch-hint">
                                    <span><?= $launchText ?></span>
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14"></path>
                                        <path d="m12 5 7 7-7 7"></path>
                                    </svg>
                                </span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>

        <!-- ================================================================
             ADMINISTRATION WORKSPACE (Accessible only with Admin privileges)
             ================================================================ -->
        <div id="viewAdminMain" style="display: none;">
            <div class="admin-top-bar">
                <div class="segmented-filters" role="tablist" aria-label="Phân hệ quản trị">
                    <button type="button" class="filter-pill active" data-admin-tab="systems">
                        <span>1. Hệ thống & Đường dẫn</span>
                        <span class="pill-count" id="adminCountSystems">0</span>
                    </button>
                    <button type="button" class="filter-pill" data-admin-tab="docs">
                        <span>2. Tài liệu hướng dẫn</span>
                        <span class="pill-count" id="adminCountDocs">0</span>
                    </button>
                    <button type="button" class="filter-pill" data-admin-tab="users">
                        <span>3. Người dùng & Phân quyền</span>
                        <span class="pill-count" id="adminCountUsers">0</span>
                    </button>
                </div>

                <button type="button" class="btn btn-outline btn-sm" onclick="window.PortalApp.toggleAdminView('portal')">
                    ← Quay lại giao diện User
                </button>
            </div>

            <!-- ADMIN TAB 1: SYSTEMS & URLS -->
            <div class="admin-panel-card" id="adminPanelSystems">
                <div class="admin-panel-header">
                    <div>
                        <strong style="font-size:15px;">Quản lý Hệ thống & Đường dẫn (URL)</strong>
                        <p style="font-size:12px; color:var(--text-muted);">Cấu hình tên, mô tả, đường dẫn Server/Development và quyền truy cập từng hệ thống.</p>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="window.PortalApp.openAddSystemModal('DEVELOPMENT')">
                            + Thêm Development
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" onclick="window.PortalApp.openAddSystemModal('SERVER')">
                            + Thêm Server
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>TT</th>
                                <th>Hệ thống / Bộ phận</th>
                                <th>Đường dẫn (URL)</th>
                                <th>Môi trường</th>
                                <th>Quyền truy cập</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody id="adminSystemsTableBody"></tbody>
                    </table>
                </div>
            </div>

            <!-- ADMIN TAB 2: GUIDE DOCUMENTS -->
            <div class="admin-panel-card" id="adminPanelDocs" style="display: none;">
                <div class="admin-panel-header">
                    <div>
                        <strong style="font-size:15px;">Quản lý Tài liệu Hướng dẫn</strong>
                        <p style="font-size:12px; color:var(--text-muted);">Gắn tài liệu PDF, Word, Video hoặc Trang hướng dẫn nội bộ trực tiếp vào từng hệ thống.</p>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.PortalApp.openAddDocModal()">
                        + Thêm tài liệu mới
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>TT</th>
                                <th>Tiêu đề & Mô tả</th>
                                <th>Định dạng</th>
                                <th>Hệ thống liên kết</th>
                                <th>Đường dẫn (URL / File)</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody id="adminDocsTableBody"></tbody>
                    </table>
                </div>
            </div>

            <!-- ADMIN TAB 3: USERS & RBAC PERMISSIONS -->
            <div class="admin-panel-card" id="adminPanelUsers" style="display: none;">
                <div class="admin-panel-header">
                    <div>
                        <strong style="font-size:15px;">Quản lý Người dùng & Phân quyền (RBAC)</strong>
                        <p style="font-size:12px; color:var(--text-muted);">Danh sách tài khoản đã đăng ký, thông tin hồ sơ chi tiết và cấp quyền User / Admin.</p>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.PortalApp.openAddUserModal()">
                        + Thêm người dùng
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>Mã NV</th>
                                <th>Họ và tên / Username</th>
                                <th>Bộ phận</th>
                                <th>Email / Điện thoại</th>
                                <th>Vai trò (Phân quyền)</th>
                                <th>Trạng thái</th>
                                <th>Thao tác Quản trị</th>
                            </tr>
                        </thead>
                        <tbody id="adminUsersTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>

    <!-- ====================================================================
         MODAL 1: ENVIRONMENT SELECTION POPUP (2-STEP ACCESS FLOW)
         ==================================================================== -->
    <div class="modal-backdrop" id="envModalBackdrop">
        <div class="modal-dialog" style="max-width: 520px;">
            <div class="modal-header">
                <div style="display:flex; align-items:center; gap:12px; min-width:0;">
                    <div class="system-icon-wrap" id="envModalIcon" style="width:40px; height:40px;"></div>
                    <div style="min-width:0;">
                        <h3 class="modal-title" id="envModalTitle" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Chọn môi trường</h3>
                        <p id="envModalDesc" style="font-size:12.5px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"></p>
                    </div>
                </div>
                <button type="button" class="modal-close" data-close-modal="envModalBackdrop">&times;</button>
            </div>
            <div class="modal-body">
                <div style="font-size:12.5px; font-weight:600; color:var(--text-secondary); margin-bottom:10px;">
                    Chọn môi trường làm việc để mở hệ thống:
                </div>
                <div class="env-choices-grid" id="envModalChoices"></div>
            </div>
            <div class="modal-footer" style="justify-content:space-between;">
                <div id="envModalAdminActions" style="display:none; gap:6px;"></div>
                <button type="button" class="btn btn-outline btn-sm" data-close-modal="envModalBackdrop">Đóng</button>
            </div>
        </div>
    </div>

    <!-- ====================================================================
         MODAL 2: INTEGRATED SYSTEM DOCUMENTATION POPUP
         ==================================================================== -->
    <div class="modal-backdrop" id="systemDocsModalBackdrop">
        <div class="modal-dialog" style="max-width: 560px;">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title" id="sysDocsModalTitle">Tài liệu hướng dẫn</h3>
                    <p id="sysDocsModalSub" style="font-size:12.5px; color:var(--text-muted); margin-top:2px;"></p>
                </div>
                <button type="button" class="modal-close" data-close-modal="systemDocsModalBackdrop">&times;</button>
            </div>
            <div class="modal-body">
                <div class="doc-modal-list" id="sysDocsModalList"></div>
            </div>
            <div class="modal-footer" style="justify-content:space-between;">
                <div>
                    <button type="button" class="btn btn-primary btn-sm" id="btnAddDocForCurrentSystem" style="display:none;">
                        + Thêm tài liệu cho hệ thống này
                    </button>
                </div>
                <button type="button" class="btn btn-outline btn-sm" data-close-modal="systemDocsModalBackdrop">Đóng</button>
            </div>
        </div>
    </div>

    <!-- ====================================================================
         MODAL 3: UNIFIED AUTHENTICATION MODAL (LOGIN & REGISTRATION)
         ==================================================================== -->
    <div class="modal-backdrop" id="authModalBackdrop">
        <div class="modal-dialog" style="max-width: 480px;">
            <div class="modal-header">
                <h3 class="modal-title" id="authModalTitle">Đăng nhập Hệ thống</h3>
                <button type="button" class="modal-close" data-close-modal="authModalBackdrop">&times;</button>
            </div>
            <div class="modal-body">
                <div class="admin-auth-notice" id="adminAuthNotice">
                    Chức năng này yêu cầu quyền Quản trị viên (Admin). Vui lòng xác thực bằng tài khoản Admin.
                </div>

                <div class="auth-tabs">
                    <button type="button" class="auth-tab-btn active" id="tabBtnAuthLogin"
                            onclick="window.PortalApp.switchAuthTab('login')">
                        Đăng nhập
                    </button>
                    <button type="button" class="auth-tab-btn" id="tabBtnAuthRegister"
                            onclick="window.PortalApp.switchAuthTab('register')">
                        Đăng ký tài khoản
                    </button>
                </div>

                <!-- FORM A: LOGIN -->
                <form id="portalLoginForm">
                    <input type="hidden" id="loginRequireAdmin" value="0">

                    <div class="form-group" style="margin-bottom:14px;">
                        <label class="form-label" for="loginUsername">Tên đăng nhập / Mã nhân viên / Email *</label>
                        <input type="text" id="loginUsername" class="form-control" required
                               placeholder="Nhập username, mã nhân viên hoặc email">
                    </div>

                    <div class="form-group" style="margin-bottom:16px;">
                        <label class="form-label" for="loginPassword">Mật khẩu *</label>
                        <input type="password" id="loginPassword" class="form-control" required
                               placeholder="Nhập mật khẩu">
                    </div>

                    <div style="background:var(--bg-subtle); border:1px dashed var(--border-color); border-radius:8px; padding:10px 12px; margin-bottom:16px; font-size:12px;">
                        <div style="font-weight:600; color:var(--text-secondary); margin-bottom:6px;">Tài khoản mẫu kiểm thử nhanh:</div>
                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                            <button type="button" class="btn btn-sm btn-outline"
                                    onclick="window.PortalApp.fillDemoAccount('user', 'user123')">
                                Điền tài khoản User (user / user123)
                            </button>
                            <button type="button" class="btn btn-sm btn-outline"
                                    onclick="window.PortalApp.fillDemoAccount('admin', 'admin123')">
                                Điền tài khoản Admin (admin / admin123)
                            </button>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:8px;">
                        <button type="button" class="btn btn-outline" data-close-modal="authModalBackdrop">Đóng</button>
                        <button type="submit" class="btn btn-primary">Đăng nhập</button>
                    </div>
                </form>

                <!-- FORM B: ACCOUNT REGISTRATION -->
                <form id="portalRegisterForm" style="display: none;">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label" for="regFullName">Họ và tên đầy đủ *</label>
                            <input type="text" id="regFullName" class="form-control" required
                                   placeholder="VD: Nguyễn Văn A">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="regUsername">Tên đăng nhập *</label>
                            <input type="text" id="regUsername" class="form-control" required
                                   placeholder="VD: nguyenvana">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="regEmployeeCode">Mã nhân viên (MSNV)</label>
                            <input type="text" id="regEmployeeCode" class="form-control"
                                   placeholder="VD: NV-102 (Tự tạo nếu trống)">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="regDepartment">Bộ phận / Phòng ban *</label>
                            <select id="regDepartment" class="form-control">
                                <option value="Sản xuất & QC">Sản xuất & QC</option>
                                <option value="Kỹ thuật Đùn">Kỹ thuật Đùn</option>
                                <option value="Đóng gói & Kho">Đóng gói & Kho</option>
                                <option value="Pha trộn Nguyên liệu">Pha trộn Nguyên liệu</option>
                                <option value="IT & DX Center">IT & DX Center</option>
                                <option value="Hành chính Nhân sự">Hành chính Nhân sự</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="regPhone">Số điện thoại / Máy lẻ</label>
                            <input type="text" id="regPhone" class="form-control"
                                   placeholder="VD: 0901234567">
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label" for="regEmail">Địa chỉ Email</label>
                            <input type="email" id="regEmail" class="form-control"
                                   placeholder="VD: nguyenvana@plasticdx.local">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="regPassword">Mật khẩu (tối thiểu 6 ký tự) *</label>
                            <input type="password" id="regPassword" class="form-control" required minlength="6"
                                   placeholder="••••••••">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="regPasswordConfirm">Xác nhận mật khẩu *</label>
                            <input type="password" id="regPasswordConfirm" class="form-control" required minlength="6"
                                   placeholder="••••••••">
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:18px;">
                        <button type="button" class="btn btn-outline" data-close-modal="authModalBackdrop">Hủy</button>
                        <button type="submit" class="btn btn-primary">Hoàn tất Đăng ký</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ====================================================================
         MODAL 4: USER PROFILE & ACCOUNT DETAILS MODAL
         ==================================================================== -->
    <div class="modal-backdrop" id="profileModalBackdrop">
        <div class="modal-dialog" style="max-width: 540px;">
            <div class="modal-header">
                <h3 class="modal-title">Hồ Sơ Cá Nhân & Chi Tiết Tài Khoản</h3>
                <button type="button" class="modal-close" data-close-modal="profileModalBackdrop">&times;</button>
            </div>
            <form id="portalProfileForm">
                <div class="modal-body">
                    <div class="profile-summary-banner">
                        <div class="profile-avatar-lg" id="profAvatarInitials">U</div>
                        <div>
                            <div style="font-size:16px; font-weight:700; color:var(--text-main);" id="profBannerName">Người dùng</div>
                            <div style="font-size:12.5px; color:var(--text-muted); margin-top:2px;" id="profBannerMeta"></div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="profFullName">Họ và tên *</label>
                            <input type="text" id="profFullName" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="profUsername">Tên đăng nhập (Cố định)</label>
                            <input type="text" id="profUsername" class="form-control" readonly style="opacity:0.7;">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="profEmployeeCode">Mã nhân viên (MSNV) *</label>
                            <input type="text" id="profEmployeeCode" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="profDepartment">Bộ phận / Phòng ban *</label>
                            <input type="text" id="profDepartment" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="profEmail">Địa chỉ Email</label>
                            <input type="email" id="profEmail" class="form-control">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="profPhone">Số điện thoại</label>
                            <input type="text" id="profPhone" class="form-control">
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label" for="profCreatedAt">Ngày đăng ký tài khoản</label>
                            <input type="text" id="profCreatedAt" class="form-control" readonly style="opacity:0.7;">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="profCurrentPassword">Mật khẩu hiện tại (Nếu đổi MK)</label>
                            <input type="password" id="profCurrentPassword" class="form-control" placeholder="Chỉ nhập khi muốn đổi mật khẩu">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="profNewPassword">Mật khẩu mới</label>
                            <input type="password" id="profNewPassword" class="form-control" placeholder="Tối thiểu 6 ký tự">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-close-modal="profileModalBackdrop">Đóng</button>
                    <button type="submit" class="btn btn-primary">Lưu cập nhật hồ sơ</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ====================================================================
         MODAL 5: ADD / EDIT SYSTEM CONFIGURATION (ADMIN)
         ==================================================================== -->
    <div class="modal-backdrop" id="systemModalBackdrop">
        <div class="modal-dialog" style="max-width: 680px;">
            <div class="modal-header">
                <h3 class="modal-title" id="systemModalTitle">Cấu Hình Hệ Thống</h3>
                <button type="button" class="modal-close" data-close-modal="systemModalBackdrop">&times;</button>
            </div>
            <form id="systemConfigForm">
                <div class="modal-body">
                    <input type="hidden" id="sysFormId" value="0">
                    <input type="hidden" id="sysFormIcon" value="grid">
                    <input type="hidden" id="sysFormColor" value="#0284c7">
                    <input type="hidden" id="sysFormGroup" value="SERVER">
                    <input type="hidden" id="sysFormStatusLabel" value="Vận hành chính thức">
                    <input type="hidden" id="sysFormVersion" value="1.0">

                    <!-- NHÓM 1: THÔNG TIN HỆ THỐNG -->
                    <div class="form-section-block">
                        <div class="form-section-header">
                            <span class="section-badge">1</span>
                            <div>
                                <h4 class="form-section-title">Thông tin hệ thống</h4>
                                <p class="form-section-subtitle">Tên nhận diện, mô tả ngắn và biểu tượng hệ thống</p>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label class="form-label" for="sysFormName">Tên hệ thống *</label>
                                <input type="text" id="sysFormName" class="form-control" required
                                       placeholder="VD: MES DX - Quản trị Sản xuất & Chất lượng">
                            </div>

                            <div class="form-group full-width">
                                <label class="form-label" for="sysFormDesc">Mô tả ngắn (1 dòng) *</label>
                                <input type="text" id="sysFormDesc" class="form-control" required
                                       placeholder="Mô tả ngắn gọn chức năng chính của hệ thống...">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="sysFormCode">Mã hệ thống</label>
                                <input type="text" id="sysFormCode" class="form-control" placeholder="VD: SYS-MES">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="sysFormDept">Bộ phận phụ trách</label>
                                <input type="text" id="sysFormDept" class="form-control" value="Nhà máy Nhựa DX">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="sysFormAccess">Phân quyền truy cập</label>
                                <select id="sysFormAccess" class="form-control">
                                    <option value="all">Tất cả người dùng</option>
                                    <option value="user">Chỉ User đã đăng nhập</option>
                                    <option value="admin">Chỉ Quản trị viên (Admin)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="sysFormOrder">Thứ tự hiển thị</label>
                                <input type="number" id="sysFormOrder" class="form-control" value="10" min="1">
                            </div>

                            <div class="form-group full-width">
                                <label class="form-label" for="sysFormActive">Trạng thái hoạt động</label>
                                <select id="sysFormActive" class="form-control">
                                    <option value="1">Đang hoạt động</option>
                                    <option value="0">Tạm ẩn</option>
                                </select>
                            </div>

                            <div class="form-group full-width">
                                <label class="form-label">Biểu tượng hệ thống (Icon)</label>
                                <div class="icon-picker-grid" id="sysIconPicker"></div>
                            </div>
                        </div>
                    </div>

                    <!-- NHÓM 2: MÔI TRƯỜNG TRUY CẬP (SERVER & DEVELOPMENT) -->
                    <div class="form-section-block">
                        <div class="form-section-header">
                            <span class="section-badge">2</span>
                            <div>
                                <h4 class="form-section-title">Môi trường truy cập (URL)</h4>
                                <p class="form-section-subtitle">Cấu hình độc lập URL Server và URL Development (cần nhập ít nhất 1 đường dẫn)</p>
                            </div>
                        </div>

                        <div class="env-config-box">
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label class="form-label" for="sysFormUrlServer" style="display:flex; align-items:center; gap:6px;">
                                    <span class="env-pill-label pill-server">Server</span>
                                    <span>URL Server (Production)</span>
                                </label>
                                <input type="text" id="sysFormUrlServer" class="form-control"
                                       placeholder="VD: http://10.163.33.27/myweb/ (Hệ thống vận hành chính thức)">
                                <div class="field-hint">Nếu chỉ nhập URL này: Người dùng nhấn vào hệ thống sẽ truy cập trực tiếp vào Server (không hiện popup).</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="sysFormUrlDev" style="display:flex; align-items:center; gap:6px;">
                                    <span class="env-pill-label pill-dev">Dev</span>
                                    <span>URL Development (Dev/Test)</span>
                                </label>
                                <input type="text" id="sysFormUrlDev" class="form-control"
                                       placeholder="VD: http://localhost/myweb/ (Hệ thống đang phát triển / test)">
                                <div class="field-hint">Nếu chỉ nhập URL này: Người dùng nhấn vào hệ thống sẽ truy cập trực tiếp vào Development (không hiện popup).</div>
                            </div>

                            <div class="env-rule-note">
                                💡 <strong>Quy tắc Portal:</strong> Khi cấu hình <em>cả 2 URL</em>, người dùng nhấn vào thẻ sẽ hiển thị popup lựa chọn môi trường Server hoặc Development.
                            </div>
                        </div>
                    </div>

                    <!-- NHÓM 3: TÀI LIỆU HƯỚNG DẪN ĐÍNH KÈM -->
                    <div class="form-section-block" style="margin-bottom:0;">
                        <div class="form-section-header" style="justify-content:space-between;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <span class="section-badge">3</span>
                                <div>
                                    <h4 class="form-section-title">Tài liệu hướng dẫn</h4>
                                    <p class="form-section-subtitle">Đính kèm các tài liệu PDF, Word, Video hoặc Website hướng dẫn cho hệ thống này</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline" id="btnAddDocRowInSystemModal"
                                    onclick="window.PortalApp.addModalDocRow()">
                                + Thêm tài liệu
                            </button>
                        </div>

                        <div id="sysFormDocsContainer" class="modal-docs-container"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-close-modal="systemModalBackdrop">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu hệ thống</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ====================================================================
         MODAL 6: ADD / EDIT GUIDE DOCUMENT (ADMIN)
         ==================================================================== -->
    <div class="modal-backdrop" id="docModalBackdrop">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="modal-title" id="docModalTitle">Cấu Hình Tài Liệu Hướng Dẫn</h3>
                <button type="button" class="modal-close" data-close-modal="docModalBackdrop">&times;</button>
            </div>
            <form id="docConfigForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="docFormId" value="0">
                    <input type="hidden" id="docFormActive" value="1">

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label" for="docFormTitle">Tiêu đề tài liệu *</label>
                            <input type="text" id="docFormTitle" class="form-control" required
                                   placeholder="VD: Hướng dẫn vận hành hệ thống">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="docFormSystemId">Hệ thống liên kết</label>
                            <select id="docFormSystemId" class="form-control">
                                <option value="">-- Tài liệu chung --</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="docFormType">Định dạng *</label>
                            <select id="docFormType" class="form-control">
                                <option value="PDF">PDF</option>
                                <option value="WORD">Word</option>
                                <option value="VIDEO">Video</option>
                                <option value="INTERNAL">Trang hướng dẫn nội bộ</option>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label" for="docFormUrl">Đường dẫn tài liệu (URL)</label>
                            <input type="text" id="docFormUrl" class="form-control"
                                   placeholder="VD: /myweb/documents/file.pdf hoặc guide.php?topic=portal-overview">
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label" for="docFormFile">Hoặc tải file từ máy tính</label>
                            <input type="file" id="docFormFile" class="form-control"
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.mp4,.webm,.html,.txt,.png,.jpg">
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label" for="docFormDesc">Mô tả ngắn</label>
                            <input type="text" id="docFormDesc" class="form-control" placeholder="Mô tả ngắn nội dung tài liệu...">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="docFormAccess">Quyền xem tài liệu</label>
                            <select id="docFormAccess" class="form-control">
                                <option value="all">Tất cả người dùng</option>
                                <option value="user">Chỉ User đã đăng nhập</option>
                                <option value="admin">Chỉ Quản trị viên (Admin)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="docFormOrder">Thứ tự</label>
                            <input type="number" id="docFormOrder" class="form-control" value="10" min="1">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-close-modal="docModalBackdrop">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu tài liệu</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ====================================================================
         MODAL 7: ADMIN USER & ROLE MANAGEMENT MODAL
         ==================================================================== -->
    <div class="modal-backdrop" id="userModalBackdrop">
        <div class="modal-dialog" style="max-width: 520px;">
            <div class="modal-header">
                <h3 class="modal-title" id="userModalTitle">Chi Tiết & Phân Quyền Người Dùng</h3>
                <button type="button" class="modal-close" data-close-modal="userModalBackdrop">&times;</button>
            </div>
            <form id="userConfigForm">
                <div class="modal-body">
                    <input type="hidden" id="userFormId" value="0">

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label" for="userFormFullName">Họ và tên đầy đủ *</label>
                            <input type="text" id="userFormFullName" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="userFormUsername">Tên đăng nhập *</label>
                            <input type="text" id="userFormUsername" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="userFormEmpCode">Mã nhân viên (MSNV)</label>
                            <input type="text" id="userFormEmpCode" class="form-control" placeholder="VD: NV-002">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="userFormDepartment">Bộ phận / Phòng ban</label>
                            <input type="text" id="userFormDepartment" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="userFormPhone">Số điện thoại</label>
                            <input type="text" id="userFormPhone" class="form-control">
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label" for="userFormEmail">Địa chỉ Email</label>
                            <input type="email" id="userFormEmail" class="form-control">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="userFormRole">Vai trò (Phân quyền) *</label>
                            <select id="userFormRole" class="form-control">
                                <option value="user">Người dùng thông thường (User)</option>
                                <option value="admin">Quản trị viên (Admin)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="userFormActive">Trạng thái tài khoản</label>
                            <select id="userFormActive" class="form-control">
                                <option value="1">Đang hoạt động</option>
                                <option value="0">Đang bị khóa</option>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label" for="userFormPassword">Đặt mật khẩu / Cấp lại mật khẩu</label>
                            <input type="password" id="userFormPassword" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-close-modal="userModalBackdrop">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu thông tin người dùng</button>
                </div>
            </form>
        </div>
    </div>

    <!-- TOAST NOTIFICATIONS -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- MINIMAL FOOTER WITH ON-DEMAND ADMIN CONFIG TRIGGER -->
    <footer class="portal-footer">
        <span>Portal Quản lý Hệ thống • DX Plastic Group</span>
        <span id="footerAdminAuthTrigger" style="margin-left:12px; display: <?= ($isLogged && !$isAdmin) ? 'none' : 'inline-block' ?>;">
            • <a href="javascript:void(0)" onclick="window.PortalApp.toggleAdminView('admin')" style="color:var(--text-muted); text-decoration:underline;">Cấu hình Quản trị (Admin)</a>
        </span>
    </footer>

    <script src="assets/js/portal.js?v=<?= (int)$jsVersion ?>"></script>
</body>
</html>
