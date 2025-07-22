<?php
// Language test page for Rey CRM
require_once 'includes/functions.php';

// Initialize language
$current_language = getCurrentLanguage();
$language_info = getCurrentLanguageInfo();
$available_languages = getAvailableLanguages();
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_language); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('language'); ?> Test - <?php echo __('rey_crm'); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/language.css">
    <script src="/assets/js/language.js" defer></script>
</head>
<body>
    <div class="container" style="margin-top: 2rem;">
        <div style="background: #f5f5f5; padding: 2rem; border-radius: 8px; margin-bottom: 2rem;">
            <h1><?php echo __('language'); ?> <?php echo __('settings'); ?></h1>
            
            <!-- Language switcher -->
            <div class="language-switcher" style="margin: 1rem 0;">
                <label for="language-select"><?php echo __('select_language'); ?>:</label><br>
                <select id="language-select" onchange="switchLanguage(this.value)" style="margin-top: 0.5rem;">
                    <?php foreach ($available_languages as $code => $info): ?>
                        <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $code === $current_language ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($info['flag'] . ' ' . $info['native_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="margin-top: 2rem;">
                <h3><?php echo __('info'); ?></h3>
                <p><strong><?php echo __('language'); ?>:</strong> <?php echo htmlspecialchars($language_info['native_name']); ?></p>
                <p><strong>Code:</strong> <?php echo htmlspecialchars($current_language); ?></p>
                <p><strong>Direction:</strong> <?php echo htmlspecialchars($language_info['direction']); ?></p>
            </div>
        </div>
        
        <div style="background: white; padding: 2rem; border: 1px solid #ddd; border-radius: 8px;">
            <h2><?php echo __('language'); ?> Test</h2>
            
            <h3><?php echo __('dashboard'); ?> <?php echo __('info'); ?></h3>
            <ul>
                <li><?php echo __('dashboard'); ?></li>
                <li><?php echo __('customers'); ?></li>
                <li><?php echo __('all_activities'); ?></li>
                <li><?php echo __('email_projects'); ?></li>
                <li><?php echo __('settings'); ?></li>
                <li><?php echo __('profile'); ?></li>
            </ul>
            
            <h3><?php echo __('login'); ?> <?php echo __('info'); ?></h3>
            <ul>
                <li><?php echo __('login_title'); ?></li>
                <li><?php echo __('username'); ?></li>
                <li><?php echo __('password'); ?></li>
                <li><?php echo __('sign_in'); ?></li>
                <li><?php echo __('forgot_password'); ?></li>
            </ul>
            
            <h3><?php echo __('customers'); ?> <?php echo __('info'); ?></h3>
            <ul>
                <li><?php echo __('add_customer'); ?></li>
                <li><?php echo __('company_name'); ?></li>
                <li><?php echo __('contact_email'); ?></li>
                <li><?php echo __('status'); ?></li>
                <li><?php echo __('notes'); ?></li>
            </ul>
            
            <h3><?php echo __('actions'); ?></h3>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 1rem;">
                <button type="button" class="btn btn-primary"><?php echo __('save'); ?></button>
                <button type="button" class="btn btn-secondary"><?php echo __('cancel'); ?></button>
                <button type="button" class="btn btn-success"><?php echo __('add'); ?></button>
                <button type="button" class="btn btn-info"><?php echo __('edit'); ?></button>
                <button type="button" class="btn btn-warning"><?php echo __('export'); ?></button>
                <button type="button" class="btn btn-danger"><?php echo __('delete'); ?></button>
            </div>
            
            <h3><?php echo __('status'); ?> Options</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.5rem; margin-top: 1rem;">
                <span class="badge badge-info"><?php echo __('prospect'); ?></span>
                <span class="badge badge-success"><?php echo __('qualified'); ?></span>
                <span class="badge badge-warning"><?php echo __('new_customer'); ?></span>
                <span class="badge badge-primary"><?php echo __('active_customer'); ?></span>
                <span class="badge badge-secondary"><?php echo __('inactive_customer'); ?></span>
                <span class="badge badge-danger"><?php echo __('lost_customer'); ?></span>
            </div>
        </div>
        
        <div style="margin-top: 2rem; text-align: center;">
            <a href="dashboard.php" class="btn btn-primary"><?php echo __('back'); ?> to <?php echo __('dashboard'); ?></a>
        </div>
    </div>
</body>
</html>
