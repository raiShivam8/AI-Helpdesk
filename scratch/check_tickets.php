<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tickets = DB::table('tickets')->select('id', 'status', 'assigned_agent_id', 'ai_resolved_at')->get();
foreach ($tickets as $t) {
    echo "ID: {$t->id} | Status: {$t->status} | Agent: " . json_encode($t->assigned_agent_id) . " | AI_Resolved: " . json_encode($t->ai_resolved_at) . "\n";
}

$aiUser = DB::table('users')->where('email', 'ai@system.local')->first();
echo "\nAI User: " . json_encode($aiUser) . "\n";
