<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="index.php" class="brand-link text-center">
        <i class="fas fa-database"></i>
        <span class="brand-text font-weight-light ml-2">Runik MySQL Backup</span>
    </a>

    <div class="sidebar">
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column">
                <li class="nav-item">
                    <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-info-circle"></i>
                        <p><?php echo Language::get('system_info'); ?></p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="backup.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'backup.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-download"></i>
                        <p><?php echo Language::get('backup'); ?></p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="restore.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'restore.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-upload"></i>
                        <p><?php echo Language::get('restore'); ?></p>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Dil Seçimi -->
        <div class="mt-4 pb-3 mb-4 border-bottom">
            <form method="post" id="languageForm" class="px-3">
                <div class="form-group">
                    <label class="text-light">
                        <i class="fas fa-language mr-2"></i><?php echo Language::get('select_language'); ?>
                    </label>
                    <select name="language" class="form-control form-control-sm" id="language_select">
                        <?php foreach(AVAILABLE_LANGUAGES as $code => $name): ?>
                            <option value="<?php echo $code; ?>" <?php echo $currentLang === $code ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <!-- Versiyon Bilgisi -->
        <div class="sidebar-custom text-center text-light opacity-50">
            <small>v2.0</small>
        </div>
    </div>
</aside>

<script>
// Dil değiştiğinde sayfayı yenile
document.getElementById('language_select').addEventListener('change', function() {
    this.form.submit();
});
</script> 