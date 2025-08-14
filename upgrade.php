<?php
// CONFIG
$githubUser   = 'devyarn-subhajit';
$repoName     = 'wp-git';
$branch       = 'main';
// $githubToken  = 'ghp_xxxxxxxxxxxxxxxxxxxxxxx';

// Files/folders to skip
$skipList = [
    'upgrade.php',
    '.env',
    'db',
    'uploads'
];

// === 0. Check version ===
$localVersionFile = __DIR__ . '/version.txt';
$localVersion = file_exists($localVersionFile) ? trim(file_get_contents($localVersionFile)) : '0.0.0';

$remoteVersionUrl = "https://raw.githubusercontent.com/$githubUser/$repoName/$branch/version.txt";
$opts = [
    "http" => [
        "header" => [
            "User-Agent: PHP",
            // "Authorization: token $githubToken"
        ]
    ]
];
$context = stream_context_create($opts);

$remoteVersion = @file_get_contents($remoteVersionUrl, false, $context);
if ($remoteVersion === false) {
    exit("❌ Failed to fetch remote version.\n");
}
$remoteVersion = trim($remoteVersion);

echo "📄 Local version: $localVersion\n";
echo "📄 Remote version: $remoteVersion\n";

if (version_compare($localVersion, $remoteVersion, '>=')) {
    exit("✅ Already up to date.\n");
}

// === Continue with update if remote version is newer ===
// (The rest is same as previous script — download zip, extract, copy, cleanup)

// TEMP PATHS
$tmpZip = __DIR__ . '/update.zip';
$tmpDir = __DIR__ . '/tmp_update';

// Download ZIP
$zipUrl = "https://api.github.com/repos/$githubUser/$repoName/zipball/$branch";
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

// Sync files
sync_directories($rootExtractedFolder, __DIR__, $skipList);

// Cleanup
rrmdir($tmpDir);
unlink($tmpZip);

echo "✅ Update complete to version $remoteVersion!\n";

// Helper functions (same as before)
function sync_directories($src, $dst, $skipList = []) {
    $skipPaths = array_map(function($item) use ($dst) {
        return realpath($dst . '/' . $item);
    }, $skipList);

    $dir = opendir($src);
    @mkdir($dst);
    while(false !== ($file = readdir($dir))) {
        if ($file != '.' && $file != '..') {
            $srcPath = "$src/$file";
            $dstPath = "$dst/$file";

            if (should_skip($dstPath, $skipPaths)) {
                continue;
            }

            if (is_dir($srcPath)) {
                sync_directories($srcPath, $dstPath, $skipList);
            } else {
                copy($srcPath, $dstPath);
            }
        }
    }
    closedir($dir);

    $dstFiles = array_diff(scandir($dst), ['.', '..']);
    foreach ($dstFiles as $file) {
        $srcPath = "$src/$file";
        $dstPath = "$dst/$file";
        if (!file_exists($srcPath) && !should_skip($dstPath, $skipPaths)) {
            is_dir($dstPath) ? rrmdir($dstPath) : unlink($dstPath);
        }
    }
}

function should_skip($path, $skipPaths) {
    $realPath = realpath($path);
    foreach ($skipPaths as $skip) {
        if ($skip && strpos($realPath, $skip) === 0) {
            return true;
        }
    }
    return false;
}

function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = "$dir/$file";
        is_dir($path) ? rrmdir($path) : unlink($path);
    }
    rmdir($dir);
}
