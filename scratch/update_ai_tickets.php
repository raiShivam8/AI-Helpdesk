<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Database\Seeders\AiAgentSeeder;

$aiAgent = User::withTrashed()->where('email', AiAgentSeeder::EMAIL)->first();
if (!$aiAgent) {
    echo "AI Agent user not found.\n";
    exit(1);
}

$updated = DB::table('tickets')
    ->whereNotNull('ai_resolved_at')
    ->where(function ($q) use ($aiAgent) {
        $q->whereNull('assigned_agent_id')
          ->orWhere('assigned_agent_id', '!=', $aiAgent->id);
    })
    ->update(['assigned_agent_id' => $aiAgent->id]);

echo "Updated {$updated} AI-resolved tickets with assigned_agent_id = {$aiAgent->id}.\n";

$aiCount = DB::table('tickets')->where('assigned_agent_id', $aiAgent->id)->count();
$aiResolvedCount = DB::table('tickets')->whereNotNull('ai_resolved_at')->count();
echo "Total tickets assigned to AI Agent: {$aiCount}\n";
echo "Total tickets resolved by AI: {$aiResolvedCount}\n";
