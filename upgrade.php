<?php
// CONFIG
$githubUser = 'devyarn-subhajit';
$repoName   = 'wp-git';
$branch     = 'main';
// $githubToken = 'ghp_xxxxxxxxxxxxx'; // for private repos (if private repo)

// Files/folders to skip (preserve these)
$skipList = [
    '.env',
    'db',
    'upgrade.php'
];

// Optional: backup .env and db/latest.sql before upgrade
backup_critical_files($skipList);

echo "⬇ Downloading update from GitHub...\n";

// TEMP PATHS
$tmpZip = __DIR__ . '/update.zip';
$tmpDir = __DIR__ . '/tmp_update';

// Download ZIP
$zipUrl = "https://api.github.com/repos/$githubUser/$repoName/zipball/$branch";
$opts = [
    "http" => [
        "header" => "User-Agent: PHP\r\n"
        // "Authorization: token $githubToken\r\n"
    ]
];
$context = stream_context_create($opts);

$data = @file_get_contents($zipUrl, false, $context);
if (!$data || !file_put_contents($tmpZip, $data)) {
    exit("❌ Failed to download update ZIP.\n");
}

// Extract ZIP
$zip = new ZipArchive;
if ($zip->open($tmpZip) === TRUE) {
    if (!is_dir($tmpDir)) mkdir($tmpDir);
    $zip->extractTo($tmpDir);
    $zip->close();
} else {
    exit("❌ Failed to open ZIP file.\n");
}

// Find extracted root folder
$extractedFolders = glob($tmpDir . '/*', GLOB_ONLYDIR);
if (!$extractedFolders || !isset($extractedFolders[0])) {
    rrmdir($tmpDir);
    unlink($tmpZip);
    exit("❌ No folder found in extracted ZIP.\n");
}
$rootExtractedFolder = $extractedFolders[0];

// Copy new files safely
sync_directories($rootExtractedFolder, __DIR__, $skipList);

// Cleanup
rrmdir($tmpDir);
unlink($tmpZip);

echo "✅ Update complete!\n";

// === FUNCTIONS ===

function sync_directories($src, $dst, $skipList = []) {
    if (!is_dir($src)) return;
    if (!is_dir($dst)) @mkdir($dst, 0777, true);

    $dir = opendir($src);
    if (!$dir) return;

    while (false !== ($file = readdir($dir))) {
        if ($file == '.' || $file == '..') continue;

        $srcPath = "$src/$file";
        $dstPath = "$dst/$file";

        if (in_array($file, $skipList)) continue;

        if (is_dir($srcPath)) {
            sync_directories($srcPath, $dstPath, $skipList);
        } else {
            copy($srcPath, $dstPath);
        }
    }

    closedir($dir);
}

function rrmdir($dir) {
    if (!is_dir($dir)) return;
    foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
        $path = "$dir/$file";
        is_dir($path) ? rrmdir($path) : @unlink($path);
    }
    rmdir($dir);
}

function backup_critical_files($skipList) {
    echo "💾 Backing up critical files...\n";
    $backupDir = __DIR__ . '/backup_' . date('Ymd_His');
    @mkdir($backupDir, 0777, true);

    foreach ($skipList as $item) {
        $src = __DIR__ . '/' . $item;
        $dst = $backupDir . '/' . $item;

        if (is_dir($src)) {
            copy_dir($src, $dst);
        } elseif (file_exists($src)) {
            copy($src, $dst);
        }
    }

    echo "💾 Backup completed: $backupDir\n";
}

function copy_dir($src, $dst) {
    if (!is_dir($src)) return;
    @mkdir($dst, 0777, true);

    $files = array_diff(scandir($src), ['.', '..']);
    foreach ($files as $file) {
        $srcPath = "$src/$file";
        $dstPath = "$dst/$file";

        if (is_dir($srcPath)) {
            copy_dir($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }
}
