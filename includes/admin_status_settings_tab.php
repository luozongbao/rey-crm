<?php
// Settings Tab Content for Admin Status Management

// Get current system settings
$settings_query = "SELECT * FROM settings WHERE setting_name LIKE 'status_%'";
$stmt = $pdo->prepare($settings_query);
$stmt->execute();
$current_settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert to associative array for easier access
$settings = [];
foreach ($current_settings as $setting) {
    $settings[$setting['setting_name']] = $setting['value'];
}

// Default settings if not set
$default_settings = [
    'status_auto_change_enabled' => '0',
    'status_change_notifications' => '1',
    'status_history_retention_days' => '365',
    'status_required_fields' => 'notes',
    'status_workflow_enabled' => '1'
];

foreach ($default_settings as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    try {
        $settings_to_update = [
            'status_auto_change_enabled',
            'status_change_notifications', 
            'status_history_retention_days',
            'status_required_fields',
            'status_workflow_enabled'
        ];
        
        foreach ($settings_to_update as $setting_name) {
            $value = $_POST[$setting_name] ?? '0';
            
            // Check if setting exists
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_name = ?");
            $check_stmt->execute([$setting_name]);
            
            if ($check_stmt->fetchColumn() > 0) {
                // Update existing setting
                $update_stmt = $pdo->prepare("UPDATE settings SET value = ?, updated_at = NOW() WHERE setting_name = ?");
                $update_stmt->execute([$value, $setting_name]);
            } else {
                // Insert new setting
                $insert_stmt = $pdo->prepare("INSERT INTO settings (setting_name, value) VALUES (?, ?)");
                $insert_stmt->execute([$setting_name, $value]);
            }
        }
        
        $message = __('settings_updated_successfully');
        
        // Refresh settings
        $stmt = $pdo->prepare($settings_query);
        $stmt->execute();
        $current_settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $settings = [];
        foreach ($current_settings as $setting) {
            $settings[$setting['setting_name']] = $setting['value'];
        }
        
    } catch (Exception $e) {
        $error = __('settings_update_failed') . ': ' . $e->getMessage();
    }
}

// Get statistics for cleanup recommendations
$stats_query = "
    SELECT 
        COUNT(*) as total_history_records,
        MIN(changed_at) as oldest_record,
        MAX(changed_at) as newest_record,
        COUNT(DISTINCT customer_id) as customers_with_history
    FROM customer_status_history
";
$stmt = $pdo->prepare($stats_query);
$stmt->execute();
$history_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get old records count (older than retention period)
$retention_days = intval($settings['status_history_retention_days']);
$old_records_query = "
    SELECT COUNT(*) as old_records_count
    FROM customer_status_history 
    WHERE changed_at < DATE_SUB(NOW(), INTERVAL ? DAY)
";
$stmt = $pdo->prepare($old_records_query);
$stmt->execute([$retention_days]);
$old_records = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="settings-content">
    <!-- Header -->
    <div class="section-header">
        <h3><?php echo __('status_system_settings'); ?></h3>
        <div class="header-actions">
            <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#systemInfoModal">
                <i class="fas fa-info-circle"></i> <?php echo __('system_info'); ?>
            </button>
        </div>
    </div>

    <?php if (isset($message)): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" class="settings-form">
        <!-- Workflow Settings -->
        <div class="settings-section">
            <h4><?php echo __('workflow_settings'); ?></h4>
            
            <div class="setting-item">
                <div class="setting-header">
                    <label class="setting-label"><?php echo __('enable_status_workflow'); ?></label>
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="status_workflow_enabled" value="1" 
                               <?php echo $settings['status_workflow_enabled'] ? 'checked' : ''; ?>>
                    </div>
                </div>
                <p class="setting-description"><?php echo __('workflow_description'); ?></p>
            </div>
            
            <div class="setting-item">
                <div class="setting-header">
                    <label class="setting-label"><?php echo __('auto_status_changes'); ?></label>
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="status_auto_change_enabled" value="1" 
                               <?php echo $settings['status_auto_change_enabled'] ? 'checked' : ''; ?>>
                    </div>
                </div>
                <p class="setting-description"><?php echo __('auto_change_description'); ?></p>
            </div>
            
            <div class="setting-item">
                <label class="setting-label"><?php echo __('required_fields_for_status_change'); ?></label>
                <select name="status_required_fields" class="form-select">
                    <option value="none" <?php echo $settings['status_required_fields'] === 'none' ? 'selected' : ''; ?>>
                        <?php echo __('no_required_fields'); ?>
                    </option>
                    <option value="notes" <?php echo $settings['status_required_fields'] === 'notes' ? 'selected' : ''; ?>>
                        <?php echo __('notes_required'); ?>
                    </option>
                    <option value="notes_reason" <?php echo $settings['status_required_fields'] === 'notes_reason' ? 'selected' : ''; ?>>
                        <?php echo __('notes_and_reason_required'); ?>
                    </option>
                </select>
                <p class="setting-description"><?php echo __('required_fields_description'); ?></p>
            </div>
        </div>

        <!-- Notification Settings -->
        <div class="settings-section">
            <h4><?php echo __('notification_settings'); ?></h4>
            
            <div class="setting-item">
                <div class="setting-header">
                    <label class="setting-label"><?php echo __('status_change_notifications'); ?></label>
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="status_change_notifications" value="1" 
                               <?php echo $settings['status_change_notifications'] ? 'checked' : ''; ?>>
                    </div>
                </div>
                <p class="setting-description"><?php echo __('notifications_description'); ?></p>
            </div>
        </div>

        <!-- Data Management -->
        <div class="settings-section">
            <h4><?php echo __('data_management'); ?></h4>
            
            <div class="setting-item">
                <label class="setting-label"><?php echo __('history_retention_period'); ?></label>
                <div class="input-group">
                    <input type="number" class="form-control" name="status_history_retention_days" 
                           value="<?php echo intval($settings['status_history_retention_days']); ?>" min="30" max="3650">
                    <span class="input-group-text"><?php echo __('days'); ?></span>
                </div>
                <p class="setting-description"><?php echo __('retention_description'); ?></p>
            </div>
        </div>

        <!-- Save Button -->
        <div class="settings-actions">
            <button type="submit" name="update_settings" class="btn btn-primary">
                <i class="fas fa-save"></i> <?php echo __('save_settings'); ?>
            </button>
        </div>
    </form>

    <!-- Data Cleanup Section -->
    <div class="settings-section">
        <h4><?php echo __('data_cleanup'); ?></h4>
        
        <div class="cleanup-info">
            <div class="cleanup-stats">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($history_stats['total_history_records']); ?></div>
                    <div class="stat-label"><?php echo __('total_history_records'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($history_stats['customers_with_history']); ?></div>
                    <div class="stat-label"><?php echo __('customers_with_history'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($old_records['old_records_count']); ?></div>
                    <div class="stat-label"><?php echo __('old_records'); ?></div>
                </div>
            </div>
            
            <?php if ($old_records['old_records_count'] > 0): ?>
                <div class="cleanup-recommendation">
                    <div class="alert alert-warning">
                        <h6><?php echo __('cleanup_recommendation'); ?></h6>
                        <p><?php echo __('cleanup_recommendation_text', ['count' => $old_records['old_records_count'], 'days' => $retention_days]); ?></p>
                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="confirmCleanup()">
                            <i class="fas fa-broom"></i> <?php echo __('cleanup_old_records'); ?>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Backup Section -->
    <div class="settings-section">
        <h4><?php echo __('backup_restore'); ?></h4>
        
        <div class="backup-actions">
            <div class="action-card">
                <h6><?php echo __('export_status_data'); ?></h6>
                <p><?php echo __('export_description'); ?></p>
                <button type="button" class="btn btn-outline-primary" onclick="exportStatusData()">
                    <i class="fas fa-download"></i> <?php echo __('export_data'); ?>
                </button>
            </div>
            
            <div class="action-card">
                <h6><?php echo __('import_status_translations'); ?></h6>
                <p><?php echo __('import_description'); ?></p>
                <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#importModal">
                    <i class="fas fa-upload"></i> <?php echo __('import_translations'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- System Info Modal -->
<div class="modal fade" id="systemInfoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('system_information'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="info-grid">
                    <div class="info-item">
                        <strong><?php echo __('total_statuses'); ?>:</strong>
                        <span><?php echo count($statuses); ?></span>
                    </div>
                    <div class="info-item">
                        <strong><?php echo __('active_statuses'); ?>:</strong>
                        <span><?php echo count(array_filter($statuses, function($s) { return $s['is_active']; })); ?></span>
                    </div>
                    <div class="info-item">
                        <strong><?php echo __('available_languages'); ?>:</strong>
                        <span>
                            <?php 
                            $languages = [];
                            foreach ($translations as $trans) {
                                if (!in_array($trans['locale'], $languages)) {
                                    $languages[] = $trans['locale'];
                                }
                            }
                            echo implode(', ', $languages);
                            ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <strong><?php echo __('oldest_history_record'); ?>:</strong>
                        <span><?php echo $history_stats['oldest_record'] ? date('Y-m-d H:i:s', strtotime($history_stats['oldest_record'])) : __('none'); ?></span>
                    </div>
                    <div class="info-item">
                        <strong><?php echo __('newest_history_record'); ?>:</strong>
                        <span><?php echo $history_stats['newest_record'] ? date('Y-m-d H:i:s', strtotime($history_stats['newest_record'])) : __('none'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('import_translations'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="importForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="import_file"><?php echo __('select_csv_file'); ?></label>
                        <input type="file" class="form-control" name="import_file" accept=".csv" required>
                        <small class="form-text text-muted"><?php echo __('csv_format_description'); ?></small>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="overwrite_existing">
                            <label class="form-check-label"><?php echo __('overwrite_existing_translations'); ?></label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                <button type="button" class="btn btn-success" onclick="importTranslations()"><?php echo __('import'); ?></button>
            </div>
        </div>
    </div>
</div>

<style>
.settings-form {
    max-width: 800px;
}

.settings-section {
    background: white;
    padding: 25px;
    margin-bottom: 25px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
}

.settings-section h4 {
    color: #495057;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f8f9fa;
}

.setting-item {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f8f9fa;
}

.setting-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.setting-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.setting-label {
    font-weight: 600;
    color: #495057;
    margin: 0;
}

.setting-description {
    color: #6c757d;
    font-size: 14px;
    margin: 0;
}

.form-check-input:checked {
    background-color: #28a745;
    border-color: #28a745;
}

.settings-actions {
    text-align: center;
    margin-top: 30px;
}

.cleanup-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: #007bff;
    display: block;
}

.stat-label {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
    margin-top: 5px;
}

.cleanup-recommendation {
    margin-top: 20px;
}

.backup-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.action-card {
    padding: 20px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: #f8f9fa;
}

.action-card h6 {
    margin-bottom: 10px;
    color: #495057;
}

.action-card p {
    color: #6c757d;
    font-size: 14px;
    margin-bottom: 15px;
}

.info-grid {
    display: grid;
    gap: 15px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #f8f9fa;
}

.alert h6 {
    margin-bottom: 10px;
}

@media (max-width: 768px) {
    .setting-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .cleanup-stats {
        grid-template-columns: 1fr;
    }
    
    .backup-actions {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function confirmCleanup() {
    if (confirm('<?php echo __("confirm_cleanup_message"); ?>')) {
        // Implement cleanup functionality
        alert('Cleanup functionality would be implemented here');
    }
}

function exportStatusData() {
    // Implement export functionality
    alert('Export functionality would be implemented here');
}

function importTranslations() {
    const form = document.getElementById('importForm');
    const formData = new FormData(form);
    
    // Implement import functionality
    alert('Import functionality would be implemented here');
}

// Auto-save warning
document.querySelectorAll('input, select').forEach(element => {
    element.addEventListener('change', function() {
        // Show unsaved changes indicator
        const saveButton = document.querySelector('button[name="update_settings"]');
        if (saveButton && !saveButton.classList.contains('btn-warning')) {
            saveButton.classList.remove('btn-primary');
            saveButton.classList.add('btn-warning');
            saveButton.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <?php echo __("unsaved_changes"); ?>';
        }
    });
});
</script>
