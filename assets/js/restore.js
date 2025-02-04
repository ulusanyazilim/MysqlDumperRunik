// Global sorgu sayacı
let totalQueryCount = 0;
let lastProgress = {
    database: '',
    current_table: '',
    processed_tables: 0,
    total_tables: 0,
    processed_bytes: 0,
    total_bytes: 0,
    elapsed_time: 0,
    speed: 0
};

$(document).ready(function() {
    $('.restore-btn').click(function() {
        const file = $(this).data('file');
        const database = $(this).data('db');
        
        Swal.fire({
            title: LANG.confirm_restore,
            text: LANG.restore_in_progress,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: LANG.yes_restore,
            cancelButtonText: LANG.cancel
        }).then((result) => {
            if (result.isConfirmed) {
                startRestore(file, database);
            }
        });
    });
});

function startRestore(file, database) {
    totalQueryCount = 0; // Sayacı sıfırla
    $('#restoreStatus').show();
    updateProgress({
        message: LANG.restore_preparing,
        percent: 0
    });

    $.ajax({
        url: 'app/ajax/restore.php',
        type: 'POST',
        data: { file, database },
        dataType: 'json',
        success: function(response) {
            if (response.error) {
                showError(response.error);
                return;
            }
            processRestore(0);
        },
        error: function() {
            showError(LANG.restore_error);
        }
    });
}

function processRestore(offset) {
    $.ajax({
        url: 'app/ajax/process_restore.php',
        method: 'POST',
        data: { offset },
        dataType: 'json',
        success: function(response) {
            if (response.error) {
                showError(response.error);
                return;
            }
            
            // Geçerli değerleri sakla ve güncelle
            lastProgress = {
                database: response.database || lastProgress.database,
                current_table: response.current_table || lastProgress.current_table,
                processed_tables: isValidNumber(response.processed_tables) ? response.processed_tables : lastProgress.processed_tables,
                total_tables: isValidNumber(response.total_tables) ? response.total_tables : lastProgress.total_tables,
                processed_bytes: isValidNumber(response.processed_bytes) ? response.processed_bytes : lastProgress.processed_bytes,
                total_bytes: isValidNumber(response.total_bytes) ? response.total_bytes : lastProgress.total_bytes,
                elapsed_time: isValidNumber(response.elapsed_time) ? response.elapsed_time : lastProgress.elapsed_time,
                speed: isValidNumber(response.speed) ? response.speed : lastProgress.speed
            };
            
            // Progress ve istatistikleri güncelle
            updateProgress(lastProgress);
            
            // İşlem tamamlandı mı kontrol et
            if (response.complete === true) {
                Swal.fire({
                    title: 'Başarılı!',
                    text: LANG.restore_completed,
                    icon: 'success',
                    confirmButtonText: LANG.ok
                }).then(() => {
                    location.reload();
                });
                return;
            }
            
            // Devam et
            if (response.next_offset !== null) {
                setTimeout(() => {
                    processRestore(response.next_offset);
                }, 100);
            }
        },
        error: function(xhr, status, error) {
            showError(LANG.restore_error + ': ' + error);
        }
    });
}

function isValidNumber(value) {
    return typeof value === 'number' && !isNaN(value) && isFinite(value) && value >= 0;
}

function updateProgress(data) {
    if (!data) return;
    
    // Bellek kullanımını optimize et
    const formatNumber = num => new Intl.NumberFormat('tr-TR').format(num);
    const formatSize = size => Number(size).toFixed(2);
    const formatTime = seconds => {
        if (!seconds) return `0 ${LANG.seconds}`;
        
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        
        if (hours > 0) return `${hours} ${LANG.hours} ${minutes} ${LANG.minutes}`;
        if (minutes > 0) return `${minutes} ${LANG.minutes} ${secs} ${LANG.seconds}`;
        return `${secs} ${LANG.seconds}`;
    };
    
    // Progress hesapla
    const percent = data.total_bytes > 0 ? 
        ((data.processed_bytes / data.total_bytes) * 100).toFixed(2) : 0;
    
    // DOM manipülasyonunu optimize et
    const progressHtml = `
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="card-title">
                    <i class="fas fa-sync-alt fa-spin mr-2"></i>
                    ${LANG.restore_status}
                </h3>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tbody>
                        <tr>
                            <td><i class="fas fa-database"></i> ${LANG.database}:</td>
                            <td>${data.database || 'N/A'}</td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-table"></i> ${LANG.current_table}:</td>
                            <td>${data.current_table || 'N/A'}</td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-list"></i> ${LANG.processed_tables}:</td>
                            <td>${formatNumber(data.processed_tables)} / ${formatNumber(data.total_tables)}</td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-hdd"></i> ${LANG.processed_size}:</td>
                            <td>${formatSize(data.processed_bytes)} MB / ${formatSize(data.total_bytes)} MB</td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-clock"></i> ${LANG.elapsed_time}:</td>
                            <td>${formatTime(data.elapsed_time)}</td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-tachometer-alt"></i> ${LANG.speed}:</td>
                            <td>${formatNumber(data.speed)} ${LANG.queries_per_second}</td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="progress mt-3">
                    <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                         role="progressbar" 
                         style="width: ${percent}%">
                        ${percent}%
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // DOM'u tek seferde güncelle
    $('#restoreStatus').html(progressHtml);
}

function showError(message) {
    Swal.fire({
        title: LANG.error,
        text: message,
        icon: 'error',
        confirmButtonText: LANG.ok
    });
    
    $('#restoreStatus').html(`
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle mr-2"></i>
            ${message}
        </div>
    `);
}

// DataTable ve Drag&Drop işlemleri
$(document).ready(function() {
    // Drag & Drop işlemleri
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        const files = e.dataTransfer.files;
        handleFiles(files);
    });

    $('#browseButton').click(() => fileInput.click());

    fileInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });

    function handleFiles(files) {
        const maxChunkSize = 5 * 1024 * 1024; // 5MB chunk size
        
        async function uploadChunks(file) {
            const totalChunks = Math.ceil(file.size / maxChunkSize);
            const fileName = file.name;
            
            for (let chunk = 0; chunk < totalChunks; chunk++) {
                const start = chunk * maxChunkSize;
                const end = Math.min(start + maxChunkSize, file.size);
                const chunkBlob = file.slice(start, end);
                
                const formData = new FormData();
                formData.append('chunk', chunkBlob);
                formData.append('fileName', fileName);
                formData.append('chunkIndex', chunk);
                formData.append('totalChunks', totalChunks);
                
                try {
                    await $.ajax({
                        url: 'app/ajax/upload_chunk.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false
                    });
                } catch (error) {
                    throw new Error(`Chunk ${chunk + 1}/${totalChunks} yükleme hatası`);
                }
            }
        }
        
        // Yükleme başladı bildirimi
        Swal.fire({
            title: LANG.uploading,
            text: LANG.please_wait,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        const uploadPromises = Array.from(files).map(uploadChunks);
        
        Promise.all(uploadPromises)
            .then(() => {
                Swal.fire({
                    title: 'Başarılı!',
                    text: LANG.file_upload_success,
                    icon: 'success',
                    confirmButtonText: LANG.ok
                }).then(() => {
                    location.reload();
                });
            })
            .catch(error => {
                Swal.close();
                showError(error.message);
            });
    }

    // Silme işlemi
    $('.delete-btn').click(function() {
        const file = $(this).data('file');
        
        Swal.fire({
            title: 'Emin misiniz?',
            text: 'Bu yedeği silmek istediğinize emin misiniz?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Evet, Sil!',
            cancelButtonText: 'İptal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'app/ajax/delete_backup.php',
                    type: 'POST',
                    data: {file: file},
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                title: 'Başarılı!',
                                text: 'Yedek dosyası silindi.',
                                icon: 'success',
                                confirmButtonText: 'Tamam'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            showError(response.error || 'Silme işlemi başarısız.');
                        }
                    },
                    error: function() {
                        showError('Silme işlemi sırasında bir hata oluştu.');
                    }
                });
            }
        });
    });
}); 