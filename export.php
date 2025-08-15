<?php
// ==== LOAD ENV ====
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    exit("❌ .env file not found at: $envFile\n");
}

$env = parse_ini_file($envFile);

// ==== CONFIG ====
$db_name = $env['DB_NAME'] ?? 'local';
$db_user = $env['DB_USER'] ?? 'root';
$db_pass = $env['DB_PASS'] ?? '';
$db_host = $env['DB_HOST'] ?? 'localhost';
$db_port = $env['DB_PORT'] ?? 3306;

$db_dir       = __DIR__ . '/db';
$latest_path  = $db_dir . '/latest.sql';
$backup_dir   = $db_dir . '/backup/' . $db_port . '-' . date('Y-m-d_H-i-s');
$log_file     = $db_dir . '/backup_log.txt';
$max_backups  = 3; // keep only latest 3 backups

// ==== CREATE DIRECTORIES ====
foreach ([$db_dir, $db_dir . '/backup', $backup_dir] as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            exit("❌ Failed to create directory: $dir\n");
        }
    }
}

// ==== LOG FUNCTION ====
function logMessage($message, $log_file) {
    $timestamp = date("[Y-m-d H:i:s]");
    file_put_contents($log_file, "$timestamp $message\n", FILE_APPEND);
    echo "$message\n";
}

// ==== EXPORT FUNCTION ====
function exportDatabase($host, $port, $user, $pass, $name, $outputPath) {
    $baseCmd = "mysqldump -h $host -P $port -u $user";
    $baseCmd .= ($pass === '') ? " $name" : " -p$pass $name";
    $command = "$baseCmd > \"$outputPath\"";

    exec($command, $output, $return_var);
    return $return_var === 0;
}

// ==== EXPORT LATEST ====
logMessage("🟡 Exporting latest database snapshot: $latest_path", $log_file);
if (exportDatabase($db_host, $db_port, $db_user, $db_pass, $db_name, $latest_path)) {
    logMessage("✅ Latest database export successful.", $log_file);
} else {
    logMessage("❌ Latest database export failed.", $log_file);
    exit;
}

// ==== EXPORT BACKUP ====
$backupFile = $backup_dir . '/backup.sql';
logMessage("🟡 Creating backup: $backupFile", $log_file);
if (exportDatabase($db_host, $db_port, $db_user, $db_pass, $db_name, $backupFile)) {
    logMessage("✅ Backup created successfully.", $log_file);
} else {
    logMessage("❌ Backup creation failed.", $log_file);
}

// ==== CLEANUP OLD BACKUPS ====
$backupFolders = glob($db_dir . '/backup/*', GLOB_ONLYDIR);
usort($backupFolders, function($a, $b) {
    return filemtime($b) <=> filemtime($a); // newest first
});

if (count($backupFolders) > $max_backups) {
    $oldFolders = array_slice($backupFolders, $max_backups);
    foreach ($oldFolders as $folder) {
        array_map('unlink', glob("$folder/*.*"));
        rmdir($folder);
        logMessage("🗑️ Deleted old backup folder: $folder", $log_file);
    }
}

logMessage("🎉 Backup process completed.", $log_file);
