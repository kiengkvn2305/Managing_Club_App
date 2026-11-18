<?php

namespace App\Support;

use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class MySqlSchemaExporter
{
    /** Compile migrations without opening or changing any database. */
    public function render(): string
    {
        $connection = new class(fn () => throw new RuntimeException('SQL export must not connect to a database.'), 'fcms', '', [
            'name' => 'mysql_export',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'engine' => 'InnoDB',
        ]) extends MySqlConnection
        {
            public function isMaria()
            {
                return false;
            }

            public function getServerVersion(): string
            {
                return '8.4.0';
            }
        };

        $resolver = new ConnectionResolver(['mysql_export' => $connection]);
        $resolver->setDefaultConnection('mysql_export');
        $repository = new DatabaseMigrationRepository($resolver, 'migrations');
        $originalSchema = Schema::getFacadeRoot();
        $files = glob(database_path('migrations/*.php')) ?: [];
        sort($files, SORT_STRING);

        $sql = "-- FCMS PRO: MySQL 8.4 schema (InnoDB / utf8mb4).\n"
            ."-- Generated from Laravel migrations by: php artisan db:export-mysql\n"
            ."-- Import into a selected EMPTY database. No application data or passwords.\n"
            ."-- Contains implemented core tables only; see docs/DATABASE.md.\n"
            ."-- Migration records prevent Laravel from creating these tables again.\n\n"
            ."SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;\n"
            ."SET time_zone = '+00:00';\n\n";

        try {
            Schema::swap($connection->getSchemaBuilder());
            $sql .= "-- Laravel migration repository\n";
            $sql .= $this->statements($connection->pretend(fn () => $repository->createRepository()));

            foreach ($files as $file) {
                $name = basename($file, '.php');
                if (! preg_match('/^[a-zA-Z0-9_]+$/D', $name)) {
                    throw new RuntimeException('Unsupported migration filename.');
                }

                $migration = require $file;
                if ($migration->getConnection() !== null) {
                    throw new RuntimeException('SQL export does not support migrations with a separate connection.');
                }

                $sql .= "-- {$name}\n";
                $sql .= $this->statements($connection->pretend(fn () => $migration->up()));
                $sql .= "insert into `migrations` (`migration`, `batch`) values ('{$name}', 1);\n\n";
            }
        } finally {
            Schema::swap($originalSchema);
        }

        return $sql;
    }

    private function statements(array $queries): string
    {
        $sql = '';
        foreach ($queries as $query) {
            if ($query['bindings'] !== []) {
                throw new RuntimeException('SQL export requires review for migrations containing bound data queries.');
            }

            $statement = $query['query'];
            if (str_starts_with($statement, 'create table ')) {
                $statement = preg_replace('/\((?=`)/', "(\n  ", $statement, 1);
                $statement = preg_replace('/, (?=`|primary key)/', ",\n  ", $statement);
                $statement = str_replace(') default character set', "\n) default character set", $statement);
            }
            $sql .= $statement.";\n\n";
        }

        return $sql;
    }
}
