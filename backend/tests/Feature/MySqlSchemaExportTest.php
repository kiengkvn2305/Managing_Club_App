<?php

namespace Tests\Feature;

use App\Support\MySqlSchemaExporter;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MySqlSchemaExportTest extends TestCase
{
    public function test_committed_sql_matches_migrations_without_changing_the_database(): void
    {
        $originalSchema = Schema::getFacadeRoot();
        $sql = app(MySqlSchemaExporter::class)->render();

        $this->assertSame($originalSchema->getConnection(), Schema::getFacadeRoot()->getConnection());
        $this->assertSame(file_get_contents(database_path('sql/fcms_pro_mysql.sql')), $sql);
        $this->assertSame(22, preg_match_all('/^create table /m', $sql));
        $this->assertSame(22, substr_count($sql, 'engine = InnoDB;'));
        $this->assertSame(22, substr_count($sql, "collate 'utf8mb4_unicode_ci'"));

        foreach (glob(database_path('migrations/*.php')) as $file) {
            $name = basename($file, '.php');
            $this->assertStringContainsString("values ('{$name}', 1);", $sql);
        }

        $this->assertStringContainsString('foreign key (`club_id`, `team_id`) references `teams` (`club_id`, `id`) on delete restrict', $sql);
        $this->assertStringContainsString('unique `teams_club_id_id_unique`(`club_id`, `id`)', $sql);
        $this->assertStringContainsString('unique `player_profiles_club_id_team_id_current_jersey_unique`(`club_id`, `team_id`, `current_jersey`)', $sql);
        $this->assertDoesNotMatchRegularExpression('/\b(drop|truncate|delete|foreign_key_checks)\b(?! restrict)/i', $sql);
        preg_match_all('/^insert into `([^`]+)`/m', $sql, $inserts);
        $this->assertSame(['migrations'], array_values(array_unique($inserts[1])));
    }
}
