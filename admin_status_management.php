<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/customer_status_functions.php';

// Ensure user is logged in and is admin
requireLogin();
requireAdmin();

$page_title = __('status_management');
$current_page = 'admin_status_management';

// Get current tab
$active_tab = $_GET['tab'] ?? 'status_list';
$valid_tabs = ['status_list', 'translations', 'analytics', 'settings'];
if (!in_array($active_tab, $valid_tabs)) {
    $active_tab = 'status_list';
}

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_status':
                    $status_key = trim($_POST['status_key']);
                    $sort_order = intval($_POST['sort_order']);
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    // Add status
                    $stmt = $pdo->prepare("INSERT INTO customer_statuses (status_key, sort_order, is_active) VALUES (?, ?, ?)");
                    $stmt->execute([$status_key, $sort_order, $is_active]);
                    $new_status_id = $pdo->lastInsertId();
                    
                    // Add default English translation
                    $english_name = trim($_POST['english_name']);
                    $english_desc = trim($_POST['english_description']);
                    if (!empty($english_name)) {
                        $stmt = $pdo->prepare("INSERT INTO customer_status_translations (status_id, locale, name, description) VALUES (?, 'en', ?, ?)");
                        $stmt->execute([$new_status_id, $english_name, $english_desc]);
                    }
                    
                    // Add Chinese translation if provided
                    $chinese_name = trim($_POST['chinese_name']);
                    $chinese_desc = trim($_POST['chinese_description']);
                    if (!empty($chinese_name)) {
                        $stmt = $pdo->prepare("INSERT INTO customer_status_translations (status_id, locale, name, description) VALUES (?, 'zh-cn', ?, ?)");
                        $stmt->execute([$new_status_id, $chinese_name, $chinese_desc]);
                    }
                    
                    $message = __('status_added_successfully');
                    break;
                    
                case 'update_status':
                    $status_id = intval($_POST['status_id']);
                    $sort_order = intval($_POST['sort_order']);
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    $stmt = $pdo->prepare("UPDATE customer_statuses SET sort_order = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$sort_order, $is_active, $status_id]);
                    
                    $message = __('status_updated_successfully');
                    break;
                    
                case 'update_translation':
                    $translation_id = intval($_POST['translation_id']);
                    $name = trim($_POST['name']);
                    $description = trim($_POST['description']);
                    
                    $stmt = $pdo->prepare("UPDATE customer_status_translations SET name = ?, description = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $translation_id]);
                    
                    $message = __('translation_updated_successfully');
                    break;
                    
                case 'add_translation':
                    $status_id = intval($_POST['status_id']);
                    $locale = trim($_POST['locale']);
                    $name = trim($_POST['name']);
                    $description = trim($_POST['description']);
                    
                    $stmt = $pdo->prepare("INSERT INTO customer_status_translations (status_id, locale, name, description) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$status_id, $locale, $name, $description]);
                    
                    $message = __('translation_added_successfully');
                    break;
            }
        }
    } catch (Exception $e) {
        $error = __('operation_failed') . ': ' . $e->getMessage();
    }
}

// Get all statuses with translations
$statuses = getAllCustomerStatusesWithTranslations();

// Get status analytics
$status_stats = getCustomerStatusCounts();

include 'includes/header.php';
?>

<div class="container">
    <div class="header">
        <h1 class="page-title"><?php echo __('status_management'); ?></h1>
        <div class="header-actions">
            <a href="admin_customer_management.php" class="btn btn-secondary"><?php echo __('back_to_admin'); ?></a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <div class="tab-navigation">
        <nav class="nav nav-tabs">
            <a class="nav-link <?php echo $active_tab === 'status_list' ? 'active' : ''; ?>" 
               href="?tab=status_list"><?php echo __('status_list'); ?></a>
            <a class="nav-link <?php echo $active_tab === 'translations' ? 'active' : ''; ?>" 
               href="?tab=translations"><?php echo __('translations'); ?></a>
            <a class="nav-link <?php echo $active_tab === 'analytics' ? 'active' : ''; ?>" 
               href="?tab=analytics"><?php echo __('analytics'); ?></a>
            <a class="nav-link <?php echo $active_tab === 'settings' ? 'active' : ''; ?>" 
               href="?tab=settings"><?php echo __('settings'); ?></a>
        </nav>
    </div>

    <!-- Tab Content -->
    <div class="tab-content">
        <?php if ($active_tab === 'status_list'): ?>
            <?php include 'includes/admin_status_list_tab.php'; ?>
        <?php elseif ($active_tab === 'translations'): ?>
            <?php include 'includes/admin_status_translations_tab.php'; ?>
        <?php elseif ($active_tab === 'analytics'): ?>
            <?php include 'includes/admin_status_analytics_tab.php'; ?>
        <?php elseif ($active_tab === 'settings'): ?>
            <?php include 'includes/admin_status_settings_tab.php'; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.tab-navigation {
    margin-bottom: 30px;
    border-bottom: 1px solid #dee2e6;
}

.nav-tabs {
    border-bottom: none;
    display: flex;
    gap: 0;
}

.nav-link {
    display: block;
    padding: 12px 20px;
    text-decoration: none;
    color: #495057;
    border: 1px solid transparent;
    border-bottom: none;
    background: #f8f9fa;
    transition: all 0.2s;
}

.nav-link:hover {
    color: #007bff;
    background: #e9ecef;
}

.nav-link.active {
    color: #007bff;
    background: white;
    border-color: #dee2e6;
    border-bottom-color: white;
    position: relative;
    z-index: 1;
}

.tab-content {
    min-height: 500px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 0 6px 6px 6px;
    padding: 30px;
}

.alert {
    padding: 12px 20px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 4px;
}

.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}
</style>

<?php include 'includes/footer.php'; ?>
