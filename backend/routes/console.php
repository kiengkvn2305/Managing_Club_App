<?php

use App\Support\MySqlSchemaExporter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('db:export-mysql', function () {
    $sql = app(MySqlSchemaExporter::class)->render();
    $directory = database_path('sql');
    if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
        throw new RuntimeException('Cannot create SQL export directory.');
    }
    $path = $directory.'/fcms_pro_mysql.sql';
    if (file_put_contents($path, $sql) === false) {
        throw new RuntimeException('Cannot write SQL export.');
    }
    $this->info('Exported MySQL schema: '.$path);
})->purpose('Export a MySQL 8.4 schema from migrations without connecting to a database');
