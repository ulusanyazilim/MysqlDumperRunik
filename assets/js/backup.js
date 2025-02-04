$(document).ready(function() {
    // Tüm checkbox'ları seç/kaldır
    $('#selectAll').change(function() {
        $('input[name="databases[]"]').prop('checked', $(this).prop('checked'));
    });

    // Backup formunu işle
    $('#backupForm').submit(function(e) {
        e.preventDefault();
        
        // Seçili veritabanlarını al
        const selectedDatabases = [];
        $('input[name="databases[]"]:checked').each(function() {
            selectedDatabases.push($(this).val());
        });

        if (selectedDatabases.length === 0) {
            alert('Lütfen en az bir veritabanı seçin!');
            return;
        }

        // Progress div'ini göster
        $('#backupStatus').show();
        $('#progressContainer').html(`
            <div class="alert alert-info">
                <i class="fas fa-sync-alt fa-spin mr-2"></i>
                ${LANG.preparing_backup}
            </div>
        `);

        // Yedekleme işlemini başlat
        $.ajax({
            url: 'app/ajax/backup.php',
            method: 'POST',
            data: { databases: selectedDatabases },
            dataType: 'json',
            success: function(response) {
                if (response.error) {
                    showError(response.error);
                    return;
                }
                // İlk tablodan başla
                processBackup(0, 0);
            },
            error: function(xhr, status, error) {
                showError('Yedekleme başlatılamadı: ' + error);
            }
        });
    });
});

function processBackup(tableIndex, offset) {
    $.ajax({
        url: 'app/ajax/process_backup.php',
        method: 'POST',
        data: { 
            tableIndex: tableIndex,
            offset: offset 
        },
        dataType: 'json',
        success: function(response) {
            if (response.error) {
                showError(response.error);
                return;
            }

            // Progress bilgisini güncelle
            updateProgress(response);

            // İşlem tamamlandı mı?
            if (response.completed) {
                setTimeout(function() {
                    location.reload();
                }, 1000);
                return;
            }

            // Veritabanı tamamlandı mı?
            if (response.isDatabaseComplete && response.nextDatabase) {
                // Yeni veritabanı için progress sıfırla
                $('#progressContainer').html(`
                    <div class="alert alert-info">
                        <i class="fas fa-sync-alt fa-spin mr-2"></i>
                        ${response.nextDatabase} ${LANG.backing_up_db}
                    </div>
                `);
            }

            // Devam et
            setTimeout(function() {
                processBackup(response.nextTableIndex, response.nextOffset);
            }, 100);
        },
        error: function(xhr, status, error) {
            showError('Yedekleme işlemi başarısız: ' + error);
        }
    });
}

function updateProgress(data) {
    if (!data) return;

    const percent = (data.currentTable / data.totalTables * 100).toFixed(2);
    const progressHtml = `
        <div class="progress">
            <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                 role="progressbar" 
                 style="width: ${percent}%">
                ${percent}%
            </div>
        </div>
        <div class="progress-details mt-2">
            <span><i class="fas fa-database"></i> ${LANG.database_label}: ${data.database}</span>
            <span><i class="fas fa-table"></i> ${LANG.table_progress}: ${data.currentTable}/${data.totalTables}</span>
            <span><i class="fas fa-clock"></i> ${LANG.elapsed_time}: ${formatTime(data.elapsedTime)}</span>
            <span><i class="fas fa-sync"></i> ${LANG.total_queries}: ${data.queryCount}</span>
        </div>
    `;

    $('#progressContainer').html(progressHtml);
}

function formatTime(seconds) {
    if (seconds < 60) return `${seconds} saniye`;
    if (seconds < 3600) {
        const minutes = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${minutes} dakika ${secs} saniye`;
    }
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    return `${hours} saat ${minutes} dakika`;
}

function showError(message) {
    $('#progressContainer').html(`
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle mr-2"></i>
            ${message}
        </div>
    `);
} 