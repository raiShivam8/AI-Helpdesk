<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/test-db', function () {
    $data = [];
    $data['php_version'] = PHP_VERSION;
    $data['env_db_connection'] = env('DB_CONNECTION');
    $data['env_db_host'] = env('DB_HOST');
    $data['env_database_url_present'] = !empty(env('DATABASE_URL'));
    $data['config_host'] = config('database.connections.pgsql.host');
    $data['config_db'] = config('database.connections.pgsql.database');
    $data['config_user'] = config('database.connections.pgsql.username');
    $data['config_sslmode'] = config('database.connections.pgsql.sslmode');

    $config = config('database.connections.pgsql');
    $pass = (string) ($config['password'] ?? '');
    $data['password_length'] = strlen($pass);
    $data['password_preview'] = strlen($pass) > 4 ? substr($pass, 0, 2) . '...' . substr($pass, -2) : 'EMPTY_OR_SHORT';

    // Test different SSL modes and connection options
    $host = $config['host'];
    $port = $config['port'] ?? 5432;
    $db = $config['database'];
    $user = $config['username'];

    $modes = ['require', 'prefer', 'allow', 'disable'];
    $testResults = [];
    $workingPdo = null;

    foreach ($modes as $mode) {
        $dsn = "pgsql:host={$host};port={$port};dbname='{$db}';sslmode={$mode}";
        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $testResults[$mode] = 'SUCCESS';
            $workingPdo = $pdo;
            break;
        } catch (\Throwable $e) {
            $testResults[$mode] = $e->getMessage();
        }
    }
    $data['ssl_mode_tests'] = $testResults;

    if ($workingPdo) {
        $data['status'] = 'CONNECTED_SUCCESSFULLY';
        try {
            $stmt = $workingPdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public'");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $data['tables_count'] = count($tables);
            $data['tables'] = $tables;

            if (count($tables) === 0 || request()->query('migrate') === 'yes') {
                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
                $data['migrate_output'] = \Illuminate\Support\Facades\Artisan::output();

                \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
                $data['seed_output'] = \Illuminate\Support\Facades\Artisan::output();
            }

            $userStmt = $workingPdo->query("SELECT count(*) FROM users");
            $data['users_count'] = $userStmt ? $userStmt->fetchColumn() : 0;
        } catch (\Throwable $te) {
            $data['query_error'] = $te->getMessage();
        }
    } else {
        $data['status'] = 'CONNECTION_FAILED';
        $data['first_error'] = reset($testResults);
    }

    return response()->json($data);
});
