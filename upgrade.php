<?php
// CONFIG
$githubUser   = 'devyarn-subhajit';
$repoName     = 'wp-git';
$branch       = 'main';
// $githubToken  = 'ghp_xxxxxxxxxxxxxxxxxxxxxxx'; // For private repos

// Files/folders to skip (won't be replaced or deleted)
$skipList = [
    '.env',
    'db',
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

// Ensure remote version format is valid
if (!preg_match('/^\d+(\.\d+)*$/', $remoteVersion)) {
    exit("❌ Invalid remote version format in version.txt\n");
}

echo "📄 Local version: $localVersion\n";
echo "📄 Remote version: $remoteVersion\n";

if (version_compare($localVersion, $remoteVersion, '>=')) {
    exit("✅ Already up to date.\n");
}

// === Continue with update ===
echo "⬇ Downloading update...\n";

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

// Ensure version.txt exists in the update
if (!file_exists($rootExtractedFolder . '/version.txt')) {
    rrmdir($tmpDir);
    unlink($tmpZip);
    exit("❌ Update aborted — version.txt missing from repository!\n");
}

// Sync files
sync_directories($rootExtractedFolder, __DIR__, $skipList);

// Cleanup
rrmdir($tmpDir);
unlink($tmpZip);

echo "✅ Update complete to version $remoteVersion!\n";

// Helper functions
function sync_directories($src, $dst, $skipList = []) {
    $dir = opendir($src);
    @mkdir($dst, 0777, true);

    // Loop through source files
    while (false !== ($file = readdir($dir))) {
        if ($file == '.' || $file == '..') continue;

        $srcPath = "$src/$file";
        $dstPath = "$dst/$file";

        // Skip paths in skipList (except upgrade.php)
        if ($file !== 'upgrade.php' && is_in_skiplist($dstPath, $skipList)) {
            continue;
        }

        if (is_dir($srcPath)) {
            sync_directories($srcPath, $dstPath, $skipList);
        } else {
            copy($srcPath, $dstPath);
        }
    }
    closedir($dir);

    // Remove files/folders that are not in source (but keep version.txt and upgrade.php)
    foreach (array_diff(scandir($dst), ['.', '..']) as $file) {
        if (in_array($file, ['version.txt', 'upgrade.php'])) continue;
        $srcPath = "$src/$file";
        $dstPath = "$dst/$file";
        if (!file_exists($srcPath) && !is_in_skiplist($dstPath, $skipList)) {
            is_dir($dstPath) ? rrmdir($dstPath) : unlink($dstPath);
        }
    }
}

function is_in_skiplist($path, $skipList) {
    foreach ($skipList as $skip) {
        if (strpos($path, DIRECTORY_SEPARATOR . $skip) !== false) {
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
