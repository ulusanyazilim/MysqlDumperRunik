<?php
class Backup {
    private $db;
    private $tables = [];
    private $rowsPerBatch = 1000;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->getTables();
    }

    private function getTables() {
        $stmt = $this->db->query('SHOW TABLES');
        while($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $this->tables[] = $row[0];
        }
    }

    public function processPartialBackup($currentTable = '', $offset = 0) {
        if (empty($currentTable)) {
            // İlk çağrı - yeni yedek dosyası oluştur
            $this->backupFile = BACKUP_PATH . '/backup_' . date('Y-m-d_H-i-s') . '.sql';
            return $this->processNextTable('', 0);
        }

        $tableIndex = array_search($currentTable, $this->tables);
        if ($tableIndex === false) {
            throw new Exception('Geçersiz tablo');
        }

        // Tablo verilerini al
        $stmt = $this->db->query("SELECT COUNT(*) FROM $currentTable");
        $totalRows = $stmt->fetchColumn();

        if ($offset === 0) {
            // Tablo yapısını yedekle
            $stmt = $this->db->query("SHOW CREATE TABLE $currentTable");
            $row = $stmt->fetch(PDO::FETCH_NUM);
            file_put_contents($this->backupFile, "\n\n" . $row[1] . ";\n\n", FILE_APPEND);
        }

        // Veri parçasını yedekle
        $rows = $this->db->query("SELECT * FROM $currentTable LIMIT $offset, {$this->rowsPerBatch}");
        $output = '';
        while ($row = $rows->fetch(PDO::FETCH_NUM)) {
            $output .= "INSERT INTO $currentTable VALUES(";
            foreach ($row as $data) {
                $output .= "'" . addslashes($data) . "',";
            }
            $output = rtrim($output, ',') . ");\n";
        }
        file_put_contents($this->backupFile, $output, FILE_APPEND);

        $newOffset = $offset + $this->rowsPerBatch;
        if ($newOffset >= $totalRows) {
            // Tablo tamamlandı, sıradaki tabloya geç
            return $this->processNextTable($currentTable, 0);
        }

        // Aynı tabloda devam et
        $progress = ($tableIndex * 100 / count($this->tables)) + 
                   ($offset * 100 / $totalRows / count($this->tables));

        return [
            'completed' => false,
            'next_table' => $currentTable,
            'next_offset' => $newOffset,
            'progress' => round($progress),
            'status' => "$currentTable tablosu yedekleniyor... (" . 
                       round($offset * 100 / $totalRows) . "%)"
        ];
    }

    private function processNextTable($currentTable, $offset) {
        if (empty($currentTable)) {
            // İlk tablo
            return [
                'completed' => false,
                'next_table' => $this->tables[0],
                'next_offset' => 0,
                'progress' => 0,
                'status' => 'Yedekleme başlıyor...'
            ];
        }

        $currentIndex = array_search($currentTable, $this->tables);
        if ($currentIndex === count($this->tables) - 1) {
            // Son tablo tamamlandı
            return [
                'completed' => true,
                'progress' => 100,
                'status' => 'Yedekleme tamamlandı!'
            ];
        }

        // Sıradaki tabloya geç
        return [
            'completed' => false,
            'next_table' => $this->tables[$currentIndex + 1],
            'next_offset' => 0,
            'progress' => round(($currentIndex + 1) * 100 / count($this->tables)),
            'status' => 'Sıradaki tabloya geçiliyor...'
        ];
    }

    public function processPartialRestore($file, $offset = 0) {
        $backupFile = BACKUP_PATH . '/' . basename($file);
        if (!file_exists($backupFile)) {
            throw new Exception('Yedek dosyası bulunamadı');
        }

        $fileSize = filesize($backupFile);
        $handle = fopen($backupFile, 'r');
        fseek($handle, $offset);

        $batchSize = 1024 * 1024; // 1MB
        $sql = '';
        $bytesRead = 0;

        while (!feof($handle) && $bytesRead < $batchSize) {
            $line = fgets($handle);
            $bytesRead += strlen($line);

            if (empty(trim($line)) || strpos($line, '--') === 0) {
                continue;
            }

            $sql .= $line;
            if (substr(trim($line), -1) === ';') {
                try {
                    $this->db->exec($sql);
                } catch (PDOException $e) {
                    fclose($handle);
                    throw new Exception('SQL Hatası: ' . $e->getMessage());
                }
                $sql = '';
            }
        }

        $newOffset = $offset + $bytesRead;
        $progress = round($newOffset * 100 / $fileSize);

        fclose($handle);

        if ($newOffset >= $fileSize) {
            return [
                'completed' => true,
                'progress' => 100,
                'status' => 'Geri yükleme tamamlandı!'
            ];
        }

        return [
            'completed' => false,
            'next_offset' => $newOffset,
            'progress' => $progress,
            'status' => 'Geri yükleme devam ediyor... (' . $progress . '%)'
        ];
    }
} 