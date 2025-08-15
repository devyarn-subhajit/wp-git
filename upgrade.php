<?php
// CONFIG
$githubUser   = 'devyarn-subhajit';
$repoName     = 'wp-git';
$branch       = 'main';
// $githubToken  = 'ghp_xxxxxxxxxxxxxxxxxxxxxxx'; // For private repos

// Files/folders to skip (preserve these)
$skipList = [
    '.env',
    'db',
];

echo "⬇ Downloading update from GitHub...\n";

// TEMP PATHS
$tmpZip = __DIR__ . '/update.zip';
$tmpDir = __DIR__ . '/tmp_update';

// Download ZIP
$zipUrl = "https://api.github.com/repos/$githubUser/$repoName/zipball/$branch";
$opts = [
    "http" => [
        "header" => [
            "User-Agent: PHP",
            // "Authorization: token $githubToken"
        ]
    ]
];
$context = stream_context_create($opts);

if (!file_put_contents($tmpZip, file_get_contents($zipUrl, false, $context))) {
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
$rootExtractedFolder = glob($tmpDir . '/*', GLOB_ONLYDIR)[0];

// 1️⃣ DELETE ALL EXCEPT SKIP LIST
clean_directory(__DIR__, $skipList);

// 2️⃣ COPY NEW FILES
sync_directories($rootExtractedFolder, __DIR__, $skipList);

// Cleanup
rrmdir($tmpDir);
unlink($tmpZip);

echo "✅ Update complete!\n";

// === FUNCTIONS ===

function clean_directory($dir, $skipList) {
    foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
        $path = "$dir/$item";
        if (in_array($item, $skipList)) continue; // skip preserved
        is_dir($path) ? rrmdir($path) : unlink($path);
    }
}

function sync_directories($src, $dst, $skipList = []) {
    $dir = opendir($src);
    @mkdir($dst, 0777, true);

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
        is_dir($path) ? rrmdir($path) : unlink($path);
    }
    rmdir($dir);
}
