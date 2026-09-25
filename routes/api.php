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

    try {
        DB::connection('pgsql')->getPdo();
        $data['status'] = 'CONNECTED_SUCCESSFULLY';

        $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema='public'");
        $tableList = [];
        foreach ($tables as $t) {
            $arr = (array) $t;
            $tableList[] = $arr['table_name'] ?? 'unknown';
        }
        $data['tables_count'] = count($tableList);
        $data['tables'] = $tableList;

        if (count($tableList) === 0 || request()->query('migrate') === 'yes') {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $data['migrate_output'] = \Illuminate\Support\Facades\Artisan::output();

            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
            $data['seed_output'] = \Illuminate\Support\Facades\Artisan::output();
        }

        try {
            $data['users_count'] = \App\Models\User::count();
            $data['admin_exists'] = \App\Models\User::where('email', 'admin@gmail.com')->exists();
        } catch (\Throwable $ue) {
            $data['users_error'] = $ue->getMessage();
        }
    } catch (\Throwable $e) {
        $data['status'] = 'CONNECTION_FAILED';
        $data['error'] = $e->getMessage();
        $data['error_code'] = $e->getCode();
        $data['error_class'] = get_class($e);
    }

    return response()->json($data);
});
