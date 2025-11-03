<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "✅ Verifikasi kolom baru di Project model:\n\n";

$fillable = (new \App\Models\Project)->getFillable();

$newFields = ['domain', 'impact_statement', 'context', 'role', 'problem', 'solution', 'integration', 'result'];

foreach ($newFields as $field) {
    $exists = in_array($field, $fillable);
    echo ($exists ? '✅' : '❌') . " {$field}: " . ($exists ? 'ADA' : 'TIDAK ADA') . "\n";
}

echo "\n✅ Total fillable fields: " . count($fillable) . "\n";
