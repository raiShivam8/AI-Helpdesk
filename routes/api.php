<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/test-db', function () {
    $data = [];
    $data['php_version'] = PHP_VERSION;
    $data['render_region'] = env('RENDER_REGION', 'NOT_SET');
    $data['env_db_connection'] = env('DB_CONNECTION');
    $data['env_db_host'] = env('DB_HOST');
    $data['env_database_url_present'] = !empty(env('DATABASE_URL'));
    $data['env_database_url_masked'] = preg_replace('/:[^:@]+@/', ':***@', (string) env('DATABASE_URL'));
    $data['config_host'] = config('database.connections.pgsql.host');
    $data['config_db'] = config('database.connections.pgsql.database');
    $data['config_user'] = config('database.connections.pgsql.username');
    $data['config_sslmode'] = config('database.connections.pgsql.sslmode');
    $data['ca_cert_file'] = file_exists('/etc/ssl/certs/ca-certificates.crt');
    $data['resolv_conf'] = @file_get_contents('/etc/resolv.conf');

    // Test DNS for bare host and regional hosts
    $dnsTests = [
        'bare' => 'dpg-da2mujjl550s73ca93bg-a',
        'oregon' => 'dpg-da2mujjl550s73ca93bg-a.oregon-postgres.render.com',
        'frankfurt' => 'dpg-da2mujjl550s73ca93bg-a.frankfurt-postgres.render.com',
        'ohio' => 'dpg-da2mujjl550s73ca93bg-a.ohio-postgres.render.com',
        'singapore' => 'dpg-da2mujjl550s73ca93bg-a.singapore-postgres.render.com',
        'virginia' => 'dpg-da2mujjl550s73ca93bg-a.virginia-postgres.render.com',
    ];
    $dnsResults = [];
    foreach ($dnsTests as $label => $h) {
        $ip = @gethostbyname($h);
        $dnsResults[$label] = ($ip !== $h) ? $ip : 'NOT_RESOLVED';
    }
    $data['dns_results'] = $dnsResults;

    $config = config('database.connections.pgsql');
    $pass = (string) ($config['password'] ?? '');
    $data['password_length'] = strlen($pass);
    $data['password_preview'] = strlen($pass) > 4 ? substr($pass, 0, 2) . '...' . substr($pass, -2) : 'EMPTY_OR_SHORT';

    $db = $config['database'];
    $user = $config['username'];
    $port = $config['port'] ?? 5432;

    $hostsToTest = array_filter(array_unique([
        'dpg-da2mujjl550s73ca93bg-a',
        $config['host'],
        'dpg-da2mujjl550s73ca93bg-a.oregon-postgres.render.com',
        'dpg-da2mujjl550s73ca93bg-a.frankfurt-postgres.render.com',
        'dpg-da2mujjl550s73ca93bg-a.ohio-postgres.render.com',
    ]));

    $passwordsToTest = array_unique(array_filter([$pass, rawurldecode($pass), urldecode($pass)]));
    $modes = ['disable', 'prefer', 'require', 'allow'];
    $testResults = [];
    $workingPdo = null;
    $workingHost = null;

    foreach ($hostsToTest as $testHost) {
        foreach ($passwordsToTest as $pIndex => $pCandidate) {
            foreach ($modes as $mode) {
                $key = "host_{$testHost}_pwd_{$pIndex}_ssl_{$mode}";
                $dsn = "pgsql:host={$testHost};port={$port};dbname={$db};sslmode={$mode}";
                try {
                    $pdo = new PDO($dsn, $user, $pCandidate, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => 4,
                    ]);
                    $testResults[$key] = 'SUCCESS';
                    $workingPdo = $pdo;
                    $workingHost = $testHost;
                    break 3;
                } catch (\Throwable $e) {
                    $testResults[$key] = $e->getMessage();
                }
            }
        }
    }
    $data['connection_matrix'] = $testResults;

    $data['connection_matrix'] = $testResults;

    // Test active Laravel connection
    $activeConn = config('database.default');
    $data['active_connection_name'] = $activeConn;
    $data['active_driver'] = config("database.connections.{$activeConn}.driver");

    try {
        $activePdo = DB::connection()->getPdo();
        $data['active_connection_status'] = 'CONNECTED';
        
        $tableNames = \Illuminate\Support\Facades\Schema::getTableListing();
        $data['tables_count'] = count($tableNames);
        $data['tables'] = $tableNames;

        if (count($tableNames) === 0 || request()->query('migrate') === 'yes') {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $data['migrate_output'] = \Illuminate\Support\Facades\Artisan::output();

            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
            $data['seed_output'] = \Illuminate\Support\Facades\Artisan::output();
        }

        $data['users_count'] = \App\Models\User::count();
        $admin = \App\Models\User::where('email', 'admin@gmail.com')->first(['id', 'email', 'name', 'role']);
        $data['admin_user_exists'] = !empty($admin);
        $data['status'] = 'READY';
    } catch (\Throwable $ae) {
        $data['active_connection_status'] = 'FAILED';
        $data['active_connection_error'] = $ae->getMessage();
        $data['status'] = $workingPdo ? 'PGSQL_DIRECT_ONLY' : 'ALL_FAILED';
    }

    return response()->json($data);
});
