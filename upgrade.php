<?php
// CONFIG
$githubUser   = 'devyarn-subhajit';
$repoName     = 'wp-git';
$branch       = 'main';
// $githubToken  = 'ghp_xxxxxxxxxxxxxxxxxxxxxxx'; // for private repos

// Files/folders to skip (preserve these)
$skipList = [
    '.env',
    'db',
    'upgrade.php' // we will handle self-update separately
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
        // For private repos: "Authorization: token $githubToken\r\n"
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

// 1️⃣ COPY NEW FILES SAFELY (skip .env, db, upgrade.php)
sync_directories($rootExtractedFolder, __DIR__, $skipList);

// 2️⃣ HANDLE upgrade.php SELF-UPDATE
$newUpgradePath = "$rootExtractedFolder/upgrade.php";
if (file_exists($newUpgradePath)) {
    copy($newUpgradePath, __DIR__ . '/upgrade.php.new');
    echo "⚡ upgrade.php updated to temporary file. Replace after script finishes.\n";
}

// Cleanup
rrmdir($tmpDir);
unlink($tmpZip);

echo "✅ Update complete!\n";

if (file_exists(__DIR__ . '/upgrade.php.new')) {
    rename(__DIR__ . '/upgrade.php.new', __DIR__ . '/upgrade.php');
    echo "⚡ upgrade.php successfully replaced.\n";
}

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

echo "new update";