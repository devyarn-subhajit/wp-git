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
    'upgrade.php',
    'tmp_update',
    'update.zip'
];

echo "=== WP-GIT UPGRADE START ===\n";
echo "GitHub Repo: $githubUser/$repoName ($branch)\n";

// TEMP PATHS
$tmpZip = __DIR__ . '/update.zip';
$tmpDir = __DIR__ . '/tmp_update';

// Step 1: Download ZIP
echo "\n[1/5] Downloading update from GitHub...\n";
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
echo "✅ ZIP downloaded.\n";

// Step 2: Extract ZIP
echo "\n[2/5] Extracting update ZIP...\n";
$zip = new ZipArchive;
if ($zip->open($tmpZip) === TRUE) {
    if (!is_dir($tmpDir)) mkdir($tmpDir);
    $zip->extractTo($tmpDir);
    $zip->close();
    echo "✅ ZIP extracted.\n";
} else {
    exit("❌ Failed to open ZIP file.\n");
}

// Step 3: Find extracted root folder
echo "\n[3/5] Locating extracted root folder...\n";
$extractedFolders = glob($tmpDir . '/*', GLOB_ONLYDIR);
if (!$extractedFolders || !isset($extractedFolders[0])) {
    rrmdir($tmpDir);
    unlink($tmpZip);
    exit("❌ No folder found in extracted ZIP.\n");
}
$rootExtractedFolder = $extractedFolders[0];
echo "✅ Found extracted folder.\n";

// Step 4: Clean local directory except skip list
echo "\n[4/5] Cleaning local directory...\n";
clean_directory(__DIR__, $skipList);
echo "✅ Clean complete.\n";

// Step 5: Copy files from GitHub
echo "\n[5/5] Copying new files from GitHub...\n";
sync_directories($rootExtractedFolder, __DIR__, $skipList);
echo "✅ Files copied.\n";

// Cleanup
rrmdir($tmpDir);
unlink($tmpZip);

echo "\n✅ Update complete!\n=== WP-GIT UPGRADE END ===\n";

// === FUNCTIONS ===

function clean_directory($dir, $skipList)
{
    foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
        $path = "$dir/$item";
        if (in_array($item, $skipList)) continue;
        is_dir($path) ? rrmdir($path) : unlink($path);
    }
}

function sync_directories($src, $dst, $skipList = [])
{
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

function rrmdir($dir)
{
    if (!is_dir($dir)) return;
    foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
        $path = "$dir/$file";
        is_dir($path) ? rrmdir($path) : @unlink($path);
    }
    rmdir($dir);
}
