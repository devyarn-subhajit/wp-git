<?php
// CONFIG
$githubUser = 'devyarn-subhajit';
$repoName   = 'wp-git';
$branch     = 'main';
// $githubToken = 'ghp_xxxxxxxxxxxxx'; // For private repos

// Files/folders to skip (preserve these)
$skipList = [
    '.env',
    'db',
    'upgrade.php'
];

echo "⬇ Downloading update from GitHub...\n";

// TEMP PATHS
$tmpZip = __DIR__ . '/update.zip';
$tmpDir = __DIR__ . '/tmp_update';

// Download ZIP
$zipUrl = "https://api.github.com/repos/$githubUser/$repoName/zipball/$branch";
$opts = [
    "http" => [
        "header" => "User-Agent: PHP\r\n"
        // "Authorization: token $githubToken\r\n" // if private
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

// Get list of files in the extracted GitHub ZIP
$githubFiles = [];
scan_files($rootExtractedFolder, $githubFiles, $rootExtractedFolder);

// 1️⃣ REMOVE local files that were deleted on GitHub
clean_deleted_files(__DIR__, $githubFiles, $skipList);

// 2️⃣ COPY/UPDATE files from GitHub
sync_directories($rootExtractedFolder, __DIR__, $skipList);

// Cleanup
rrmdir($tmpDir);
unlink($tmpZip);

echo "✅ Update complete!\n";

// === FUNCTIONS ===

function scan_files($dir, &$fileList, $root) {
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $path = "$dir/$item";
        $relative = ltrim(str_replace($root, '', $path), '/\\');
        if (is_dir($path)) {
            scan_files($path, $fileList, $root);
        } else {
            $fileList[] = $relative;
        }
    }
}

function clean_deleted_files($dir, $githubFiles, $skipList) {
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        if (in_array($item, $skipList)) continue;

        $path = "$dir/$item";

        if (is_dir($path)) {
            clean_deleted_files($path, $githubFiles, $skipList);
            // remove empty dirs
            if (!count(array_diff(scandir($path), ['.', '..']))) rmdir($path);
        } else {
            $relative = ltrim(str_replace(__DIR__, '', $path), '/\\');
            if (!in_array($relative, $githubFiles)) {
                @unlink($path);
                echo "🗑 Deleted old file: $relative\n";
            }
        }
    }
}

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
            echo "✅ Updated: $file\n";
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
