<?php
/**
 * Backend API Controller for System Management Portal (PlasticDX)
 * Handles Registration, Login, User Profile, RBAC User Management, Systems & Documents
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/config/database.php';

$conn = getPortalDbConnection();

/**
 * Send standardized JSON response and terminate execution
 *
 * @param bool $success
 * @param string $message
 * @param array $extra
 * @param int $httpCode
 * @return void
 */
function sendJsonResponse(bool $success, string $message, array $extra = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Require authenticated user session and valid CSRF token
 *
 * @return array Current user
 */
function requireLoggedUserAccess(): array
{
    $user = getPortalUser();
    if (empty($user['is_logged']) || empty($user['id'])) {
        sendJsonResponse(false, 'Vui lòng đăng nhập để thực hiện chức năng này.', ['require_login' => true], 401);
    }
    $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyPortalCsrfToken($csrf)) {
        sendJsonResponse(false, 'Phiên làm việc không hợp lệ hoặc đã hết hạn. Vui lòng tải lại trang.', [], 403);
    }
    return $user;
}

/**
 * Require administrator privileges and valid CSRF token for write operations
 *
 * @return array Current admin user
 */
function requireAdminWriteAccess(): array
{
    if (!isPortalAdmin()) {
        sendJsonResponse(
            false,
            'Chức năng này yêu cầu quyền Quản trị viên (Admin). Vui lòng đăng nhập tài khoản Admin.',
            ['require_admin' => true],
            403
        );
    }
    $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyPortalCsrfToken($csrf)) {
        sendJsonResponse(false, 'Phiên làm việc không hợp lệ hoặc đã hết hạn. Vui lòng tải lại trang.', [], 403);
    }
    return getPortalUser();
}

// Parse JSON body if request is application/json
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $rawBody = file_get_contents('php://input');
    $jsonBody = json_decode($rawBody, true);
    if (is_array($jsonBody)) {
        $_POST = array_merge($_POST, $jsonBody);
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_portal_data';

switch ($action) {
    // =========================================================================
    // 1. ACCOUNT REGISTRATION (Quy trình Đăng ký tài khoản)
    // =========================================================================
    case 'register':
        $fullName        = trim($_POST['full_name'] ?? '');
        $username        = trim($_POST['username'] ?? '');
        $employeeCode    = strtoupper(trim($_POST['employee_code'] ?? ''));
        $email           = trim($_POST['email'] ?? '');
        $phone           = trim($_POST['phone'] ?? '');
        $department      = trim($_POST['department'] ?? 'Sản xuất & QC');
        $password        = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        // Input validation
        if (mb_strlen($fullName) < 2) {
            sendJsonResponse(false, 'Vui lòng nhập Họ và tên hợp lệ (tối thiểu 2 ký tự).');
        }

        if (!preg_match('/^[a-zA-Z0-9_.]{3,30}$/', $username)) {
            sendJsonResponse(false, 'Tên đăng nhập phải từ 3–30 ký tự, chỉ gồm chữ cái không dấu, số, dấu chấm hoặc gạch dưới.');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendJsonResponse(false, 'Địa chỉ Email không đúng định dạng.');
        }

        if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $phone)) {
            sendJsonResponse(false, 'Số điện thoại chỉ được chứa chữ số và dài từ 6–20 ký tự.');
        }

        if (strlen($password) < 6) {
            sendJsonResponse(false, 'Mật khẩu phải có độ dài tối thiểu 6 ký tự.');
        }

        if ($password !== $passwordConfirm) {
            sendJsonResponse(false, 'Mật khẩu xác nhận không khớp.');
        }

        // Check duplicate username
        $chkStmt = $conn->prepare("SELECT id FROM portal_users WHERE username = ? LIMIT 1");
        $chkStmt->bind_param('s', $username);
        $chkStmt->execute();
        $chkRes = $chkStmt->get_result();
        if ($chkRes && $chkRes->num_rows > 0) {
            $chkStmt->close();
            sendJsonResponse(false, 'Tên đăng nhập "' . $username . '" đã tồn tại. Vui lòng chọn tên khác.');
        }
        $chkStmt->close();

        // Fill defaults so no field is ever empty
        if ($email === '') {
            $email = strtolower($username) . '@plasticdx.local';
        }
        if ($phone === '') {
            $phone = 'Chưa cập nhật';
        }
        if ($department === '') {
            $department = 'Sản xuất & QC';
        }

        $passHash = password_hash($password, PASSWORD_DEFAULT);
        $role = 'user';
        $isActive = 1;

        $insStmt = $conn->prepare("
            INSERT INTO portal_users
            (username, employee_code, password_hash, full_name, email, phone, role, department, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insStmt->bind_param(
            'ssssssssi',
            $username, $employeeCode, $passHash, $fullName, $email, $phone, $role, $department, $isActive
        );
        $ok = $insStmt->execute();
        $newUserId = (int)$insStmt->insert_id;
        $insStmt->close();

        if (!$ok || $newUserId <= 0) {
            sendJsonResponse(false, 'Không thể tạo tài khoản: ' . $conn->error);
        }

        // Auto-generate employee_code if user left it blank
        if ($employeeCode === '') {
            $employeeCode = 'NV-' . str_pad((string)$newUserId, 3, '0', STR_PAD_LEFT);
            $updCode = $conn->prepare("UPDATE portal_users SET employee_code = ? WHERE id = ?");
            $updCode->bind_param('si', $employeeCode, $newUserId);
            $updCode->execute();
            $updCode->close();
        }

        // Fetch full saved user record from DB and log user in automatically
        $fetchStmt = $conn->prepare("
            SELECT id, username, employee_code, full_name, email, phone, role, department, is_active, created_at
            FROM portal_users
            WHERE id = ?
            LIMIT 1
        ");
        $fetchStmt->bind_param('i', $newUserId);
        $fetchStmt->execute();
        $savedRow = $fetchStmt->get_result()->fetch_assoc();
        $fetchStmt->close();

        $_SESSION['portal_user'] = formatPortalUserRecord($savedRow);

        sendJsonResponse(true, 'Đăng ký tài khoản "' . $fullName . '" thành công! Hệ thống đã tự động đăng nhập.', [
            'user'       => $_SESSION['portal_user'],
            'is_admin'   => false,
            'csrf_token' => getPortalCsrfToken()
        ]);
        break;

    // =========================================================================
    // 2. AUTHENTICATION (Login / Logout)
    // =========================================================================
    case 'login':
        $identifier   = trim($_POST['username'] ?? '');
        $password     = $_POST['password'] ?? '';
        $requireAdmin = !empty($_POST['require_admin']) && (int)$_POST['require_admin'] === 1;

        if ($identifier === '' || $password === '') {
            sendJsonResponse(false, 'Vui lòng nhập đầy đủ Tên đăng nhập (hoặc Mã NV) và Mật khẩu.');
        }

        $stmt = $conn->prepare("
            SELECT id, username, employee_code, password_hash, full_name, email, phone, role, department, is_active, created_at
            FROM portal_users
            WHERE username = ? OR employee_code = ? OR email = ?
            LIMIT 1
        ");
        $stmt->bind_param('sss', $identifier, $identifier, $identifier);
        $stmt->execute();
        $res = $stmt->get_result();
        $userRow = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$userRow || !password_verify($password, $userRow['password_hash'])) {
            sendJsonResponse(false, 'Thông tin đăng nhập hoặc mật khẩu không chính xác.');
        }

        if ((int)$userRow['is_active'] !== 1) {
            sendJsonResponse(false, 'Tài khoản này hiện đang bị khóa. Vui lòng liên hệ Quản trị viên.');
        }

        if ($requireAdmin && $userRow['role'] !== 'admin') {
            sendJsonResponse(false, 'Tài khoản "' . $userRow['username'] . '" là Người dùng thông thường, không có quyền Quản trị viên (Admin).');
        }

        $_SESSION['portal_user'] = formatPortalUserRecord($userRow);
        $isAdmin = ($_SESSION['portal_user']['role'] === 'admin');

        sendJsonResponse(
            true,
            'Đăng nhập thành công! Xin chào ' . $_SESSION['portal_user']['full_name'] . '.',
            [
                'user'       => $_SESSION['portal_user'],
                'is_admin'   => $isAdmin,
                'csrf_token' => getPortalCsrfToken()
            ]
        );
        break;

    case 'logout':
        unset($_SESSION['portal_user']);
        sendJsonResponse(true, 'Đã đăng xuất khỏi tài khoản.', [
            'user'       => getPortalUser(),
            'is_admin'   => false,
            'csrf_token' => getPortalCsrfToken()
        ]);
        break;

    // =========================================================================
    // 3. USER PROFILE UPDATE (Cập nhật Hồ sơ cá nhân & Đổi mật khẩu)
    // =========================================================================
    case 'update_profile':
        $currentUser = requireLoggedUserAccess();
        $userId      = (int)$currentUser['id'];

        $fullName        = trim($_POST['full_name'] ?? '');
        $employeeCode    = strtoupper(trim($_POST['employee_code'] ?? ''));
        $email           = trim($_POST['email'] ?? '');
        $phone           = trim($_POST['phone'] ?? '');
        $department      = trim($_POST['department'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';

        if (mb_strlen($fullName) < 2) {
            sendJsonResponse(false, 'Họ và tên phải có ít nhất 2 ký tự.');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendJsonResponse(false, 'Địa chỉ Email không hợp lệ.');
        }

        if ($phone !== '' && $phone !== 'Chưa cập nhật' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $phone)) {
            sendJsonResponse(false, 'Số điện thoại không hợp lệ.');
        }

        if ($employeeCode === '') {
            $employeeCode = 'NV-' . str_pad((string)$userId, 3, '0', STR_PAD_LEFT);
        }
        if ($email === '') {
            $email = $currentUser['username'] . '@plasticdx.local';
        }
        if ($phone === '') {
            $phone = 'Chưa cập nhật';
        }
        if ($department === '') {
            $department = 'Sản xuất & QC';
        }

        // If user wants to change password, verify current password first
        if ($newPassword !== '') {
            if (strlen($newPassword) < 6) {
                sendJsonResponse(false, 'Mật khẩu mới phải có tối thiểu 6 ký tự.');
            }
            $pwStmt = $conn->prepare("SELECT password_hash FROM portal_users WHERE id = ? LIMIT 1");
            $pwStmt->bind_param('i', $userId);
            $pwStmt->execute();
            $pwRow = $pwStmt->get_result()->fetch_assoc();
            $pwStmt->close();

            if (!$pwRow || !password_verify($currentPassword, $pwRow['password_hash'])) {
                sendJsonResponse(false, 'Mật khẩu hiện tại không chính xác.');
            }

            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $upd = $conn->prepare("
                UPDATE portal_users
                SET full_name = ?, employee_code = ?, email = ?, phone = ?, department = ?, password_hash = ?
                WHERE id = ?
            ");
            $upd->bind_param('ssssssi', $fullName, $employeeCode, $email, $phone, $department, $newHash, $userId);
        } else {
            $upd = $conn->prepare("
                UPDATE portal_users
                SET full_name = ?, employee_code = ?, email = ?, phone = ?, department = ?
                WHERE id = ?
            ");
            $upd->bind_param('sssssi', $fullName, $employeeCode, $email, $phone, $department, $userId);
        }

        $ok = $upd->execute();
        $upd->close();

        if (!$ok) {
            sendJsonResponse(false, 'Không thể cập nhật hồ sơ: ' . $conn->error);
        }

        syncPortalUserSession($conn);

        sendJsonResponse(true, 'Đã cập nhật thông tin hồ sơ cá nhân thành công.', [
            'user' => getPortalUser()
        ]);
        break;

    // =========================================================================
    // 4. FETCH PORTAL DATA (Systems, Documents, Stats, and Users for Admin)
    // =========================================================================
    case 'get_portal_data':
        $isAdmin = isPortalAdmin();
        $currentUser = getPortalUser();

        // Fetch systems ordered by display_order
        $systems = [];
        $sysSql = "
            SELECT id, system_code, name, description, icon, icon_color, url,
                   group_type, status_label, is_active, access_role, display_order,
                   version, department, updated_at
            FROM portal_systems
            ORDER BY display_order ASC, id ASC
        ";
        $sysResult = $conn->query($sysSql);
        if ($sysResult) {
            while ($row = $sysResult->fetch_assoc()) {
                $row['id']            = (int)$row['id'];
                $row['system_code']   = !empty($row['system_code']) ? (string)$row['system_code'] : ('SYS-' . $row['id']);
                $row['name']          = (string)($row['name'] ?? '');
                $row['description']   = !empty($row['description']) ? (string)$row['description'] : 'Hệ thống thành phần trong Portal.';
                $row['icon']          = !empty($row['icon']) ? (string)$row['icon'] : 'grid';
                $row['icon_color']    = !empty($row['icon_color']) ? (string)$row['icon_color'] : '#0284c7';
                $row['url']           = (string)($row['url'] ?? '');
                $row['group_type']    = ($row['group_type'] ?? 'SERVER') === 'DEVELOPMENT' ? 'DEVELOPMENT' : 'SERVER';
                $row['status_label']  = !empty($row['status_label']) ? (string)$row['status_label'] : ($row['group_type'] === 'SERVER' ? 'Vận hành chính thức' : 'Đang phát triển');
                $row['is_active']     = (int)$row['is_active'];
                $row['access_role']   = !empty($row['access_role']) ? (string)$row['access_role'] : 'all';
                $row['display_order'] = (int)$row['display_order'];
                $row['version']       = !empty($row['version']) ? (string)$row['version'] : '1.0';
                $row['department']    = !empty($row['department']) ? (string)$row['department'] : 'Nhà máy Nhựa DX';

                // Parse separate Server and Development URLs
                $rawUrl = (string)($row['url'] ?? '');
                $urlServer = '';
                $urlDev = '';
                if (strpos($rawUrl, '|') !== false) {
                    $parts = explode('|', $rawUrl, 2);
                    $urlServer = trim($parts[0] ?? '');
                    $urlDev = trim($parts[1] ?? '');
                } else {
                    if (($row['group_type'] ?? '') === 'DEVELOPMENT') {
                        $urlDev = trim($rawUrl);
                    } else {
                        $urlServer = trim($rawUrl);
                    }
                }
                $row['url_server'] = $urlServer;
                $row['url_dev'] = $urlDev;

                if (!$isAdmin) {
                    if ($row['is_active'] !== 1) {
                        continue;
                    }
                    if (!canAccessPortalItem($row['access_role'])) {
                        continue;
                    }
                }
                $systems[] = $row;
            }
        }

        // Fetch guide documents
        $documents = [];
        $docSql = "
            SELECT d.id, d.system_id, d.title, d.description, d.doc_type, d.file_url,
                   d.access_role, d.display_order, d.is_active, d.updated_at,
                   s.name AS system_name, s.system_code
            FROM portal_documents d
            LEFT JOIN portal_systems s ON d.system_id = s.id
            ORDER BY d.display_order ASC, d.id ASC
        ";
        $docResult = $conn->query($docSql);
        if ($docResult) {
            while ($row = $docResult->fetch_assoc()) {
                $row['id']            = (int)$row['id'];
                $row['system_id']     = $row['system_id'] !== null ? (int)$row['system_id'] : null;
                $row['title']         = (string)($row['title'] ?? '');
                $row['description']   = !empty($row['description']) ? (string)$row['description'] : 'Tài liệu hướng dẫn vận hành chuẩn.';
                $row['doc_type']      = !empty($row['doc_type']) ? (string)$row['doc_type'] : 'PDF';
                $row['file_url']      = (string)($row['file_url'] ?? '');
                $row['access_role']   = !empty($row['access_role']) ? (string)$row['access_role'] : 'all';
                $row['is_active']     = (int)$row['is_active'];
                $row['display_order'] = (int)$row['display_order'];
                $row['system_name']   = !empty($row['system_name']) ? (string)$row['system_name'] : 'Tài liệu chung Portal';
                $row['system_code']   = !empty($row['system_code']) ? (string)$row['system_code'] : 'PORTAL';

                if (!$isAdmin) {
                    if ($row['is_active'] !== 1) {
                        continue;
                    }
                    if (!canAccessPortalItem($row['access_role'])) {
                        continue;
                    }
                }
                $documents[] = $row;
            }
        }

        // Fetch users list ONLY for Admin
        $users = [];
        if ($isAdmin) {
            $userSql = "
                SELECT id, username, employee_code, full_name, email, phone, role, department, is_active, created_at
                FROM portal_users
                ORDER BY role ASC, id ASC
            ";
            $userRes = $conn->query($userSql);
            if ($userRes) {
                while ($uRow = $userRes->fetch_assoc()) {
                    $users[] = formatPortalUserRecord($uRow);
                }
            }
        }

        // Calculate summary statistics
        $totalSystems = count($systems);
        $serverSystems = 0;
        $devSystems = 0;
        foreach ($systems as $sys) {
            if ($sys['group_type'] === 'SERVER') {
                $serverSystems++;
            } else {
                $devSystems++;
            }
        }

        sendJsonResponse(true, 'Tải dữ liệu thành công.', [
            'user'       => $currentUser,
            'is_admin'   => $isAdmin,
            'csrf_token' => getPortalCsrfToken(),
            'stats'      => [
                'total_systems'   => $totalSystems,
                'server_systems'  => $serverSystems,
                'dev_systems'     => $devSystems,
                'total_documents' => count($documents),
                'total_users'     => count($users)
            ],
            'systems'    => $systems,
            'documents'  => $documents,
            'users'      => $users
        ]);
        break;

    // =========================================================================
    // 5. ADMIN USER MANAGEMENT & RBAC (Quản lý Người dùng & Phân quyền)
    // =========================================================================
    case 'save_user':
        $currentAdmin = requireAdminWriteAccess();

        $id           = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $username     = trim($_POST['username'] ?? '');
        $employeeCode = strtoupper(trim($_POST['employee_code'] ?? ''));
        $fullName     = trim($_POST['full_name'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $department   = trim($_POST['department'] ?? 'Sản xuất & QC');
        $role         = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $isActive     = isset($_POST['is_active']) ? ((int)$_POST['is_active'] === 1 ? 1 : 0) : 1;
        $password     = $_POST['password'] ?? '';

        if (mb_strlen($fullName) < 2) {
            sendJsonResponse(false, 'Vui lòng nhập Họ và tên hợp lệ.');
        }

        if (!preg_match('/^[a-zA-Z0-9_.]{3,30}$/', $username)) {
            sendJsonResponse(false, 'Tên đăng nhập phải từ 3–30 ký tự (chữ cái, số, dấu chấm hoặc gạch dưới).');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendJsonResponse(false, 'Địa chỉ Email không hợp lệ.');
        }

        if ($email === '') {
            $email = strtolower($username) . '@plasticdx.local';
        }
        if ($phone === '') {
            $phone = 'Chưa cập nhật';
        }
        if ($department === '') {
            $department = 'Sản xuất & QC';
        }

        // Prevent current admin from demoting or locking themselves
        if ($id === (int)$currentAdmin['id']) {
            if ($role !== 'admin') {
                sendJsonResponse(false, 'Bạn không thể tự hạ quyền Quản trị viên của chính mình.');
            }
            if ($isActive !== 1) {
                sendJsonResponse(false, 'Bạn không thể tự khóa tài khoản đang đăng nhập.');
            }
        }

        // Check duplicate username
        $dupStmt = $conn->prepare("SELECT id FROM portal_users WHERE username = ? AND id != ? LIMIT 1");
        $dupStmt->bind_param('si', $username, $id);
        $dupStmt->execute();
        if ($dupStmt->get_result()->num_rows > 0) {
            $dupStmt->close();
            sendJsonResponse(false, 'Tên đăng nhập "' . $username . '" đã được sử dụng bởi tài khoản khác.');
        }
        $dupStmt->close();

        if ($id > 0) {
            if ($employeeCode === '') {
                $employeeCode = 'NV-' . str_pad((string)$id, 3, '0', STR_PAD_LEFT);
            }
            if ($password !== '') {
                if (strlen($password) < 6) {
                    sendJsonResponse(false, 'Mật khẩu mới phải từ 6 ký tự trở lên.');
                }
                $passHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    UPDATE portal_users
                    SET username = ?, employee_code = ?, full_name = ?, email = ?, phone = ?,
                        department = ?, role = ?, is_active = ?, password_hash = ?
                    WHERE id = ?
                ");
                $stmt->bind_param('sssssssisi', $username, $employeeCode, $fullName, $email, $phone, $department, $role, $isActive, $passHash, $id);
            } else {
                $stmt = $conn->prepare("
                    UPDATE portal_users
                    SET username = ?, employee_code = ?, full_name = ?, email = ?, phone = ?,
                        department = ?, role = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->bind_param('sssssssii', $username, $employeeCode, $fullName, $email, $phone, $department, $role, $isActive, $id);
            }
            $ok = $stmt->execute();
            $stmt->close();

            if (!$ok) {
                sendJsonResponse(false, 'Không thể cập nhật người dùng: ' . $conn->error);
            }
            sendJsonResponse(true, 'Đã cập nhật thông tin và phân quyền cho "' . $fullName . '".');
        } else {
            if (strlen($password) < 6) {
                sendJsonResponse(false, 'Vui lòng nhập mật khẩu tối thiểu 6 ký tự cho tài khoản mới.');
            }
            $passHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                INSERT INTO portal_users
                (username, employee_code, password_hash, full_name, email, phone, role, department, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('ssssssssi', $username, $employeeCode, $passHash, $fullName, $email, $phone, $role, $department, $isActive);
            $ok = $stmt->execute();
            $newId = (int)$stmt->insert_id;
            $stmt->close();

            if (!$ok) {
                sendJsonResponse(false, 'Không thể tạo người dùng mới: ' . $conn->error);
            }

            if ($employeeCode === '') {
                $employeeCode = ($role === 'admin' ? 'ADM-' : 'NV-') . str_pad((string)$newId, 3, '0', STR_PAD_LEFT);
                $conn->query("UPDATE portal_users SET employee_code = '" . $conn->real_escape_string($employeeCode) . "' WHERE id = {$newId}");
            }

            sendJsonResponse(true, 'Đã thêm tài khoản "' . $fullName . '" thành công.');
        }
        break;

    case 'toggle_user_role':
        $currentAdmin = requireAdminWriteAccess();
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id <= 0) {
            sendJsonResponse(false, 'ID người dùng không hợp lệ.');
        }
        if ($id === (int)$currentAdmin['id']) {
            sendJsonResponse(false, 'Bạn không thể tự thay đổi quyền của tài khoản đang đăng nhập.');
        }

        $stmt = $conn->prepare("SELECT id, full_name, role FROM portal_users WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $uRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$uRow) {
            sendJsonResponse(false, 'Không tìm thấy người dùng.');
        }

        $newRole = ($uRow['role'] === 'admin') ? 'user' : 'admin';
        $upd = $conn->prepare("UPDATE portal_users SET role = ? WHERE id = ?");
        $upd->bind_param('si', $newRole, $id);
        $upd->execute();
        $upd->close();

        sendJsonResponse(
            true,
            'Đã chuyển quyền của "' . $uRow['full_name'] . '" thành ' . ($newRole === 'admin' ? 'Quản trị viên (Admin)' : 'Người dùng (User)') . '.',
            ['new_role' => $newRole]
        );
        break;

    case 'toggle_user_active':
        $currentAdmin = requireAdminWriteAccess();
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id <= 0) {
            sendJsonResponse(false, 'ID người dùng không hợp lệ.');
        }
        if ($id === (int)$currentAdmin['id']) {
            sendJsonResponse(false, 'Bạn không thể tự khóa tài khoản của chính mình.');
        }

        $stmt = $conn->prepare("SELECT id, full_name, is_active FROM portal_users WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $uRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$uRow) {
            sendJsonResponse(false, 'Không tìm thấy người dùng.');
        }

        $newActive = ((int)$uRow['is_active'] === 1) ? 0 : 1;
        $upd = $conn->prepare("UPDATE portal_users SET is_active = ? WHERE id = ?");
        $upd->bind_param('ii', $newActive, $id);
        $upd->execute();
        $upd->close();

        sendJsonResponse(
            true,
            $newActive ? 'Đã mở khóa tài khoản "' . $uRow['full_name'] . '".' : 'Đã khóa tài khoản "' . $uRow['full_name'] . '".',
            ['is_active' => $newActive]
        );
        break;

    case 'delete_user':
        $currentAdmin = requireAdminWriteAccess();
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id <= 0) {
            sendJsonResponse(false, 'ID người dùng không hợp lệ.');
        }
        if ($id === (int)$currentAdmin['id']) {
            sendJsonResponse(false, 'Bạn không thể xóa tài khoản đang đăng nhập.');
        }

        $stmt = $conn->prepare("DELETE FROM portal_users WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            sendJsonResponse(false, 'Không thể xóa người dùng này.');
        }
        sendJsonResponse(true, 'Đã xóa tài khoản người dùng.');
        break;

    // =========================================================================
    // 6. SAVE SYSTEM (Create / Update System Configuration)
    // =========================================================================
    case 'save_system':
        requireAdminWriteAccess();

        $id           = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $systemCode   = strtoupper(trim($_POST['system_code'] ?? ''));
        $name         = trim($_POST['name'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $icon         = trim($_POST['icon'] ?? 'grid');
        $iconColor    = trim($_POST['icon_color'] ?? '#0284c7');
        $urlServer    = trim($_POST['url_server'] ?? '');
        $urlDev       = trim($_POST['url_dev'] ?? '');
        $rawUrl       = trim($_POST['url'] ?? '');
        $isActive     = isset($_POST['is_active']) ? ((int)$_POST['is_active'] === 1 ? 1 : 0) : 1;
        $accessRole   = trim($_POST['access_role'] ?? 'all');
        $displayOrder = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 10;
        $version      = trim($_POST['version'] ?? '1.0');
        $department   = trim($_POST['department'] ?? 'Nhà máy Nhựa DX');

        // Resolve composite or independent URLs
        if ($urlServer !== '' || $urlDev !== '') {
            if ($urlServer !== '' && $urlDev !== '') {
                $url = $urlServer . '|' . $urlDev;
                $groupType = !empty($_POST['group_type']) ? strtoupper(trim($_POST['group_type'])) : 'SERVER';
            } elseif ($urlServer !== '') {
                $url = $urlServer;
                $groupType = 'SERVER';
            } else {
                $url = '|' . $urlDev;
                $groupType = 'DEVELOPMENT';
            }
        } else {
            $url = $rawUrl;
            $groupType = strtoupper(trim($_POST['group_type'] ?? 'SERVER'));
        }

        if ($name === '') {
            sendJsonResponse(false, 'Vui lòng nhập Tên hệ thống.');
        }

        if ($url === '' || $url === '|') {
            sendJsonResponse(false, 'Vui lòng nhập ít nhất một đường dẫn truy cập (Server hoặc Development).');
        }

        if ($description === '') {
            $description = 'Hệ thống thành phần được quản lý trên Portal.';
        }

        if ($systemCode === '') {
            $systemCode = 'SYS-' . strtoupper(substr(md5($name . microtime()), 0, 5));
        }

        if (!in_array($groupType, ['SERVER', 'DEVELOPMENT'], true)) {
            $groupType = 'SERVER';
        }

        if (!in_array($accessRole, ['all', 'user', 'admin'], true)) {
            $accessRole = 'all';
        }

        $statusLabel = trim($_POST['status_label'] ?? '');
        if ($statusLabel === '') {
            $statusLabel = ($groupType === 'SERVER') ? 'Vận hành chính thức' : 'Đang phát triển';
        }

        $targetSystemId = 0;
        if ($id > 0) {
            $stmt = $conn->prepare("
                UPDATE portal_systems
                SET system_code = ?, name = ?, description = ?, icon = ?, icon_color = ?,
                    url = ?, group_type = ?, status_label = ?, is_active = ?, access_role = ?,
                    display_order = ?, version = ?, department = ?
                WHERE id = ?
            ");
            $stmt->bind_param(
                'ssssssssisissi',
                $systemCode, $name, $description, $icon, $iconColor,
                $url, $groupType, $statusLabel, $isActive, $accessRole,
                $displayOrder, $version, $department, $id
            );
            $ok = $stmt->execute();
            $stmt->close();

            if (!$ok) {
                sendJsonResponse(false, 'Không thể cập nhật hệ thống: ' . $conn->error);
            }
            $targetSystemId = $id;
        } else {
            $stmt = $conn->prepare("
                INSERT INTO portal_systems
                (system_code, name, description, icon, icon_color, url, group_type, status_label, is_active, access_role, display_order, version, department)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                'ssssssssisiss',
                $systemCode, $name, $description, $icon, $iconColor,
                $url, $groupType, $statusLabel, $isActive, $accessRole,
                $displayOrder, $version, $department
            );
            $ok = $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();

            if (!$ok) {
                sendJsonResponse(false, 'Không thể thêm hệ thống mới: ' . $conn->error);
            }
            $targetSystemId = (int)$newId;
        }

        // Process attached documents from System Modal
        if ($targetSystemId > 0 && isset($_POST['documents_json'])) {
            $docsList = json_decode($_POST['documents_json'], true);
            if (is_array($docsList)) {
                $delDocs = $conn->prepare("DELETE FROM portal_documents WHERE system_id = ?");
                $delDocs->bind_param('i', $targetSystemId);
                $delDocs->execute();
                $delDocs->close();

                $insDoc = $conn->prepare("
                    INSERT INTO portal_documents (system_id, title, description, doc_type, file_url, access_role, display_order, is_active)
                    VALUES (?, ?, ?, ?, ?, 'all', ?, 1)
                ");
                $docOrder = 10;
                foreach ($docsList as $dItem) {
                    $dTitle = trim($dItem['title'] ?? '');
                    $dUrl   = trim($dItem['file_url'] ?? $dItem['url'] ?? '');
                    $dType  = strtoupper(trim($dItem['doc_type'] ?? 'PDF'));
                    if (!in_array($dType, ['PDF', 'WORD', 'VIDEO', 'INTERNAL', 'WEBSITE'], true)) {
                        $dType = 'PDF';
                    }
                    $dDesc = trim($dItem['description'] ?? 'Tài liệu hướng dẫn hệ thống.');
                    if ($dTitle !== '' && $dUrl !== '') {
                        $insDoc->bind_param('issssi', $targetSystemId, $dTitle, $dDesc, $dType, $dUrl, $docOrder);
                        $insDoc->execute();
                        $docOrder += 10;
                    }
                }
                $insDoc->close();
            }
        }

        $successMsg = ($id > 0)
            ? 'Đã cập nhật thông tin hệ thống "' . $name . '" thành công.'
            : 'Đã thêm hệ thống mới "' . $name . '" thành công.';

        sendJsonResponse(true, $successMsg, ['id' => $targetSystemId]);
        break;

    // =========================================================================
    // 7. TOGGLE SYSTEM GROUP (DEVELOPMENT <-> SERVER)
    // =========================================================================
    case 'toggle_system_group':
        requireAdminWriteAccess();

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            sendJsonResponse(false, 'ID hệ thống không hợp lệ.');
        }

        $stmt = $conn->prepare("SELECT id, name, group_type, status_label FROM portal_systems WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $sys = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$sys) {
            sendJsonResponse(false, 'Không tìm thấy hệ thống cần chuyển đổi.');
        }

        $newGroup = ($sys['group_type'] === 'SERVER') ? 'DEVELOPMENT' : 'SERVER';
        $newStatusLabel = ($newGroup === 'SERVER') ? 'Vận hành chính thức' : 'Đang phát triển';

        $upd = $conn->prepare("UPDATE portal_systems SET group_type = ?, status_label = ? WHERE id = ?");
        $upd->bind_param('ssi', $newGroup, $newStatusLabel, $id);
        $ok = $upd->execute();
        $upd->close();

        if (!$ok) {
            sendJsonResponse(false, 'Lỗi khi chuyển trạng thái nhóm hệ thống.');
        }

        $msg = ($newGroup === 'SERVER')
            ? 'Đã chuyển "' . $sys['name'] . '" sang nhóm SERVER (Vận hành chính thức).'
            : 'Đã chuyển "' . $sys['name'] . '" sang nhóm DEVELOPMENT (Đang phát triển).';

        sendJsonResponse(true, $msg, ['new_group' => $newGroup, 'new_status_label' => $newStatusLabel]);
        break;

    // =========================================================================
    // 8. TOGGLE SYSTEM ACTIVE STATE & DELETE SYSTEM
    // =========================================================================
    case 'toggle_system_active':
        requireAdminWriteAccess();

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            sendJsonResponse(false, 'ID hệ thống không hợp lệ.');
        }

        $stmt = $conn->prepare("SELECT id, name, is_active FROM portal_systems WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $sys = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$sys) {
            sendJsonResponse(false, 'Không tìm thấy hệ thống.');
        }

        $newActive = ((int)$sys['is_active'] === 1) ? 0 : 1;
        $upd = $conn->prepare("UPDATE portal_systems SET is_active = ? WHERE id = ?");
        $upd->bind_param('ii', $newActive, $id);
        $upd->execute();
        $upd->close();

        sendJsonResponse(
            true,
            $newActive ? 'Đã kích hoạt hiển thị hệ thống "' . $sys['name'] . '".' : 'Đã tạm ẩn hệ thống "' . $sys['name'] . '".',
            ['is_active' => $newActive]
        );
        break;

    case 'delete_system':
        requireAdminWriteAccess();

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            sendJsonResponse(false, 'ID hệ thống không hợp lệ.');
        }

        $stmt = $conn->prepare("DELETE FROM portal_systems WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            sendJsonResponse(false, 'Không thể xóa hệ thống này.');
        }
        sendJsonResponse(true, 'Đã xóa hệ thống khỏi danh sách Portal.');
        break;

    // =========================================================================
    // 9. SAVE & DELETE GUIDE DOCUMENT
    // =========================================================================
    case 'save_document':
        requireAdminWriteAccess();

        $id           = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $systemId     = !empty($_POST['system_id']) ? (int)$_POST['system_id'] : null;
        $title        = trim($_POST['title'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $docType      = strtoupper(trim($_POST['doc_type'] ?? 'PDF'));
        $fileUrl      = trim($_POST['file_url'] ?? '');
        $accessRole   = trim($_POST['access_role'] ?? 'all');
        $displayOrder = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 10;
        $isActive     = isset($_POST['is_active']) ? ((int)$_POST['is_active'] === 1 ? 1 : 0) : 1;

        if ($title === '') {
            sendJsonResponse(false, 'Tiêu đề tài liệu hướng dẫn không được để trống.');
        }

        if ($description === '') {
            $description = 'Tài liệu hướng dẫn vận hành chuẩn.';
        }

        if (!in_array($docType, ['PDF', 'WORD', 'VIDEO', 'INTERNAL', 'WEBSITE'], true)) {
            $docType = 'PDF';
        }

        if (!in_array($accessRole, ['all', 'user', 'admin'], true)) {
            $accessRole = 'all';
        }

        // Handle optional file upload
        if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/docs/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            $origName = basename($_FILES['doc_file']['name']);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'mp4', 'webm', 'html', 'txt', 'png', 'jpg'];

            if (!in_array($ext, $allowedExts, true)) {
                sendJsonResponse(false, 'Định dạng file tải lên không được hỗ trợ (' . htmlspecialchars($ext) . ').');
            }

            $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($origName, PATHINFO_FILENAME));
            $targetFileName = date('Ymd_His') . '_' . $safeName . '.' . $ext;
            $targetPath = $uploadDir . $targetFileName;

            if (!move_uploaded_file($_FILES['doc_file']['tmp_name'], $targetPath)) {
                sendJsonResponse(false, 'Không thể lưu file tải lên máy chủ.');
            }

            $fileUrl = 'uploads/docs/' . $targetFileName;
        }

        if ($fileUrl === '') {
            sendJsonResponse(false, 'Vui lòng nhập đường dẫn tài liệu (URL) hoặc tải file lên từ máy tính.');
        }

        if ($id > 0) {
            $stmt = $conn->prepare("
                UPDATE portal_documents
                SET system_id = ?, title = ?, description = ?, doc_type = ?, file_url = ?,
                    access_role = ?, display_order = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->bind_param(
                'isssssiii',
                $systemId, $title, $description, $docType, $fileUrl,
                $accessRole, $displayOrder, $isActive, $id
            );
            $ok = $stmt->execute();
            $stmt->close();

            if (!$ok) {
                sendJsonResponse(false, 'Không thể cập nhật tài liệu: ' . $conn->error);
            }
            sendJsonResponse(true, 'Đã cập nhật tài liệu "' . $title . '" thành công.');
        } else {
            $stmt = $conn->prepare("
                INSERT INTO portal_documents
                (system_id, title, description, doc_type, file_url, access_role, display_order, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                'isssssii',
                $systemId, $title, $description, $docType, $fileUrl,
                $accessRole, $displayOrder, $isActive
            );
            $ok = $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();

            if (!$ok) {
                sendJsonResponse(false, 'Không thể thêm tài liệu mới: ' . $conn->error);
            }
            sendJsonResponse(true, 'Đã thêm tài liệu hướng dẫn "' . $title . '" thành công.', ['id' => $newId]);
        }
        break;

    case 'delete_document':
        requireAdminWriteAccess();

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            sendJsonResponse(false, 'ID tài liệu không hợp lệ.');
        }

        $stmt = $conn->prepare("DELETE FROM portal_documents WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            sendJsonResponse(false, 'Không thể xóa tài liệu này.');
        }
        sendJsonResponse(true, 'Đã xóa tài liệu hướng dẫn.');
        break;

    default:
        sendJsonResponse(false, 'Hành động API không hợp lệ.', [], 400);
}
