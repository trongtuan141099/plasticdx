<?php
/**
 * Internal Guide & Documentation Reader Page
 * Module: System Management Portal (PlasticDX) - Minimal UI Edition
 */

require_once __DIR__ . '/config/database.php';

$conn = getPortalDbConnection();
$topic = isset($_GET['topic']) ? trim($_GET['topic']) : 'portal-overview';

$guides = [
    'portal-overview' => [
        'title'    => 'Hướng dẫn vận hành & Sử dụng Portal Quản lý Hệ thống',
        'badge'    => 'HƯỚNG DẪN NỘI BỘ',
        'updated'  => '21/10/2026',
        'dept'     => 'Ban Chuyển Đổi Số (DX Center)',
        'summary'  => 'Hướng dẫn truy cập nhanh hệ thống trong 2 bước (Chọn hệ thống → Chọn môi trường Server/Development) và tra cứu tài liệu trực tiếp trên từng thẻ.',
        'sections' => [
            [
                'heading' => '1. Truy cập hệ thống nhanh trong 2 thao tác',
                'content' => '<ul style="padding-left:20px; margin-top:8px;">
                    <li><strong>Bước 1 - Chọn hệ thống:</strong> Nhấn trực tiếp vào thẻ (Card) hệ thống cần làm việc trên màn hình chính.</li>
                    <li><strong>Bước 2 - Chọn môi trường:</strong> Chọn <strong>Server (Production)</strong> để làm việc trên hệ thống chính thức hoặc <strong>Development (Dev/Test)</strong> để kiểm thử.</li>
                </ul>'
            ],
            [
                'heading' => '2. Mở tài liệu hướng dẫn trực tiếp từ từng Card',
                'content' => '<ul style="padding-left:20px; margin-top:8px;">
                    <li>Trên mỗi thẻ hệ thống đều tích hợp sẵn nút <strong>Tài liệu</strong>.</li>
                    <li>Nhấn vào nút <strong>Tài liệu</strong> để xem danh sách các hướng dẫn PDF, Word, Video và Trang hướng dẫn nội bộ được gắn trực tiếp với hệ thống đó mà không cần tìm kiếm ở khu vực khác.</li>
                </ul>'
            ]
        ]
    ],
    'printbox-manual' => [
        'title'    => 'Sổ tay Hướng dẫn In tem thùng QR & Thiết lập máy in nhiệt (65x30mm)',
        'badge'    => 'HƯỚNG DẪN PRINTBOX',
        'updated'  => '21/10/2026',
        'dept'     => 'Bộ phận Đóng gói & Kho thành phẩm',
        'summary'  => 'Quy trình chuẩn thao tác in tem thùng sản xuất (CTSX) trên hệ thống PrintBox Intranet và thông số chuẩn cho máy in nhiệt 65x30mm.',
        'sections' => [
            [
                'heading' => '1. Thiết lập thông số máy in nhiệt 65mm × 30mm',
                'content' => '<ul style="padding-left:20px; margin-top:8px;">
                    <li><strong>Paper Size (Khổ giấy):</strong> <code>65 mm × 30 mm</code>.</li>
                    <li><strong>Margins (Căn lề):</strong> Chọn <code>None</code> (Không lề).</li>
                    <li><strong>Scale (Tỷ lệ):</strong> <code>Default</code> hoặc <code>100%</code>.</li>
                    <li><strong>Headers and footers:</strong> Bỏ tích chọn.</li>
                </ul>'
            ],
            [
                'heading' => '2. Quy trình chọn Chỉ thị sản xuất (CTSX) & In tem',
                'content' => '<ul style="padding-left:20px; margin-top:8px;">
                    <li>Nhấn vào thẻ <strong>PrintBox Intranet</strong> trên Portal và chọn môi trường <strong>Server (Production)</strong>.</li>
                    <li>Tìm kiếm mã sản phẩm hoặc mã lệnh CTSX, nhấn <strong>In tem</strong> và nhập sản lượng thực tế.</li>
                </ul>'
            ]
        ]
    ],
    'word-template-guide' => [
        'title'    => 'Quy chuẩn Biểu mẫu Word / Excel Nhập linh kiện & Bóc tách Bản vẽ',
        'badge'    => 'TÀI LIỆU BIỂU MẪU',
        'updated'  => '21/10/2026',
        'dept'     => 'Phòng Kỹ thuật & R&D',
        'summary'  => 'Hướng dẫn cấu trúc cột dữ liệu và quy cách đặt tên file khi sử dụng công cụ bóc tách thông số kỹ thuật từ bản vẽ.',
        'sections' => [
            [
                'heading' => '1. Cấu trúc thông tin bắt buộc trong Biểu mẫu',
                'content' => '<ul style="padding-left:20px; margin-top:8px;">
                    <li><strong>Mã linh kiện / Mã sản phẩm (Part Number):</strong> Viết hoa, không chứa khoảng trắng thừa.</li>
                    <li><strong>Kích thước danh định & Dung sai:</strong> Ghi rõ đơn vị <code>mm</code>.</li>
                </ul>'
            ],
            [
                'heading' => '2. Liên kết trực tiếp đến File mẫu',
                'content' => 'Tải trực tiếp biểu mẫu chuẩn tại: <a href="/getpdf_info/mau_nhap_linh_kien.xlsx" style="color:var(--primary);font-weight:600;">Tải file mẫu nhập linh kiện (.xlsx)</a>.'
            ]
        ]
    ],
    'video-training' => [
        'title'    => 'Đào tạo Trực quan: An toàn Vận hành, Tiêu chuẩn 5S & Chuyển đổi số Nhà máy',
        'badge'    => 'VIDEO ĐÀO TẠO',
        'updated'  => '21/10/2026',
        'dept'     => 'Ban An toàn & 5S Nhà máy Nhựa DX',
        'summary'  => 'Tài liệu đào tạo trực quan kết hợp quy trình 5S, kiểm tra thiết bị đầu ca và ghi nhận dữ liệu lên hệ thống điện tử.',
        'sections' => [
            [
                'heading' => '1. Nội dung chương trình đào tạo trực quan',
                'content' => '<ul style="padding-left:20px; margin-top:8px;">
                    <li><strong>Bước 1:</strong> Kiểm tra 5S & An toàn đầu ca.</li>
                    <li><strong>Bước 2:</strong> Tra cứu Điều kiện Sản xuất (ĐKSX) trên thẻ <em>Plastic Condition</em>.</li>
                    <li><strong>Bước 3:</strong> Ghi nhận sản lượng trên <em>MyWeb</em>, quản lý cuộn trên <em>Web Bobin</em> và in tem qua <em>PrintBox</em>.</li>
                </ul>'
            ],
            [
                'heading' => '2. Tài liệu PDF đính kèm',
                'content' => '<ul style="padding-left:20px; margin-top:8px;">
                    <li><a href="/myweb/documents/PE-PM-T-EQ-0654 Tiêu chuẩn 5S cho trang phục.pdf" target="_blank" style="color:var(--primary);font-weight:600;">Mở PDF: Tiêu chuẩn 5S cho trang phục (PE-PM-T-EQ-0654)</a></li>
                    <li><a href="/myweb/documents/PD-PM-T-ER-0817 Đào tạo vận hành máy nghiền, lọc, 5S, KYT .pdf" target="_blank" style="color:var(--primary);font-weight:600;">Mở PDF: Đào tạo vận hành máy nghiền, lọc, 5S, KYT (PD-PM-T-ER-0817)</a></li>
                </ul>'
            ]
        ]
    ]
];

$currentGuide = $guides[$topic] ?? $guides['portal-overview'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($currentGuide['title']) ?> - Portal Hệ thống</title>
    <link rel="stylesheet" href="assets/css/portal.css">
    <script>
        (function () {
            var savedTheme = localStorage.getItem('plasticdx_theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>
<body>
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
                    <div class="brand-subtitle">Tài liệu hướng dẫn nội bộ</div>
                </div>
            </a>
            <div class="header-actions">
                <a href="index.php" class="btn btn-outline btn-sm">
                    ← Quay lại Portal
                </a>
            </div>
        </div>
    </header>

    <main class="portal-container" style="max-width: 880px;">
        <div class="segmented-filters" style="margin-bottom:20px; flex-wrap:wrap;">
            <?php foreach ($guides as $key => $g): ?>
                <a href="guide.php?topic=<?= urlencode($key) ?>"
                   class="filter-pill <?= $key === $topic ? 'active' : '' ?>"
                   style="text-decoration:none;">
                    <?= htmlspecialchars($g['badge']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <article class="admin-panel-card" style="padding: 32px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
                <span class="doc-type-badge"><?= htmlspecialchars($currentGuide['badge']) ?></span>
                <span style="font-size:12.5px; color:var(--text-muted);">
                    <?= htmlspecialchars($currentGuide['dept']) ?> • Cập nhật: <?= htmlspecialchars($currentGuide['updated']) ?>
                </span>
            </div>

            <h1 style="font-size:22px; font-weight:700; color:var(--text-main); margin-bottom:12px;">
                <?= htmlspecialchars($currentGuide['title']) ?>
            </h1>

            <p style="font-size:14px; color:var(--text-secondary); background:var(--bg-subtle); padding:14px 16px; border-radius:10px; margin-bottom:24px;">
                <?= htmlspecialchars($currentGuide['summary']) ?>
            </p>

            <?php foreach ($currentGuide['sections'] as $sec): ?>
                <section style="margin-bottom:20px;">
                    <h2 style="font-size:16px; font-weight:700; color:var(--text-main); margin-bottom:8px;">
                        <?= htmlspecialchars($sec['heading']) ?>
                    </h2>
                    <div style="font-size:14px; color:var(--text-secondary); line-height:1.7;">
                        <?= $sec['content'] ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </article>
    </main>
</body>
</html>
