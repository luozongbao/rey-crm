<?php
// Translations Tab Content for Admin Status Management

// Get selected status filter
$selected_status = $_GET['status'] ?? '';
$selected_locale = $_GET['locale'] ?? '';

// Get all translations with status info
$translations_query = "
    SELECT 
        cst.id as translation_id,
        cst.status_id,
        cst.locale,
        cst.name,
        cst.description,
        cs.status_key,
        cs.sort_order,
        cs.is_active
    FROM customer_status_translations cst
    JOIN customer_statuses cs ON cst.status_id = cs.id
";

$where_conditions = [];
$params = [];

if (!empty($selected_status)) {
    $where_conditions[] = "cs.status_key = ?";
    $params[] = $selected_status;
}

if (!empty($selected_locale)) {
    $where_conditions[] = "cst.locale = ?";
    $params[] = $selected_locale;
}

if (!empty($where_conditions)) {
    $translations_query .= " WHERE " . implode(" AND ", $where_conditions);
}

$translations_query .= " ORDER BY cs.sort_order, cst.locale";

$stmt = $pdo->prepare($translations_query);
$stmt->execute($params);
$translations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available locales
$locales = [
    'en' => 'English',
    'zh-cn' => '中文 (简体)',
    'zh-tw' => '中文 (繁體)',
    'es' => 'Español',
    'fr' => 'Français',
    'de' => 'Deutsch',
    'ja' => '日本語',
    'ko' => '한국어'
];

// Get statuses without certain translations
$missing_translations = [];
foreach ($statuses as $status) {
    foreach ($locales as $locale_code => $locale_name) {
        $has_translation = false;
        foreach ($translations as $translation) {
            if ($translation['status_id'] == $status['id'] && $translation['locale'] == $locale_code) {
                $has_translation = true;
                break;
            }
        }
        if (!$has_translation) {
            $missing_translations[] = [
                'status_id' => $status['id'],
                'status_key' => $status['status_key'],
                'locale' => $locale_code,
                'locale_name' => $locale_name
            ];
        }
    }
}
?>

<div class="translations-content">
    <!-- Header with Filters and Add Button -->
    <div class="section-header">
        <h3><?php echo __('status_translations'); ?></h3>
        <div class="header-actions">
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addTranslationModal">
                <i class="fas fa-plus"></i> <?php echo __('add_translation'); ?>
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
        <form method="GET" class="filters-form">
            <input type="hidden" name="tab" value="translations">
            <div class="row">
                <div class="col-md-3">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value=""><?php echo __('all_statuses'); ?></option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo $status['status_key']; ?>" 
                                    <?php echo $selected_status === $status['status_key'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status['status_key']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="locale" class="form-select" onchange="this.form.submit()">
                        <option value=""><?php echo __('all_languages'); ?></option>
                        <?php foreach ($locales as $code => $name): ?>
                            <option value="<?php echo $code; ?>" 
                                    <?php echo $selected_locale === $code ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='?tab=translations'">
                        <?php echo __('clear_filters'); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Missing Translations Alert -->
    <?php if (!empty($missing_translations) && empty($selected_status) && empty($selected_locale)): ?>
        <div class="alert alert-warning">
            <h6><?php echo __('missing_translations'); ?></h6>
            <p><?php echo count($missing_translations); ?> <?php echo __('translations_missing'); ?></p>
            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#missingTranslationsModal">
                <?php echo __('view_missing'); ?>
            </button>
        </div>
    <?php endif; ?>

    <!-- Translations Table -->
    <div class="translations-table-section">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th><?php echo __('status'); ?></th>
                        <th><?php echo __('language'); ?></th>
                        <th><?php echo __('name'); ?></th>
                        <th><?php echo __('description'); ?></th>
                        <th><?php echo __('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($translations)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                <?php echo __('no_translations_found'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($translations as $translation): ?>
                            <tr>
                                <td>
                                    <code><?php echo htmlspecialchars($translation['status_key']); ?></code>
                                    <span class="badge badge-<?php echo $translation['is_active'] ? 'success' : 'secondary'; ?> ms-2">
                                        <?php echo $translation['is_active'] ? __('active') : __('inactive'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="language-info">
                                        <span class="locale-code"><?php echo $translation['locale']; ?></span>
                                        <span class="locale-name"><?php echo $locales[$translation['locale']] ?? $translation['locale']; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($translation['name']); ?></strong>
                                </td>
                                <td>
                                    <?php if ($translation['description']): ?>
                                        <span class="description-text"><?php echo htmlspecialchars($translation['description']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted"><?php echo __('no_description'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-translation" 
                                                data-translation-id="<?php echo $translation['translation_id']; ?>"
                                                data-status-key="<?php echo htmlspecialchars($translation['status_key']); ?>"
                                                data-locale="<?php echo $translation['locale']; ?>"
                                                data-name="<?php echo htmlspecialchars($translation['name']); ?>"
                                                data-description="<?php echo htmlspecialchars($translation['description']); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-translation" 
                                                data-translation-id="<?php echo $translation['translation_id']; ?>"
                                                data-status-key="<?php echo htmlspecialchars($translation['status_key']); ?>"
                                                data-locale="<?php echo $translation['locale']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Translation Coverage Summary -->
    <div class="coverage-summary">
        <h4><?php echo __('translation_coverage'); ?></h4>
        <div class="coverage-grid">
            <?php foreach ($statuses as $status): ?>
                <div class="coverage-item">
                    <div class="coverage-header">
                        <code><?php echo $status['status_key']; ?></code>
                        <span class="badge badge-<?php echo $status['is_active'] ? 'success' : 'secondary'; ?>">
                            <?php echo $status['is_active'] ? __('active') : __('inactive'); ?>
                        </span>
                    </div>
                    <div class="coverage-languages">
                        <?php foreach ($locales as $locale_code => $locale_name): ?>
                            <?php 
                            $has_translation = false;
                            foreach ($translations as $translation) {
                                if ($translation['status_id'] == $status['id'] && $translation['locale'] == $locale_code) {
                                    $has_translation = true;
                                    break;
                                }
                            }
                            ?>
                            <span class="language-badge <?php echo $has_translation ? 'has-translation' : 'missing-translation'; ?>"
                                  title="<?php echo $locale_name; ?>">
                                <?php echo $locale_code; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Add Translation Modal -->
<div class="modal fade" id="addTranslationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('add_translation'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_translation">
                    
                    <div class="form-group">
                        <label for="add_status_id"><?php echo __('status'); ?> *</label>
                        <select name="status_id" class="form-select" required>
                            <option value=""><?php echo __('select_status'); ?></option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo $status['id']; ?>">
                                    <?php echo htmlspecialchars($status['status_key']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="add_locale"><?php echo __('language'); ?> *</label>
                        <select name="locale" class="form-select" required>
                            <option value=""><?php echo __('select_language'); ?></option>
                            <?php foreach ($locales as $code => $name): ?>
                                <option value="<?php echo $code; ?>"><?php echo htmlspecialchars($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="add_name"><?php echo __('name'); ?> *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="add_description"><?php echo __('description'); ?></label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                    <button type="submit" class="btn btn-success"><?php echo __('add_translation'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Translation Modal -->
<div class="modal fade" id="editTranslationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('edit_translation'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_translation">
                    <input type="hidden" name="translation_id" id="edit_translation_id">
                    
                    <div class="form-group">
                        <label><?php echo __('status'); ?></label>
                        <input type="text" class="form-control" id="edit_status_key" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo __('language'); ?></label>
                        <input type="text" class="form-control" id="edit_locale_display" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_name"><?php echo __('name'); ?> *</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_description"><?php echo __('description'); ?></label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo __('update_translation'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Missing Translations Modal -->
<div class="modal fade" id="missingTranslationsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('missing_translations'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('language'); ?></th>
                                <th><?php echo __('action'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($missing_translations as $missing): ?>
                                <tr>
                                    <td><code><?php echo $missing['status_key']; ?></code></td>
                                    <td><?php echo $missing['locale_name']; ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-success add-missing-translation"
                                                data-status-id="<?php echo $missing['status_id']; ?>"
                                                data-status-key="<?php echo $missing['status_key']; ?>"
                                                data-locale="<?php echo $missing['locale']; ?>"
                                                data-locale-name="<?php echo $missing['locale_name']; ?>">
                                            <i class="fas fa-plus"></i> <?php echo __('add'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.filters-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.language-info {
    display: flex;
    flex-direction: column;
}

.locale-code {
    font-family: monospace;
    font-weight: 600;
    color: #495057;
}

.locale-name {
    font-size: 12px;
    color: #6c757d;
}

.description-text {
    max-width: 200px;
    display: inline-block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.coverage-summary {
    margin-top: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.coverage-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.coverage-item {
    background: white;
    padding: 15px;
    border-radius: 6px;
    border: 1px solid #dee2e6;
}

.coverage-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.coverage-languages {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.language-badge {
    display: inline-block;
    padding: 3px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.language-badge.has-translation {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.language-badge.missing-translation {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.badge-success {
    background-color: #28a745;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
}

.badge-secondary {
    background-color: #6c757d;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit translation functionality
    document.querySelectorAll('.edit-translation').forEach(button => {
        button.addEventListener('click', function() {
            const translationId = this.dataset.translationId;
            const statusKey = this.dataset.statusKey;
            const locale = this.dataset.locale;
            const name = this.dataset.name;
            const description = this.dataset.description;
            
            document.getElementById('edit_translation_id').value = translationId;
            document.getElementById('edit_status_key').value = statusKey;
            document.getElementById('edit_locale_display').value = '<?php echo json_encode($locales); ?>'[locale] || locale;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_description').value = description;
            
            const modal = new bootstrap.Modal(document.getElementById('editTranslationModal'));
            modal.show();
        });
    });
    
    // Add missing translation functionality
    document.querySelectorAll('.add-missing-translation').forEach(button => {
        button.addEventListener('click', function() {
            const statusId = this.dataset.statusId;
            const statusKey = this.dataset.statusKey;
            const locale = this.dataset.locale;
            const localeName = this.dataset.localeName;
            
            // Pre-fill the add translation modal
            document.querySelector('#addTranslationModal select[name="status_id"]').value = statusId;
            document.querySelector('#addTranslationModal select[name="locale"]').value = locale;
            
            // Close missing translations modal
            bootstrap.Modal.getInstance(document.getElementById('missingTranslationsModal')).hide();
            
            // Open add translation modal
            const addModal = new bootstrap.Modal(document.getElementById('addTranslationModal'));
            addModal.show();
        });
    });
    
    // Delete translation functionality
    document.querySelectorAll('.delete-translation').forEach(button => {
        button.addEventListener('click', function() {
            const statusKey = this.dataset.statusKey;
            const locale = this.dataset.locale;
            if (confirm('Are you sure you want to delete the ' + locale + ' translation for "' + statusKey + '"?')) {
                // Implementation for delete functionality
                alert('Delete functionality would be implemented here');
            }
        });
    });
});
</script>
