<?php
require_once 'includes/header.php';

// Oturum kontrolü
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Veritabanı listesini al
$db = Database::getInstance()->getConnection();
$databases = [];
$excludedDbs = ['information_schema', 'performance_schema', 'mysql', 'sys', 'phpmyadmin'];

$result = $db->query("SELECT 
    TABLE_SCHEMA as 'database',
    COUNT(*) as tables,
    ROUND(SUM(DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as size
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA NOT IN ('" . implode("','", $excludedDbs) . "')
GROUP BY TABLE_SCHEMA");

while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $databases[] = [
        'name' => $row['database'],
        'tables' => $row['tables'],
        'size' => $row['size'] ?? 0
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Runik MySQL Backup - <?php echo Language::get('backup'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <style>
        .progress-details {
            font-size: 0.9rem;
            margin-top: 10px;
        }
        .progress-details span {
            margin-right: 15px;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
    <div class="wrapper">
        <?php include 'includes/navbar.php'; ?>
        <?php include 'includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <section class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1><?php echo Language::get('backup_operations'); ?></h1>
                        </div>
                    </div>
                </div>
            </section>

            <section class="content">
                <div class="container-fluid">
                    <div class="row">
                        <!-- İlerleme ve İstatistikler -->
                        <div class="col-md-12" id="backupStatus" style="display:none;">
                            <div class="card card-primary">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-sync-alt mr-2"></i><?php echo Language::get('backup_status'); ?>
                                    </h3>
                                </div>
                                <div class="card-body" id="progressContainer">
                                    <!-- Progress bar buraya gelecek -->
                                </div>
                            </div>
                        </div>

                        <!-- Veritabanı Listesi -->
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-database mr-2"></i><?php echo Language::get('select_databases'); ?>
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <form id="backupForm" method="post">
                                        <table class="table table-bordered table-striped">
                                            <thead>
                                                <tr>
                                                    <th width="5%"><input type="checkbox" id="selectAll"></th>
                                                    <th><?php echo Language::get('database_name'); ?></th>
                                                    <th><?php echo Language::get('table_count'); ?></th>
                                                    <th><?php echo Language::get('size'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($databases as $db): ?>
                                                <tr>
                                                    <td><input type="checkbox" name="databases[]" value="<?php echo $db['name']; ?>"></td>
                                                    <td><?php echo $db['name']; ?></td>
                                                    <td><?php echo $db['tables']; ?></td>
                                                    <td><?php echo $db['size']; ?> MB</td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        <button type="submit" class="btn btn-primary mt-3">
                                            <i class="fas fa-download mr-2"></i><?php echo Language::get('start_backup'); ?>
                                        </button>
                                    </form>
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
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    <script>
    // Dil çevirilerini JavaScript'e aktar
    const LANG = {
        database_label: '<?php echo Language::get("database_label"); ?>',
        table_progress: '<?php echo Language::get("table_progress"); ?>',
        elapsed_time: '<?php echo Language::get("elapsed_time"); ?>',
        total_queries: '<?php echo Language::get("total_queries"); ?>',
        preparing_backup: '<?php echo Language::get("preparing_backup"); ?>',
        backing_up_db: '<?php echo Language::get("backing_up_db"); ?>'
    };
    </script>
    <script src="assets/js/backup.js"></script>
</body>
</html> 