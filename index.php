<?php
require_once 'includes/header.php';

// Oturum kontrolü
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// PHP Bilgileri
$phpInfo = [
    'version' => PHP_VERSION,
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'display_errors' => ini_get('display_errors'),
    'max_input_vars' => ini_get('max_input_vars'),
    'default_charset' => ini_get('default_charset'),
    'extensions' => get_loaded_extensions()
];

// MySQL Bilgileri
$db = Database::getInstance()->getConnection();
$mysqlInfo = [
    'version' => $db->getAttribute(PDO::ATTR_SERVER_VERSION),
    'connection_status' => $db->getAttribute(PDO::ATTR_CONNECTION_STATUS),
    'server_info' => $db->getAttribute(PDO::ATTR_SERVER_INFO),
    'client_version' => $db->getAttribute(PDO::ATTR_CLIENT_VERSION)
];

// MySQL Variables
try {
    $stmt = $db->query("SHOW VARIABLES WHERE Variable_name IN (
        'max_connections',
        'innodb_buffer_pool_size',
        'key_buffer_size',
        'max_allowed_packet',
        'query_cache_size',
        'table_open_cache'
    )");
    $mysqlVariables = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $mysqlVariables = [];
}

// Sistem Bilgileri
$systemInfo = [
    'os' => PHP_OS,
    'server_software' => $_SERVER['SERVER_SOFTWARE'],
    'server_name' => $_SERVER['SERVER_NAME'],
    'server_addr' => $_SERVER['SERVER_ADDR'] ?? 'N/A',
    'server_port' => $_SERVER['SERVER_PORT'],
    'document_root' => $_SERVER['DOCUMENT_ROOT'],
    'disk_free_space' => disk_free_space("/"),
    'disk_total_space' => disk_total_space("/"),
];

// Tarayıcı ve IP Bilgileri
$clientInfo = [
    'ip' => $_SERVER['REMOTE_ADDR'],
    'forwarded_ip' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'N/A',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'N/A',
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'protocol' => $_SERVER['SERVER_PROTOCOL']
];

// Hosting Bilgileri
$hostingInfo = [
    'hostname' => gethostname(),
    'interface' => php_uname(),
    'server_admin' => $_SERVER['SERVER_ADMIN'] ?? 'N/A',
    'server_signature' => $_SERVER['SERVER_SIGNATURE'] ?? 'N/A'
];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Runik MySQL Backup - <?php echo Language::get('system_info'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body class="hold-transition sidebar-mini">
    <div class="wrapper">
        <!-- Navbar ve Sidebar -->
        <?php include 'includes/navbar.php'; ?>
        <?php include 'includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <section class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1><?php echo Language::get('system_information'); ?></h1>
                        </div>
                    </div>
                </div>
            </section>

            <section class="content">
                <div class="container-fluid">
                    <!-- PHP Bilgileri -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fab fa-php mr-2"></i><?php echo Language::get('php_info'); ?>
                            </h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped">
                                <tr>
                                    <td width="30%"><?php echo Language::get('php_version'); ?></td>
                                    <td><?php echo $phpInfo['version']; ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo Language::get('memory_limit'); ?></td>
                                    <td><?php echo $phpInfo['memory_limit']; ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo Language::get('max_execution_time'); ?></td>
                                    <td><?php echo $phpInfo['max_execution_time']; ?> <?php echo Language::get('seconds'); ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo Language::get('upload_max_filesize'); ?></td>
                                    <td><?php echo $phpInfo['upload_max_filesize']; ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo Language::get('post_max_size'); ?></td>
                                    <td><?php echo $phpInfo['post_max_size']; ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo Language::get('loaded_extensions'); ?></td>
                                    <td><small><?php echo implode(', ', $phpInfo['extensions']); ?></small></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- MySQL Bilgileri -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-database mr-2"></i><?php echo Language::get('mysql_info'); ?>
                            </h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped">
                                <tr>
                                    <td width="30%"><?php echo Language::get('mysql_version'); ?></td>
                                    <td><?php echo $mysqlInfo['version']; ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo Language::get('connection_status'); ?></td>
                                    <td><?php echo $mysqlInfo['connection_status']; ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo Language::get('max_connections'); ?></td>
                                    <td><?php echo $mysqlVariables['max_connections'] ?? 'N/A'; ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo Language::get('buffer_pool_size'); ?></td>
                                    <td><?php echo $mysqlVariables['innodb_buffer_pool_size'] ?? 'N/A'; ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo Language::get('query_cache_size'); ?></td>
                                    <td><?php echo $mysqlVariables['query_cache_size'] ?? 'N/A'; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Sistem Bilgileri -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-server mr-2"></i><?php echo Language::get('system_info'); ?>
                            </h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped">
                                <tr>
                                    <td width="30%"><?php echo Language::get('operating_system'); ?></td>
                                    <td><?php echo $systemInfo['os']; ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo Language::get('server_software'); ?></td>
                                    <td><?php echo $systemInfo['server_software']; ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo Language::get('hostname'); ?></td>
                                    <td><?php echo $hostingInfo['hostname']; ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo Language::get('disk_usage'); ?></td>
                                    <td>
                                        <?php 
                                        $usedSpace = $systemInfo['disk_total_space'] - $systemInfo['disk_free_space'];
                                        $usedPercent = round(($usedSpace / $systemInfo['disk_total_space']) * 100);
                                        ?>
                                        <div class="progress">
                                            <div class="progress-bar bg-primary" role="progressbar" 
                                                 style="width: <?php echo $usedPercent; ?>%">
                                                <?php echo $usedPercent; ?>%
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo round($systemInfo['disk_free_space'] / 1024 / 1024 / 1024, 2); ?> GB / 
                                            <?php echo round($systemInfo['disk_total_space'] / 1024 / 1024 / 1024, 2); ?> GB
                                        </small>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- IP ve Tarayıcı Bilgileri -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-globe mr-2"></i><?php echo Language::get('client_info'); ?>
                            </h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped">
                                <tr>
                                    <td width="30%"><?php echo Language::get('ip_address'); ?></td>
                                    <td><?php echo $clientInfo['ip']; ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo Language::get('forwarded_ip'); ?></td>
                                    <td><?php echo $clientInfo['forwarded_ip']; ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo Language::get('user_agent'); ?></td>
                                    <td><?php echo $clientInfo['user_agent']; ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo Language::get('accept_language'); ?></td>
                                    <td><?php echo $clientInfo['accept_language']; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    <script>
    $(document).ready(function() {
        // Fabrika ayarlarına dön
        $('#resetSystem').click(function() {
            if (confirm('<?php echo Language::get('factory_reset_confirm'); ?>')) {
                $.post('app/ajax/reset.php', function(response) {
                    if (response.success) {
                        window.location.href = 'install/index.php';
                    } else {
                        alert('<?php echo Language::get('error'); ?>: ' + response.error);
                    }
                });
            }
        });
    });
    </script>
</body>
</html> 