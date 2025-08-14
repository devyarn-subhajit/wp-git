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

$export_path = __DIR__ . '/db/latest.sql';
$db_dir = __DIR__ . '/db';

// Create db folder if it doesn't exist
if (!is_dir($db_dir)) {
    if (!mkdir($db_dir, 0755, true)) {
        exit("❌ Failed to create db directory.\n");
    }
}

echo "🟡 Exporting database to: $export_path\n";

// Build mysqldump command
$baseCmd = "mysqldump -h $db_host -P $db_port -u $db_user";
$baseCmd .= ($db_pass === '') ? " $db_name" : " -p$db_pass $db_name";
$command = "$baseCmd > \"$export_path\"";

// Execute
exec($command, $output, $return_var);

// Result
if ($return_var === 0) {
    echo "✅ Database export successful.\n";
} else {
    echo "❌ Database export failed. Error code: $return_var\n";
}
