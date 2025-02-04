<?php
session_start();
require_once 'app/config/config.php';
require_once 'app/models/Database.php';

if (!isset($_SESSION['admin_logged_in']) || !isset($_SESSION['selected_dbs'])) {
    header('Location: backup.php');
    exit;
}

$selectedDbs = $_SESSION['selected_dbs'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Runik MySQL Backup - Yedekleme İlerlemesi</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .progress-card {
            transition: all 0.3s ease;
        }
        .stats-item {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            background: #f8f9fa;
        }
        .animated-bg {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { background-color: #f8f9fa; }
            50% { background-color: #e9ecef; }
            100% { background-color: #f8f9fa; }
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
                            <h1>Yedekleme İlerlemesi</h1>
                        </div>
                    </div>
                </div>
            </section>

            <section class="content">
                <div class="container-fluid">
                    <!-- İşlem Detayları -->
                    <div class="row">
                        <!-- Sol Taraf - İstatistikler -->
                        <div class="col-md-4">
                            <div class="info-box bg-gradient-info">
                                <span class="info-box-icon"><i class="fas fa-database"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Aktif Veritabanı</span>
                                    <span class="info-box-number" id="activeDb">-</span>
                                </div>
                            </div>

                            <div class="info-box bg-gradient-success">
                                <span class="info-box-icon"><i class="fas fa-table"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Aktif Tablo</span>
                                    <span class="info-box-number" id="activeTable">-</span>
                                </div>
                            </div>

                            <div class="info-box bg-gradient-warning">
                                <span class="info-box-icon"><i class="fas fa-clock"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Geçen Süre</span>
                                    <span class="info-box-number" id="elapsedTime">0:00</span>
                                </div>
                            </div>

                            <div class="info-box bg-gradient-danger">
                                <span class="info-box-icon"><i class="fas fa-hourglass-half"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Tahmini Kalan Süre</span>
                                    <span class="info-box-number" id="estimatedTime">Hesaplanıyor...</span>
                                </div>
                            </div>
                        </div>

                        <!-- Sağ Taraf - İlerleme ve Konsol -->
                        <div class="col-md-8">
                            <!-- Genel İlerleme -->
                            <div class="card card-primary">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-tasks mr-2"></i>Genel İlerleme
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="progress">
                                        <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" 
                                             id="totalProgress" style="width: 0%">0%</div>
                                    </div>
                                    <div class="mt-3">
                                        <span class="badge badge-info mr-2" id="totalDbs">0/0 Veritabanı</span>
                                        <span class="badge badge-success mr-2" id="totalTables">0 Tablo</span>
                                        <span class="badge badge-warning" id="totalRows">0 Satır</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Aktif İşlem -->
                            <div class="card card-info">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-spinner fa-spin mr-2"></i>
                                        <span id="currentDbName">Başlatılıyor...</span>
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="progress mb-3">
                                        <div class="progress-bar bg-info progress-bar-striped progress-bar-animated" 
                                             id="currentProgress" style="width: 0%">0%</div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <small class="text-muted">Aktif Tablo:</small>
                                            <div id="currentTable" class="text-bold">-</div>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">İşlenen Satır:</small>
                                            <div id="currentRows" class="text-bold">-</div>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">Dosya Boyutu:</small>
                                            <div id="currentSize" class="text-bold">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Konsol -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card card-dark">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-terminal mr-2"></i>İşlem Detayları
                                    </h3>
                                </div>
                                <div class="card-body p-0">
                                    <div class="console-wrapper" style="background: #1a1a1a; color: #fff; padding: 15px; height: 300px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 14px;">
                                        <div id="console-output"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tamamlanan Yedeklemeler -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card card-success">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-check-circle mr-2"></i>Tamamlanan Yedeklemeler
                                    </h3>
                                </div>
                                <div class="card-body" id="completedBackups">
                                    <!-- AJAX ile doldurulacak -->
                                </div>
                            </div>
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
        var selectedDbs = <?php echo json_encode($selectedDbs); ?>;
        var startTime = new Date();
        var currentDbIndex = 0;
        var totalProcessedRows = 0;
        var totalProcessedSize = 0;
        var completedDbs = [];

        function updateStats(stats) {
            // Genel ilerleme
            var totalProgress = (currentDbIndex * 100 / selectedDbs.length) + 
                              (stats.progress / selectedDbs.length);
            $('#totalProgress').css('width', totalProgress + '%').text(Math.round(totalProgress) + '%');
            
            // Veritabanı sayısı
            $('#totalDbs').text(currentDbIndex + '/' + selectedDbs.length + ' Veritabanı');
            
            // Toplam tablo ve satır
            $('#totalTables').text(stats.total_tables + ' Tablo');
            $('#totalRows').text(stats.processed_rows + ' Satır');
            
            // Tahmini süre
            var elapsed = (new Date() - startTime) / 1000;
            var rate = stats.total_processed / elapsed;
            var remaining = (stats.total_size - stats.total_processed) / rate;
            $('#estimatedTime').text(Math.round(remaining) + ' saniye');
            
            // Aktif yedekleme bilgileri
            $('#currentDbName').text(selectedDbs[currentDbIndex]);
            $('#currentProgress').css('width', stats.progress + '%').text(Math.round(stats.progress) + '%');
            $('#currentTable').text(stats.current_table);
            $('#currentRows').text(stats.processed_rows + ' / ' + stats.total_rows + ' satır');
            $('#currentSize').text(stats.processed_size + ' MB');
        }

        function addCompletedBackup(db, size, duration) {
            var html = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle mr-2"></i>
                    <strong>${db}</strong> - ${size} MB
                    <small class="float-right">
                        <i class="fas fa-clock mr-1"></i>${duration} saniye
                    </small>
                </div>
            `;
            $('#completedBackups').prepend(html);
        }

        function processDatabase(dbIndex = 0, table = '', offset = 0) {
            if (dbIndex >= selectedDbs.length) {
                alert('Tüm yedeklemeler tamamlandı!');
                window.location.href = 'backup.php';
                return;
            }

            $.post('app/ajax/backup.php', {
                database: selectedDbs[dbIndex],
                table: table,
                offset: offset
            }, function(response) {
                if (response.error) {
                    alert('Hata: ' + response.error);
                    return;
                }

                updateStats(response);

                if (response.completed) {
                    // Veritabanı tamamlandı
                    var duration = Math.round((new Date() - startTime) / 1000);
                    addCompletedBackup(selectedDbs[dbIndex], response.processed_size, duration);
                    currentDbIndex++;
                    startTime = new Date(); // Yeni veritabanı için süreyi sıfırla
                    processDatabase(dbIndex + 1);
                } else {
                    // Aynı veritabanına devam et
                    processDatabase(dbIndex, response.next_table, response.next_offset);
                }
            });
        }

        // Yedeklemeyi başlat
        processDatabase();
    });
    </script>
</body>
</html> 