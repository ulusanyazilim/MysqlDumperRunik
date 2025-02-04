<?php
require_once 'includes/header.php';

// Oturum kontrolü
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Yedek dosyalarını listele
$backupFiles = [];
if (is_dir(BACKUP_PATH)) {
    foreach (new DirectoryIterator(BACKUP_PATH) as $file) {
        if ($file->isDot()) continue;
        if ($file->isFile() && (
            pathinfo($file->getFilename(), PATHINFO_EXTENSION) === 'gz' ||
            pathinfo($file->getFilename(), PATHINFO_EXTENSION) === 'sql'
        )) {
            $backupFiles[] = [
                'name' => $file->getFilename(),
                'size' => round($file->getSize() / 1024 / 1024, 2),
                'date' => date('d.m.Y H:i:s', $file->getMTime())
            ];
        }
    }
}
// Tarihe göre sırala (en yeni en üstte)
usort($backupFiles, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <title>Runik MySQL Backup - <?php echo Language::get('restore'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        #dropZone {
            border: 2px dashed #ccc;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        #dropZone.dragover {
            background: #e9ecef;
            border-color: #007bff;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
    <div class="wrapper">
        <!-- Navbar ve Sidebar aynı şekilde kalacak -->
        <?php include 'includes/navbar.php'; ?>
        <?php include 'includes/sidebar.php'; ?>

        <!-- Content -->
        <div class="content-wrapper">
            <section class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1><?php echo Language::get('restore_operations'); ?></h1>
                        </div>
                    </div>
                </div>
            </section>

            <section class="content">
                <div class="container-fluid">
                    <div class="row">
                        <!-- İlerleme ve İstatistikler -->
                        <div class="col-md-6" id="restoreStatus" style="display:none;">
                            <div class="card card-primary">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-sync-alt mr-2"></i><?php echo Language::get('restore_status'); ?>
                                    </h3>
                                </div>
                                <div class="card-body" id="progressContainer">
                                    <!-- Progress bar buraya gelecek -->
                                </div>
                            </div>
                        </div>

                        <!-- Dosya Yükleme -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-upload mr-2"></i><?php echo Language::get('restore'); ?>
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div id="dropZone">
                                        <i class="fas fa-cloud-upload-alt fa-3x mb-3"></i>
                                        <p><?php echo Language::get('drag_drop_files'); ?></p>
                                        <button type="button" class="btn btn-primary" id="browseButton">
                                            <i class="fas fa-folder-open mr-2"></i><?php echo Language::get('browse_files'); ?>
                                        </button>
                                        <input type="file" id="fileInput" style="display: none;" accept=".sql,.gz">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <div class="mt-4">
                                        <table class="table table-bordered table-striped">
                                            <thead>
                                                <tr>
                                                    <th><?php echo Language::get('file_name'); ?></th>
                                                    <th><?php echo Language::get('size'); ?></th>
                                                    <th><?php echo Language::get('backup_date'); ?></th>
                                                    <th><?php echo Language::get('actions'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($backupFiles as $file): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($file['name']); ?></td>
                                                    <td><?php echo $file['size']; ?> MB</td>
                                                    <td><?php echo $file['date']; ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-success restore-btn" 
                                                                data-file="<?php echo htmlspecialchars($file['name']); ?>"
                                                                data-db="<?php echo htmlspecialchars(explode('_', $file['name'])[0]); ?>">
                                                            <i class="fas fa-undo mr-1"></i><?php echo Language::get('restore'); ?>
                                                        </button>
                                                        <a href="app/ajax/download.php?file=<?php echo urlencode($file['name']); ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-download mr-1"></i><?php echo Language::get('download'); ?>
                                                        </a>
                                                        <button class="btn btn-sm btn-danger delete-btn" data-file="<?php echo htmlspecialchars($file['name']); ?>">
                                                            <i class="fas fa-trash mr-1"></i><?php echo Language::get('delete'); ?>
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
                </div>
            </section>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/restore.js"></script>

    <!-- DataTable dil verilerini gizli div içinde sakla -->
    <script type="text/template" id="datatableLang">
        <?php echo json_encode(Language::get('datatable')); ?>
    </script>

    <!-- Scripts kısmından önce -->
    <?php
// Dil tanımlamalarını PHP'de hazırla
$_SESSION['backup']['queryCount'] = 0;
$langData = [
    'restore_preparing' => Language::get("restore_preparing"),
    'restore_in_progress' => Language::get("restore_in_progress"),

    'database_label' => Language::get("database_label"),
    'current_table' => Language::get("current_table"),
    'processed_tables' => Language::get("processed_tables"),
    'processed_size' => Language::get("processed_size"),
    'elapsed_time' => Language::get("elapsed_time"),
    'speed' => Language::get("speed"),
    'queries_per_second' => Language::get("queries_per_second"),
    'restore_status' => Language::get("restore_status"),
    'confirm_restore' => Language::get("confirm_restore"),
    'yes_restore' => Language::get("yes_restore"),
    'cancel' => Language::get("cancel"),
    'error' => Language::get("error"),
    'ok' => Language::get("ok"),
    'seconds' => Language::get("seconds"),
    'minutes' => Language::get("minutes"),
    'hours' => Language::get("hours"),
    // Eksik olan dil anahtarları
    'restore_error' => Language::get("restore_error"),
    'restore_completed' => Language::get("restore_completed"),
    'confirm_restore_text' => Language::get("confirm_restore_text"),
    'database' => Language::get("database"),
    'table' => Language::get("table"),
    'tables' => Language::get("tables"),
    'processed' => Language::get("processed"),
    'total' => Language::get("total"),
    'size' => Language::get("size"),
    'speed' => Language::get("speed"),
    'time' => Language::get("time"),
    'total_queries' => Language::get("total_queries")
];
?>

<!-- Diğer HTML içeriği -->

<!-- Dil tanımlamalarını JavaScript'e aktar -->
<script>
const LANG = <?php echo json_encode($langData, JSON_UNESCAPED_UNICODE); ?>;
</script>
</body>
</html> 