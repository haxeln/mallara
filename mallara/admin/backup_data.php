<?php
session_start();
require '../config/database.php'; // Pastikan path ini sesuai

// --- 1. CEK KEAMANAN ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// --- 2. LOGIKA PHP BACKUP ---
// Ambil semua tabel
 $all_tables = [];
 $result_all_tables = mysqli_query($conn, "SHOW TABLES");
while ($row = mysqli_fetch_row($result_all_tables)) {
    $all_tables[] = $row[0];
}

 $message = "";
 $msgType = "";

// Proses Backup
if (isset($_POST['backup_now'])) {
    set_time_limit(0); 

    // Tentukan Folder (Default: 'backups' atau sesuai input user)
    $folder_name = isset($_POST['backup_folder']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['backup_folder']) : 'backups';
    $backup_dir = __DIR__ . '/' . $folder_name . '/';
    
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true); 
    }

    // Tentukan Tabel
    $selected_tables = isset($_POST['tables']) ? $_POST['tables'] : [];
    $tables = empty($selected_tables) ? $all_tables : $selected_tables;

    // Generate SQL
    $return = "";
    foreach ($tables as $table) {
        $result = mysqli_query($conn, "SELECT * FROM $table");
        $num_fields = mysqli_num_fields($result);
        $row2 = mysqli_fetch_row(mysqli_query($conn, "SHOW CREATE TABLE $table"));
        
        $return .= 'DROP TABLE IF EXISTS ' . $table . ';';
        $return .= "\n\n" . $row2[1] . ";\n\n";
        
        for ($i = 0; $i < $num_fields; $i++) {
            while ($row = mysqli_fetch_row($result)) {
                $return .= 'INSERT INTO ' . $table . ' VALUES(';
                for ($j = 0; $j < $num_fields; $j++) {
                    $row[$j] = addslashes($row[$j]);
                    $row[$j] = str_replace("\n", "\\n", $row[$j]);
                    $return .= isset($row[$j]) ? '"' . $row[$j] . '"' : '""';
                    if ($j < ($num_fields - 1)) { $return .= ','; }
                }
                $return .= ");\n";
            }
        }
        $return .= "\n\n\n";
    }

    // Simpan File
    $file_name = 'backup_' . date('Ymd_His') . '.sql';
    $file_path = $backup_dir . $file_name;
    
    $handle = fopen($file_path, 'w+');
    if ($handle && fwrite($handle, $return)) {
        $message = "Backup berhasil! File disimpan di: <strong>$folder_name/$file_name</strong>";
        $msgType = "success";
    } else {
        $message = "Gagal menyimpan file. Cek permission folder.";
        $msgType = "danger";
    }
    fclose($handle);
}

// --- LOGIKA HAPUS FILE ---
if (isset($_GET['delete_file'])) {
    $file_to_delete = basename($_GET['delete_file']);
    $delete_path = __DIR__ . '/backups/' . $file_to_delete;

    if (file_exists($delete_path)) {
        unlink($delete_path);
        $message = "File <strong>$file_to_delete</strong> berhasil dihapus.";
        $msgType = "warning";
    } else {
        $message = "Gagal menghapus file. File tidak ditemukan.";
        $msgType = "danger";
    }
    echo "<script>window.location.href='backup_data.php';</script>";
    exit;
}

// Baca History Backup
 $backup_history = [];
 $folders_to_scan = ['backups'];

foreach ($folders_to_scan as $fol) {
    $dir_path = __DIR__ . '/' . $fol . '/';
    if (is_dir($dir_path)) {
        foreach (glob($dir_path . "*.sql") as $file) {
            $backup_history[] = [
                'name' => basename($file),
                'path' => $fol . '/' . basename($file),
                'size' => filesize($file),
                'date' => filemtime($file)
            ];
        }
    }
}

// Urutkan history terbaru
usort($backup_history, function($a, $b) {
    return $b['date'] - $a['date'];
});
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Data - Mallara</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            --admin-primary: #8d1a1a;
            --admin-bg: #f3f4f6;
            /* Badge colors untuk referensi, meski tidak dipakai langsung di backup */
            --badge-process: #dc3545;
            --badge-shipped: #ffc107;
            --badge-done: #28a745;
        }

        body {
            margin: 0; padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--admin-bg);
            display: flex;
            min-height: 100vh;
            color: #8d1a1a; 
        }

        /* --- SIDEBAR STYLES (SAMA PERSIS DARI REPORTS) --- */
        .sidebar {
            width: 260px; background-color: var(--admin-primary); color: white;
            position: fixed; top: 0; left: 0; height: 100%;
            display: flex; flex-direction: column; z-index: 1000;
        }
        .sidebar-header { padding: 25px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.1); }
        .sidebar-header h2 { margin: 0; font-family: 'Times New Roman', serif; letter-spacing: 2px; font-size: 28px; }
        .sidebar-header span { font-size: 12px; opacity: 0.7; }
        .sidebar-menu { flex: 1; padding-top: 20px; }
        .menu-item {
            display: flex; align-items: center; padding: 15px 25px;
            color: rgba(255,255,255,0.8); text-decoration: none; transition: 0.3s;
            border-left: 4px solid transparent; font-size: 15px;
        }
        .menu-item i { width: 25px; text-align: center; margin-right: 15px; }
        .menu-item:hover, .menu-item.active { background: rgba(255, 255, 255, 0.15); color: white; border-left-color: #fff; }
        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); }

        /* MAIN CONTENT */
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }

        .btn-primary {
            background-color: var(--admin-primary);
            border-color: var(--admin-primary);
        }
        .btn-primary:hover {
            background-color: #600000;
            border-color: #600000;
        }
        .text-primary { color: var(--admin-primary) !important; }
        .form-control:focus, .form-select:focus {
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 0.25rem rgba(141, 26, 26, 0.25);
        }

        .table-container {
            background: white; padding: 20px; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); overflow-x: auto;
        }

        th { 
            color: var(--admin-primary) !important; 
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h2, .sidebar-header span, .menu-item span, .sidebar-footer span { display: none; }
            .sidebar-header { padding: 15px 0; }
            .menu-item { justify-content: center; padding: 15px; }
            .menu-item i { margin: 0; font-size: 18px; }
            .main-content { margin-left: 70px; width: calc(100% - 70px); padding: 15px; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR (SAMA PERSIS DENGAN REPORTS) -->
    <aside class="sidebar">
        <div class="sidebar-header"><h2>MALLARA</h2><span>ADMIN PANEL</span></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
            <a href="products.php" class="menu-item"><i class="fas fa-box"></i> <span>Products</span></a>
            <a href="trending.php" class="menu-item"><i class="fas fa-fire"></i> <span>Trending</span></a>
            <a href="orders.php" class="menu-item"><i class="fas fa-shopping-bag"></i> <span>Transactions</span></a>
            <a href="users.php" class="menu-item"><i class="fas fa-users"></i> <span>Users</span></a>
            <a href="reports.php" class="menu-item"><i class="fas fa-chart-line"></i> <span>Reports</span></a>
            <!-- Active di Backup Data -->
            <a href="backup_data.php" class="menu-item active"><i class="fas fa-database"></i> <span>Backup Data</span></a>    
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="menu-item" style="color: #ffcccc;"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        
        <!-- Notifikasi Alert -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $msgType; ?> alert-dismissible fade show shadow-sm" role="alert">
                <?= $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Header Halaman -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
            <!-- Font size 24px sesuai request sebelumnya -->
            <h2 class="fw-bold text-primary" style="font-family: 'Times New Roman', serif; font-size: 24px; margin:0;">Backup Data</h2>
            <span class="text-muted"><i class="far fa-calendar-alt me-2 text-primary"></i> <?= date('d - m - Y'); ?></span>
        </div>

        <div class="row g-4">
            <!-- KOLOM KIRI: Form Backup -->
            <div class="col-lg-4">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body">
                        <h5 class="card-title fw-bold text-primary mb-3">Backup Configuration</h5>
                        <p class="text-muted small mb-4">Back up system data to prevent data loss.</p>
                        
                        <form method="POST">
                            <!-- Input Folder -->
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-secondary">Select Storage Folder</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-folder text-warning"></i></span>
                                    <input type="text" name="backup_folder" class="form-control" value="backups" placeholder="Nama folder...">
                                </div>
                            </div>

                            <!-- Tanggal (Readonly) -->
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-uppercase text-secondary">BackUp Date</label>
                                <input type="text" class="form-control bg-light" value="<?= date('d - m - Y'); ?>" disabled>
                            </div>

                            <!-- Pilihan Tabel (Opsional) -->
                            <div class="accordion mb-4 shadow-sm" id="accordionTableSelect">
                                <div class="accordion-item border">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed py-3 fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                            <i class="fas fa-list-ul me-2 text-primary"></i> Choose Table (Opsional)
                                        </button>
                                    </h2>
                                    <div id="collapseOne" class="accordion-collapse collapse" data-bs-parent="#accordionTableSelect">
                                        <div class="accordion-body bg-light">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" id="checkAll" onchange="toggleAll(this)">
                                                <label class="form-check-label small fw-bold text-primary" for="checkAll">SELECT ALL</label>
                                            </div>
                                            <hr class="my-2">
                                            <div style="max-height: 150px; overflow-y: auto; padding-right: 5px;">
                                                <?php foreach($all_tables as $table): ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input table-checkbox" type="checkbox" name="tables[]" value="<?= $table; ?>" id="tbl_<?= $table; ?>">
                                                        <label class="form-check-label small text-secondary" for="tbl_<?= $table; ?>">
                                                            <?= $table; ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tombol Aksi -->
                            <button type="submit" name="backup_now" class="btn btn-primary w-100 py-2 shadow">
                                <i class="fas fa-save me-2"></i> BACKUP NOW
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- KOLOM KANAN: History Backup -->
            <div class="col-lg-8">
                <div class="table-container h-100 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold m-0 text-primary"><i class="fas fa-history me-2"></i>BACKUP HISTORY</h5>
                        <span class="badge bg-secondary rounded-pill"><?= count($backup_history); ?> Files</span>
                    </div>
                    
                    <div class="table-responsive flex-grow-1">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3 text-uppercase small" style="width: 50px;">No</th>
                                    <th class="text-uppercase small">Filename</th>
                                    <th class="text-uppercase small">Date</th>
                                    <th class="text-uppercase small">Size</th>
                                    <th class="text-end pe-3 text-uppercase small">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($backup_history)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="fas fa-folder-open fa-2x mb-3 opacity-25"></i>
                                            <p class="mb-0">Belum ada riwayat backup.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; foreach ($backup_history as $file): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold text-muted"><?= $no++; ?></td>
                                            <td class="fw-bold text-primary">
                                                <?= $file['name']; ?>
                                            </td>
                                            <td class="text-muted small"><?= date('d - m - Y', $file['date']); ?></td>
                                            <td class="text-muted small">
                                                <?php 
                                                    $size = $file['size'];
                                                    if($size >= 1048576) {
                                                        echo round($size / 1048576, 2) . ' MB';
                                                    } else {
                                                        echo round($size / 1024, 2) . ' KB';
                                                    }
                                                ?>
                                            </td>
                                            <td class="text-end pe-3">
                                                <a href="<?= $file['path']; ?>" class="btn btn-sm btn-outline-primary me-1" download title="Download">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <a href="?delete_file=<?= $file['name']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Yakin ingin menghapus file backup ini?');" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function toggleAll(source) {
            var checkboxes = document.querySelectorAll('.table-checkbox');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = source.checked;
            });
        }
    </script>
</body>
</html>