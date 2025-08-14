<?php
// === LOAD ENV ===
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    exit("❌ .env file not found at: $envPath\n");
}
$env = parse_ini_file($envPath);

$dbHost = $env['DB_HOST'];
$dbUser = $env['DB_USER'];
$dbPass = $env['DB_PASS'];
$dbName = $env['DB_NAME'];
$dbPort = $env['DB_PORT'] ?? 3306;

// === CONFIG ===
$sqlFile = __DIR__ . '/db/latest.sql';
$wpPhar  = __DIR__ . '/wp.phar';

// === VALIDATION ===
if (!file_exists($sqlFile)) {
    die("❌ SQL file not found at $sqlFile\n");
}
if (!file_exists($wpPhar)) {
    die("❌ wp-cli.phar not found at $wpPhar\n");
}

// === STEP 0: Get new domain from current DB before import ===
echo "🔌 Connecting to current DB to get target domain...\n";
$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, (int)$dbPort);
if ($mysqli->connect_error) {
    exit("❌ MySQL connection failed: " . $mysqli->connect_error . "\n");
}
$result = $mysqli->query("SELECT option_value FROM wp_options WHERE option_name = 'siteurl' LIMIT 1");
$wpReplace = ($result && $row = $result->fetch_assoc()) ? $row['option_value'] : null;
$mysqli->close();

if (!$wpReplace) {
    die("❌ Could not retrieve target domain from DB.\n");
}
echo "🌐 Target domain (replace with): $wpReplace\n";

// === STEP 1: Detect old domain from SQL file ===
echo "🔍 Checking old domain in SQL dump...\n";
$sqlContent = file_get_contents($sqlFile);
preg_match("/\(\d+,'siteurl','(.*?)'/i", $sqlContent, $matches);
$wpSearch = $matches[1] ?? null;

if (!$wpSearch) {
    die("❌ Could not determine old domain from SQL file.\n");
}
echo "🔎 Old domain found in dump: $wpSearch\n";

// === STEP 2: Import DB dump ===
echo "🚀 Importing database from $sqlFile...\n";
$importCmd = "mysql -h $dbHost -P $dbPort -u $dbUser " .
             (!empty($dbPass) ? "-p$dbPass " : "") .
             "$dbName < \"$sqlFile\"";
exec($importCmd, $output, $status);
if ($status === 0) {
    echo "✅ Database imported successfully.\n";
} else {
    die("❌ Failed to import database. Exit code: $status\n");
}

// === STEP 3: Conditional Search & Replace ===
if ($wpSearch === $wpReplace) {
    echo "⚠️ Old and new domains are the same. Skipping search-replace.\n";
} else {
    echo "🔍 Replacing URLs: $wpSearch → $wpReplace\n";
    $replaceCmd = "php \"$wpPhar\" search-replace \"$wpSearch\" \"$wpReplace\" --skip-columns=guid --all-tables";
    exec($replaceCmd, $output, $status);

    echo implode("\n", $output) . "\n";
    if ($status === 0) {
        echo "✅ Search & replace completed.\n";
    } else {
        echo "❌ Search & replace failed. Exit code: $status\n";
    }
}
