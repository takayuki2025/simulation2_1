<?php
// .envファイルから環境変数を読み込む（dotenvライブラリが使える前提）
// プロジェクトのルートディレクトリにあるautoload.phpを読み込むように修正
require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$host = $_ENV['DB_HOST'];
$db   = $_ENV['DB_DATABASE'];
$user = $_ENV['DB_USERNAME'];
$pass = $_ENV['DB_PASSWORD'];
$charset = 'utf8mb4';

$dsn = "mysql:host=mysql;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "<h1>データベースに接続成功！</h1>";
    echo "<p>PHPのバージョン: " . phpversion() . "</p>";
    echo "<p>PDOドライバー: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "</p>";
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

?>