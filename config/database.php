<?php
/**
 * Database configuration, auto-initialization, schema migration, and RBAC helpers
 * Module: System Management Portal (PlasticDX)
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', 1);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Database connection parameters
define('PORTAL_DB_HOST', 'localhost');
define('PORTAL_DB_USER', 'root');
define('PORTAL_DB_PASS', '');
define('PORTAL_DB_NAME', 'plasticdx_db');

/**
 * Establish MySQL connection, initialize schema, and sync user session
 *
 * @return mysqli
 */
function getPortalDbConnection(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli(PORTAL_DB_HOST, PORTAL_DB_USER, PORTAL_DB_PASS);
    if ($conn->connect_error) {
        die('Database connection failed: ' . htmlspecialchars($conn->connect_error));
    }

    $conn->set_charset('utf8mb4');

    $dbName = PORTAL_DB_NAME;
    $conn->query("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->select_db($dbName);

    initializePortalSchema($conn);
    ensurePortalUsersColumns($conn);
    syncPortalUserSession($conn);

    return $conn;
}

/**
 * Ensure extended profile columns exist on portal_users without losing existing data
 *
 * @param mysqli $conn
 * @return void
 */
function ensurePortalUsersColumns(mysqli $conn): void
{
    $columns = [];
    $res = $conn->query("SHOW COLUMNS FROM `portal_users`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $columns[$row['Field']] = true;
        }
    }

    if (!isset($columns['employee_code'])) {
        $conn->query("ALTER TABLE `portal_users` ADD COLUMN `employee_code` VARCHAR(50) NOT NULL DEFAULT '' AFTER `username`");
    }
    if (!isset($columns['email'])) {
        $conn->query("ALTER TABLE `portal_users` ADD COLUMN `email` VARCHAR(120) NOT NULL DEFAULT '' AFTER `full_name`");
    }
    if (!isset($columns['phone'])) {
        $conn->query("ALTER TABLE `portal_users` ADD COLUMN `phone` VARCHAR(30) NOT NULL DEFAULT '' AFTER `email`");
    }
    if (!isset($columns['updated_at'])) {
        $conn->query("ALTER TABLE `portal_users` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`");
    }

    // Backfill empty profile fields on default accounts so no data is missing
    $conn->query("
        UPDATE `portal_users`
        SET `employee_code` = CASE WHEN `employee_code` = '' OR `employee_code` IS NULL THEN CONCAT('NV-', LPAD(`id`, 3, '0')) ELSE `employee_code` END,
            `email` = CASE WHEN `email` = '' OR `email` IS NULL THEN CONCAT(`username`, '@plasticdx.local') ELSE `email` END,
            `phone` = CASE WHEN `phone` = '' OR `phone` IS NULL THEN '0901234567' ELSE `phone` END,
            `department` = CASE WHEN `department` = '' OR `department` IS NULL THEN 'Sản xuất & QC' ELSE `department` END
    ");
    $conn->query("UPDATE `portal_users` SET `employee_code` = 'ADM-001' WHERE `username` = 'admin' AND (`employee_code` = 'NV-001' OR `employee_code` = '')");
}

/**
 * Create required tables and seed initial configuration data if empty
 *
 * @param mysqli $conn
 * @return void
 */
function initializePortalSchema(mysqli $conn): void
{
    $check = $conn->query("SHOW TABLES LIKE 'portal_systems'");
    if ($check && $check->num_rows > 0) {
        return;
    }

    // 1. Users table for Portal Registration, Authentication & RBAC
    $conn->query("
        CREATE TABLE IF NOT EXISTS `portal_users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `employee_code` VARCHAR(50) NOT NULL DEFAULT '',
            `password_hash` VARCHAR(255) NOT NULL,
            `full_name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(120) NOT NULL DEFAULT '',
            `phone` VARCHAR(30) NOT NULL DEFAULT '',
            `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
            `department` VARCHAR(100) NOT NULL DEFAULT 'Sản xuất & QC',
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 2. Systems / Modules registry table
    $conn->query("
        CREATE TABLE IF NOT EXISTS `portal_systems` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `system_code` VARCHAR(50) NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `description` TEXT NOT NULL,
            `icon` VARCHAR(60) NOT NULL DEFAULT 'grid',
            `icon_color` VARCHAR(30) NOT NULL DEFAULT '#0284c7',
            `url` VARCHAR(500) NOT NULL,
            `group_type` ENUM('SERVER', 'DEVELOPMENT') NOT NULL DEFAULT 'SERVER',
            `status_label` VARCHAR(60) NOT NULL DEFAULT 'Đang hoạt động',
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `access_role` ENUM('all', 'user', 'admin') NOT NULL DEFAULT 'all',
            `display_order` INT NOT NULL DEFAULT 10,
            `version` VARCHAR(30) DEFAULT '1.0',
            `department` VARCHAR(100) DEFAULT 'Nhà máy Nhựa DX',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_group_order` (`group_type`, `display_order`),
            INDEX `idx_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 3. Guide documents table
    $conn->query("
        CREATE TABLE IF NOT EXISTS `portal_documents` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `system_id` INT DEFAULT NULL,
            `title` VARCHAR(200) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `doc_type` ENUM('PDF', 'WORD', 'VIDEO', 'INTERNAL') NOT NULL DEFAULT 'PDF',
            `file_url` VARCHAR(500) NOT NULL,
            `access_role` ENUM('all', 'user', 'admin') NOT NULL DEFAULT 'all',
            `display_order` INT NOT NULL DEFAULT 10,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_doc_type` (`doc_type`),
            INDEX `idx_doc_order` (`display_order`),
            CONSTRAINT `fk_doc_system` FOREIGN KEY (`system_id`) REFERENCES `portal_systems`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Seed default accounts: admin / admin123 and user / user123
    $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
    $userPass  = password_hash('user123', PASSWORD_DEFAULT);

    $stmtUser = $conn->prepare("
        INSERT IGNORE INTO `portal_users`
        (`username`, `employee_code`, `password_hash`, `full_name`, `email`, `phone`, `role`, `department`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if ($stmtUser) {
        $u1 = ['admin', 'ADM-001', $adminPass, 'Quản trị viên Hệ thống', 'admin@plasticdx.local', '0901234567', 'admin', 'IT & DX Center'];
        $stmtUser->bind_param('ssssssss', $u1[0], $u1[1], $u1[2], $u1[3], $u1[4], $u1[5], $u1[6], $u1[7]);
        $stmtUser->execute();

        $u2 = ['user', 'NV-001', $userPass, 'Nhân viên Vận hành', 'user@plasticdx.local', '0908888999', 'user', 'Sản xuất & QC'];
        $stmtUser->bind_param('ssssssss', $u2[0], $u2[1], $u2[2], $u2[3], $u2[4], $u2[5], $u2[6], $u2[7]);
        $stmtUser->execute();
        $stmtUser->close();
    }

    // Seed initial systems
    $initialSystems = [
        [
            'SYS-MYWEB',
            'MyWeb - Hệ thống Quản trị Sản xuất & Nhân sự',
            'Hệ thống ERP nội bộ quản lý kế hoạch sản xuất, dữ liệu đùn ống, nhân sự (HRM), kiểm tra 5S, giám sát IoT, kho vật tư và đơn hàng.',
            'factory',
            '#0284c7',
            '/myweb/',
            'SERVER',
            'Vận hành chính thức',
            1,
            'all',
            1,
            '2.4',
            'Toàn nhà máy'
        ],
        [
            'SYS-PRINTBOX',
            'PrintBox Intranet - Quản lý & In Tem Thùng QR',
            'Quản lý chỉ thị sản xuất (CTSX), kiểm soát định mức quy cách đóng gói thùng chẵn/lẻ và in tem nhãn thùng QR Code tại xưởng.',
            'qr_code',
            '#059669',
            '/printbox/',
            'SERVER',
            'Vận hành chính thức',
            1,
            'all',
            2,
            '2.5',
            'Đóng gói & Kho'
        ],
        [
            'SYS-PRODDASH',
            'Production Dashboard - Bảng Điều Khiển Sản Xuất',
            'Màn hình theo dõi trực quan tiến độ sản xuất, hình ảnh kiểm soát chất lượng và tra cứu nhanh bản vẽ/tài liệu kỹ thuật tại chuyền.',
            'monitoring',
            '#2563eb',
            '/production_dashboard/',
            'SERVER',
            'Vận hành chính thức',
            1,
            'all',
            3,
            '1.8',
            'Sản xuất & QC'
        ],
        [
            'SYS-PLASTIC-COND',
            'Plastic Condition - Tra Cứu Điều Kiện Sản Xuất',
            'Hệ thống tra cứu thông số kỹ thuật và điều kiện vận hành máy đùn nhựa (ĐKSX) theo từng mã máy và kích thước sản phẩm.',
            'tune',
            '#7c3aed',
            '/plastic_condition/',
            'SERVER',
            'Vận hành chính thức',
            1,
            'all',
            4,
            '1.2',
            'Kỹ thuật Đùn'
        ],
        [
            'SYS-WEBBOBIN',
            'Web Bobin - Quản Lý Cuộn Bobin & Kiểm Tra QC',
            'Theo dõi vòng đời cuộn Bobin, tạo mã QR định danh, kiểm tra chất lượng (QC Check), quản lý tồn dư và báo cáo lịch sử cuộn.',
            'album',
            '#0d9488',
            '/WEB_BOBIN/',
            'SERVER',
            'Vận hành chính thức',
            1,
            'all',
            5,
            '1.5',
            'Sản xuất & QC'
        ],
        [
            'SYS-COLORMIX',
            'Tiện Ích Phối Màu & Tra Cứu Độ Nhớt',
            'Công cụ tính toán công thức phối màu hạt nhựa, tổng hợp mẻ trộn và tra cứu độ nhớt nguyên liệu truy cập nhanh tại hiện trường.',
            'palette',
            '#ea580c',
            '/myweb/index.php?mainpage=utilities&subpage=color_mixer',
            'SERVER',
            'Vận hành chính thức',
            1,
            'all',
            6,
            '2.0',
            'Pha trộn Nguyên liệu'
        ],
        [
            'DEV-GETPDF',
            'GetPDF Info - Trích Xuất Dữ Liệu Bản Vẽ PDF',
            'Công cụ tự động bóc tách thông số kỹ thuật từ bản vẽ PDF và xuất biểu mẫu nhập linh kiện tiêu chuẩn sang Excel.',
            'document_scanner',
            '#d97706',
            '/getpdf_info/',
            'DEVELOPMENT',
            'Đang thử nghiệm (UAT)',
            1,
            'all',
            1,
            '0.9-beta',
            'Kỹ thuật & R&D'
        ],
        [
            'DEV-DATA-ANALYSIS',
            'Data Analysis - Phân Tích Dữ Liệu & Chi Phí SX',
            'Hệ thống phân tích dữ liệu chi phí sản xuất, kiểm thử pipeline xử lý dữ liệu và mô hình hóa hiệu suất nhà máy.',
            'analytics',
            '#e11d48',
            '/data_analysis/',
            'DEVELOPMENT',
            'Đang phát triển',
            1,
            'all',
            2,
            '0.5-dev',
            'Ban Cải tiến DX'
        ],
        [
            'DEV-PRODDASH-V2',
            'Production Dashboard V2 - Giao Diện Thế Hệ Mới',
            'Phiên bản nâng cấp thử nghiệm của bảng điều khiển sản xuất với kho tài liệu số hóa tích hợp trực tiếp.',
            'rocket_launch',
            '#6366f1',
            '/production_dashboard1/',
            'DEVELOPMENT',
            'Đang đánh giá',
            1,
            'all',
            3,
            '2.0-rc1',
            'IT & Sản xuất'
        ]
    ];

    $stmtSys = $conn->prepare("
        INSERT INTO `portal_systems`
        (`system_code`, `name`, `description`, `icon`, `icon_color`, `url`, `group_type`, `status_label`, `is_active`, `access_role`, `display_order`, `version`, `department`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if ($stmtSys) {
        foreach ($initialSystems as $s) {
            $stmtSys->bind_param(
                'ssssssssisiss',
                $s[0], $s[1], $s[2], $s[3], $s[4], $s[5],
                $s[6], $s[7], $s[8], $s[9], $s[10], $s[11], $s[12]
            );
            $stmtSys->execute();
        }
        $stmtSys->close();
    }

    // Seed initial guide documents
    $initialDocs = [
        [
            1,
            'Hướng dẫn vận hành & Sử dụng Portal Quản lý Hệ thống',
            'Tài liệu hướng dẫn tổng quan cách truy cập các phân hệ, phân quyền người dùng và quy trình chuyển trạng thái DEVELOPMENT sang SERVER.',
            'INTERNAL',
            'guide.php?topic=portal-overview',
            'all',
            1
        ],
        [
            2,
            'Sổ tay Hướng dẫn In tem thùng QR & Thiết lập máy in nhiệt (65x30mm)',
            'Hướng dẫn chi tiết quy trình chọn chỉ thị sản xuất, kiểm soát thùng lẻ, duyệt vượt định mức và cài đặt khổ giấy máy in nhiệt.',
            'INTERNAL',
            'guide.php?topic=printbox-manual',
            'all',
            2
        ],
        [
            1,
            'HDCV Vận hành máy in đùn nhựa (PMW-00243-B)',
            'Tài liệu PDF tiêu chuẩn hướng dẫn công việc vận hành máy in trên dây chuyền đùn nhựa.',
            'PDF',
            '/myweb/documents/36. PMW-00243-B HDCV Vận hành máy in đùn nhựa.pdf',
            'all',
            3
        ],
        [
            1,
            'Quy trình thiết lập máy đùn nhựa (PMP-00264-9.3-E)',
            'Tài liệu PDF quy trình chuẩn thiết lập thông số và kiểm tra an toàn trước khi khởi động máy đùn.',
            'PDF',
            '/myweb/documents/42. PMP-00264-9.3-E Qui trình thiết lập máy đùn nhựa.pdf',
            'all',
            4
        ],
        [
            1,
            'HDCV Kiểm tra sản phẩm đùn theo Check Sheet (PMW-04304-A)',
            'Hướng dẫn công việc kiểm tra ngoại quan và kích thước sản phẩm đùn theo biểu mẫu check sheet.',
            'PDF',
            '/myweb/documents/128. PMW-04304 -A HDCV kiểm tra sản phẩm đùn theo check sheet.pdf',
            'all',
            5
        ],
        [
            5,
            'HDCV Thao tác lấy Bobin sau đùn (PMW-05703-A)',
            'Tài liệu PDF hướng dẫn thao tác thu cuộn, lấy Bobin và gắn thẻ nhận diện sau công đoạn đùn.',
            'PDF',
            '/myweb/documents/129. PMW-05703-A Hướng dẫn thao tác lấy bobin sau đùn.pdf',
            'all',
            6
        ],
        [
            7,
            'Biểu mẫu chuẩn Nhập linh kiện & Bóc tách bản vẽ',
            'File biểu mẫu cấu trúc chuẩn dùng cho công cụ bóc tách thông tin bản vẽ và nhập danh mục linh kiện.',
            'WORD',
            'guide.php?topic=word-template-guide',
            'all',
            7
        ],
        [
            1,
            'Video Đào tạo An toàn & Tiêu chuẩn 5S Nhà máy Nhựa DX',
            'Video hướng dẫn trực quan quy trình thực hành 5S, an toàn vận hành thiết bị và thao tác trên hệ thống điện tử tại xưởng.',
            'VIDEO',
            'guide.php?topic=video-training',
            'all',
            8
        ]
    ];

    $stmtDoc = $conn->prepare("
        INSERT INTO `portal_documents`
        (`system_id`, `title`, `description`, `doc_type`, `file_url`, `access_role`, `display_order`, `is_active`)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ");

    if ($stmtDoc) {
        foreach ($initialDocs as $d) {
            $stmtDoc->bind_param('isssssi', $d[0], $d[1], $d[2], $d[3], $d[4], $d[5], $d[6]);
            $stmtDoc->execute();
        }
        $stmtDoc->close();
    }
}

/**
 * Normalize user record from DB into a clean session/response array with zero null values
 *
 * @param array $row
 * @return array
 */
function formatPortalUserRecord(array $row): array
{
    $id = (int)($row['id'] ?? 0);
    $username = (string)($row['username'] ?? '');
    return [
        'id'            => $id,
        'username'      => $username,
        'employee_code' => !empty($row['employee_code']) ? (string)$row['employee_code'] : ('NV-' . str_pad((string)$id, 3, '0', STR_PAD_LEFT)),
        'full_name'     => !empty($row['full_name']) ? (string)$row['full_name'] : $username,
        'email'         => !empty($row['email']) ? (string)$row['email'] : ($username . '@plasticdx.local'),
        'phone'         => !empty($row['phone']) ? (string)$row['phone'] : 'Chưa cập nhật',
        'role'          => ($row['role'] ?? 'user') === 'admin' ? 'admin' : 'user',
        'department'    => !empty($row['department']) ? (string)$row['department'] : 'Sản xuất & QC',
        'is_active'     => isset($row['is_active']) ? (int)$row['is_active'] : 1,
        'created_at'    => !empty($row['created_at']) ? (string)$row['created_at'] : date('Y-m-d H:i:s'),
        'is_logged'     => true
    ];
}

/**
 * Synchronize active session with latest database state on every request
 *
 * @param mysqli $conn
 * @return void
 */
function syncPortalUserSession(mysqli $conn): void
{
    if (empty($_SESSION['portal_user']['id'])) {
        return;
    }

    $uid = (int)$_SESSION['portal_user']['id'];
    $stmt = $conn->prepare("
        SELECT id, username, employee_code, full_name, email, phone, role, department, is_active, created_at
        FROM portal_users
        WHERE id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row || (int)$row['is_active'] !== 1) {
        // Account was deleted or locked -> invalidate session immediately
        unset($_SESSION['portal_user']);
        return;
    }

    $_SESSION['portal_user'] = formatPortalUserRecord($row);
}

/**
 * Get current logged-in user session info (defaults to standard guest/user viewer)
 *
 * @return array
 */
function getPortalUser(): array
{
    if (isset($_SESSION['portal_user']) && is_array($_SESSION['portal_user']) && !empty($_SESSION['portal_user']['is_logged'])) {
        return $_SESSION['portal_user'];
    }
    return [
        'id'            => 0,
        'username'      => 'guest',
        'employee_code' => 'GUEST',
        'full_name'     => 'Khách truy cập',
        'email'         => '',
        'phone'         => '',
        'role'          => 'user',
        'department'    => 'Truy cập chung',
        'is_active'     => 1,
        'created_at'    => '',
        'is_logged'     => false
    ];
}

/**
 * Check if current user has Administrator privileges
 *
 * @return bool
 */
function isPortalAdmin(): bool
{
    $user = getPortalUser();
    return !empty($user['is_logged']) && ($user['role'] ?? 'user') === 'admin';
}

/**
 * Check if current user can access a resource with given access_role
 *
 * @param string $accessRole
 * @return bool
 */
function canAccessPortalItem(string $accessRole): bool
{
    if (isPortalAdmin()) {
        return true;
    }
    if ($accessRole === 'all') {
        return true;
    }
    $user = getPortalUser();
    if ($accessRole === 'user' && !empty($user['is_logged'])) {
        return true;
    }
    return false;
}

/**
 * Generate or retrieve CSRF token for session security
 *
 * @return string
 */
function getPortalCsrfToken(): string
{
    if (empty($_SESSION['portal_csrf_token'])) {
        $_SESSION['portal_csrf_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['portal_csrf_token'];
}

/**
 * Verify CSRF token from request
 *
 * @param string|null $token
 * @return bool
 */
function verifyPortalCsrfToken(?string $token): bool
{
    if (empty($_SESSION['portal_csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['portal_csrf_token'], $token);
}
