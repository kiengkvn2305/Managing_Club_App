-- FCMS PRO: MySQL 8.4 schema (InnoDB / utf8mb4).
-- Generated from Laravel migrations by: php artisan db:export-mysql
-- Import into a selected EMPTY database. No application data or passwords.
-- Contains implemented core tables only; see docs/DATABASE.md.
-- Migration records prevent Laravel from creating these tables again.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '+00:00';

-- Laravel migration repository
create table `migrations` (
  `id` int unsigned not null auto_increment primary key,
  `migration` varchar(255) not null,
  `batch` int not null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

-- 0001_01_01_000000_create_users_table
create table `users` (
  `id` bigint unsigned not null auto_increment primary key,
  `name` varchar(255) not null,
  `email` varchar(255) not null,
  `email_verified_at` timestamp null,
  `password` varchar(255) not null,
  `remember_token` varchar(100) null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `users` add unique `users_email_unique`(`email`);

create table `password_reset_tokens` (
  `email` varchar(255) not null,
  `token` varchar(255) not null,
  `created_at` timestamp null,
  primary key (`email`)
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

create table `sessions` (
  `id` varchar(255) not null,
  `user_id` bigint unsigned null,
  `ip_address` varchar(45) null,
  `user_agent` text null,
  `payload` longtext not null,
  `last_activity` int not null,
  primary key (`id`)
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `sessions` add index `sessions_user_id_index`(`user_id`);

alter table `sessions` add index `sessions_last_activity_index`(`last_activity`);

insert into `migrations` (`migration`, `batch`) values ('0001_01_01_000000_create_users_table', 1);

-- 0001_01_01_000001_create_cache_table
create table `cache` (
  `key` varchar(255) not null,
  `value` mediumtext not null,
  `expiration` int not null,
  primary key (`key`)
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `cache` add index `cache_expiration_index`(`expiration`);

create table `cache_locks` (
  `key` varchar(255) not null,
  `owner` varchar(255) not null,
  `expiration` int not null,
  primary key (`key`)
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `cache_locks` add index `cache_locks_expiration_index`(`expiration`);

insert into `migrations` (`migration`, `batch`) values ('0001_01_01_000001_create_cache_table', 1);

-- 0001_01_01_000002_create_jobs_table
create table `jobs` (
  `id` bigint unsigned not null auto_increment primary key,
  `queue` varchar(255) not null,
  `payload` longtext not null,
  `attempts` tinyint unsigned not null,
  `reserved_at` int unsigned null,
  `available_at` int unsigned not null,
  `created_at` int unsigned not null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `jobs` add index `jobs_queue_index`(`queue`);

create table `job_batches` (
  `id` varchar(255) not null,
  `name` varchar(255) not null,
  `total_jobs` int not null,
  `pending_jobs` int not null,
  `failed_jobs` int not null,
  `failed_job_ids` longtext not null,
  `options` mediumtext null,
  `cancelled_at` int null,
  `created_at` int not null,
  `finished_at` int null,
  primary key (`id`)
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

create table `failed_jobs` (
  `id` bigint unsigned not null auto_increment primary key,
  `uuid` varchar(255) not null,
  `connection` text not null,
  `queue` text not null,
  `payload` longtext not null,
  `exception` longtext not null,
  `failed_at` timestamp not null default CURRENT_TIMESTAMP
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `failed_jobs` add unique `failed_jobs_uuid_unique`(`uuid`);

insert into `migrations` (`migration`, `batch`) values ('0001_01_01_000002_create_jobs_table', 1);

-- 2026_11_17_000001_create_club_platform
alter table `users` add `is_system_admin` tinyint(1) not null default '0';

alter table `users` add `is_active` tinyint(1) not null default '1';

create table `clubs` (
  `id` bigint unsigned not null auto_increment primary key,
  `name` varchar(120) not null,
  `slug` varchar(160) not null,
  `description` text null,
  `city` varchar(120) null,
  `home_ground` varchar(160) null,
  `format` enum('5', '7', '11') not null default '7',
  `status` enum('active', 'archived') not null default 'active',
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `clubs` add unique `clubs_slug_unique`(`slug`);

create table `club_members` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `user_id` bigint unsigned not null,
  `role` enum('owner', 'co_owner', 'general_manager', 'treasurer', 'hr_manager', 'equipment_manager', 'sporting_manager', 'member') not null,
  `status` enum('active', 'inactive') not null default 'active',
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `club_members` add constraint `club_members_club_id_foreign` foreign key (`club_id`) references `clubs` (`id`) on delete restrict;

alter table `club_members` add constraint `club_members_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete restrict;

alter table `club_members` add unique `club_members_club_id_user_id_unique`(`club_id`, `user_id`);

alter table `club_members` add index `club_members_user_id_status_index`(`user_id`, `status`);

create table `teams` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `name` varchar(120) not null,
  `format` enum('5', '7', '11') not null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `teams` add constraint `teams_club_id_foreign` foreign key (`club_id`) references `clubs` (`id`) on delete restrict;

alter table `teams` add unique `teams_club_id_name_unique`(`club_id`, `name`);

alter table `teams` add unique `teams_club_id_id_unique`(`club_id`, `id`);

create table `player_profiles` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `user_id` bigint unsigned null,
  `team_id` bigint unsigned null,
  `full_name` varchar(120) not null,
  `email` varchar(255) null,
  `phone` varchar(25) null,
  `date_of_birth` date null,
  `jersey_number` smallint unsigned null,
  `current_jersey` smallint unsigned null,
  `position` varchar(5) null,
  `preferred_foot` enum('left', 'right', 'both') null,
  `status` enum('active', 'inactive', 'injured') not null default 'active',
  `notes` text null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `player_profiles` add constraint `player_profiles_club_id_foreign` foreign key (`club_id`) references `clubs` (`id`) on delete restrict;

alter table `player_profiles` add constraint `player_profiles_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete restrict;

alter table `player_profiles` add constraint `player_profiles_club_id_team_id_foreign` foreign key (`club_id`, `team_id`) references `teams` (`club_id`, `id`) on delete restrict;

alter table `player_profiles` add unique `player_profiles_club_id_email_unique`(`club_id`, `email`);

alter table `player_profiles` add unique `player_profiles_club_id_team_id_current_jersey_unique`(`club_id`, `team_id`, `current_jersey`);

alter table `player_profiles` add index `player_profiles_club_id_status_full_name_index`(`club_id`, `status`, `full_name`);

create table `club_invitations` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `invited_by` bigint unsigned not null,
  `email` varchar(255) not null,
  `role` varchar(30) not null,
  `token_hash` varchar(64) not null,
  `expires_at` timestamp not null,
  `accepted_at` timestamp null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `club_invitations` add constraint `club_invitations_club_id_foreign` foreign key (`club_id`) references `clubs` (`id`) on delete restrict;

alter table `club_invitations` add constraint `club_invitations_invited_by_foreign` foreign key (`invited_by`) references `users` (`id`) on delete restrict;

alter table `club_invitations` add unique `club_invitations_club_id_email_unique`(`club_id`, `email`);

alter table `club_invitations` add unique `club_invitations_token_hash_unique`(`token_hash`);

create table `audit_logs` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `actor_id` bigint unsigned not null,
  `action` varchar(60) not null,
  `subject_type` varchar(60) not null,
  `subject_id` bigint unsigned not null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `audit_logs` add constraint `audit_logs_club_id_foreign` foreign key (`club_id`) references `clubs` (`id`) on delete restrict;

alter table `audit_logs` add constraint `audit_logs_actor_id_foreign` foreign key (`actor_id`) references `users` (`id`) on delete restrict;

alter table `audit_logs` add index `audit_logs_club_id_created_at_index`(`club_id`, `created_at`);

insert into `migrations` (`migration`, `batch`) values ('2026_11_17_000001_create_club_platform', 1);

-- 2026_11_18_000001_create_match_lineups
create table `match_lineups` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `team_id` bigint unsigned not null,
  `opponent` varchar(120) not null,
  `match_date` date not null,
  `formation` varchar(120) null,
  `players` json not null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `match_lineups` add constraint `match_lineups_club_id_foreign` foreign key (`club_id`) references `clubs` (`id`) on delete restrict;

alter table `match_lineups` add constraint `match_lineups_club_id_team_id_foreign` foreign key (`club_id`, `team_id`) references `teams` (`club_id`, `id`) on delete restrict;

alter table `teams` add `captain_id` bigint unsigned null;

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000001_create_match_lineups', 1);

-- 2026_11_18_000002_add_membership_title
alter table `club_members` add `title` varchar(120) null;

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000002_add_membership_title', 1);

-- 2026_11_18_000003_soft_delete_clubs
alter table `clubs` add `deleted_at` timestamp null;

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000003_soft_delete_clubs', 1);

-- 2026_11_18_000004_club_formats_and_team_managers
alter table `clubs` add `formats` json null;

create table `team_managers` (
  `id` bigint unsigned not null auto_increment primary key,
  `team_id` bigint unsigned not null,
  `club_id` bigint unsigned not null,
  `membership_id` bigint unsigned not null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `team_managers` add constraint `team_managers_membership_id_foreign` foreign key (`membership_id`) references `club_members` (`id`) on delete restrict;

alter table `team_managers` add constraint `team_managers_club_id_team_id_foreign` foreign key (`club_id`, `team_id`) references `teams` (`club_id`, `id`) on delete restrict;

alter table `team_managers` add unique `team_managers_team_id_membership_id_unique`(`team_id`, `membership_id`);

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000004_club_formats_and_team_managers', 1);

-- 2026_11_18_000005_create_team_players
create table `team_players` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `team_id` bigint unsigned not null,
  `player_id` bigint unsigned not null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `team_players` add constraint `team_players_player_id_foreign` foreign key (`player_id`) references `player_profiles` (`id`) on delete restrict;

alter table `team_players` add constraint `team_players_club_id_team_id_foreign` foreign key (`club_id`, `team_id`) references `teams` (`club_id`, `id`) on delete restrict;

alter table `team_players` add unique `team_players_team_id_player_id_unique`(`team_id`, `player_id`);

insert into team_players (club_id, team_id, player_id) select club_id, team_id, id from player_profiles where team_id is not null;

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000005_create_team_players', 1);

-- 2026_11_18_000006_team_jersey_numbers
alter table `team_players` add `jersey_number` smallint unsigned null;

update team_players set jersey_number = (select jersey_number from player_profiles where player_profiles.id = team_players.player_id);

update player_profiles set current_jersey = null;

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000006_team_jersey_numbers', 1);

-- 2026_11_18_000007_club_finance_team_positions
alter table `team_players` add `position` varchar(5) null;

update team_players set position = (select position from player_profiles where player_profiles.id = team_players.player_id);

create table `finance_transactions` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `created_by` bigint unsigned not null,
  `type` enum('income', 'expense') not null,
  `amount` bigint unsigned not null,
  `occurred_on` date not null,
  `description` varchar(255) not null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `finance_transactions` add constraint `finance_transactions_club_id_foreign` foreign key (`club_id`) references `clubs` (`id`) on delete restrict;

alter table `finance_transactions` add constraint `finance_transactions_created_by_foreign` foreign key (`created_by`) references `users` (`id`) on delete restrict;

alter table `finance_transactions` add index `finance_transactions_club_id_occurred_on_index`(`club_id`, `occurred_on`);

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000007_club_finance_team_positions', 1);

-- 2026_11_18_000008_finance_receipts
alter table `finance_transactions` add `receipt_path` varchar(255) null;

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000008_finance_receipts', 1);

-- 2026_11_18_000009_club_activities
create table `club_celebrations` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `title` varchar(160) not null,
  `event_date` date not null,
  `message` text null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `club_celebrations` add constraint `club_celebrations_club_id_foreign` foreign key (`club_id`) references `clubs` (`id`) on delete restrict;

create table `team_trainings` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `team_id` bigint unsigned null,
  `team_name` varchar(120) not null,
  `training_date` date not null,
  `training_time` varchar(5) not null,
  `location` varchar(160) not null,
  `notes` text null,
  `attendance` json not null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `team_trainings` add constraint `team_trainings_club_id_foreign` foreign key (`club_id`) references `clubs` (`id`) on delete restrict;

create table `club_awards` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `player_id` bigint unsigned null,
  `player_name` varchar(120) not null,
  `award_year` smallint unsigned not null,
  `title` varchar(160) not null,
  `gift` varchar(255) null,
  `notes` text null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `club_awards` add constraint `club_awards_club_id_foreign` foreign key (`club_id`) references `clubs` (`id`) on delete restrict;

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000009_club_activities', 1);

-- 2026_11_18_000010_create_team_dues
create table `team_dues_rates` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `team_id` bigint unsigned not null,
  `effective_month` date not null,
  `amount` bigint unsigned not null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `team_dues_rates` add unique `team_dues_rates_team_id_effective_month_unique`(`team_id`, `effective_month`);

create table `team_membership_periods` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `team_id` bigint unsigned not null,
  `player_id` bigint unsigned not null,
  `joined_on` date not null,
  `left_on` date null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `team_membership_periods` add index `team_membership_periods_club_id_team_id_player_id_index`(`club_id`, `team_id`, `player_id`);

create table `team_dues_entries` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `team_id` bigint unsigned not null,
  `player_id` bigint unsigned not null,
  `month` date not null,
  `status` varchar(10) not null,
  `amount` bigint unsigned not null,
  `finance_transaction_id` bigint unsigned null,
  `created_by` bigint unsigned not null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `team_dues_entries` add unique `team_dues_entries_team_id_player_id_month_unique`(`team_id`, `player_id`, `month`);

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000010_create_team_dues', 1);

-- 2026_11_18_000011_activity_approvals
alter table `club_celebrations` add `approval_status` varchar(16) not null default 'approved';

alter table `club_celebrations` add `submitted_by` bigint unsigned null;

alter table `team_trainings` add `approval_status` varchar(16) not null default 'approved';

alter table `team_trainings` add `submitted_by` bigint unsigned null;

alter table `club_awards` add `approval_status` varchar(16) not null default 'approved';

alter table `club_awards` add `submitted_by` bigint unsigned null;

alter table `team_trainings` add `training_status` varchar(16) not null default 'scheduled';

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000011_activity_approvals', 1);

-- 2026_11_18_000012_dues_adjustments
create table `team_dues_adjustments` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `team_id` bigint unsigned not null,
  `player_id` bigint unsigned not null,
  `month` date not null,
  `old_status` varchar(10) not null,
  `new_status` varchar(10) not null,
  `amount` bigint unsigned not null,
  `old_transaction_id` bigint unsigned null,
  `new_transaction_id` bigint unsigned null,
  `reason` varchar(255) not null,
  `created_by` bigint unsigned not null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `team_dues_adjustments` add index `team_dues_adjustments_club_id_team_id_player_id_month_index`(`club_id`, `team_id`, `player_id`, `month`);

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000012_dues_adjustments', 1);

-- 2026_11_18_000013_add_profile_images
alter table `clubs` add `avatar_path` varchar(255) null;

alter table `clubs` add `background_path` varchar(255) null;

alter table `player_profiles` add `avatar_path` varchar(255) null;

alter table `users` add `avatar_path` varchar(255) null;

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000013_add_profile_images', 1);

-- 2026_11_18_000014_create_planned_expenses
create table `planned_expenses` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `description` varchar(255) not null,
  `amount` bigint unsigned null,
  `notes` text null,
  `effective_from` date not null,
  `effective_to` date null,
  `cancelled` tinyint(1) not null default '0',
  `created_by` bigint unsigned not null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `planned_expenses` add constraint `planned_expenses_club_id_foreign` foreign key (`club_id`) references `clubs` (`id`) on delete restrict;

alter table `planned_expenses` add constraint `planned_expenses_created_by_foreign` foreign key (`created_by`) references `users` (`id`) on delete restrict;

alter table `planned_expenses` add index `planned_expenses_club_id_effective_from_effective_to_index`(`club_id`, `effective_from`, `effective_to`);

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000014_create_planned_expenses', 1);

-- 2026_11_18_000015_club_discovery_friendlies
alter table `clubs` add `is_discoverable` tinyint(1) not null default '0';

create table `friendly_invitations` (
  `id` bigint unsigned not null auto_increment primary key,
  `source_club_id` bigint unsigned not null,
  `target_club_id` bigint unsigned not null,
  `pair_key` varchar(64) not null,
  `pending_key` varchar(10) null,
  `status` varchar(16) not null,
  `match_date` date not null,
  `match_time` varchar(5) null,
  `location` varchar(160) null,
  `message` text null,
  `created_by` bigint unsigned not null,
  `responded_by` bigint unsigned null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `friendly_invitations` add constraint `friendly_invitations_source_club_id_foreign` foreign key (`source_club_id`) references `clubs` (`id`) on delete restrict;

alter table `friendly_invitations` add constraint `friendly_invitations_target_club_id_foreign` foreign key (`target_club_id`) references `clubs` (`id`) on delete restrict;

alter table `friendly_invitations` add constraint `friendly_invitations_created_by_foreign` foreign key (`created_by`) references `users` (`id`) on delete restrict;

alter table `friendly_invitations` add constraint `friendly_invitations_responded_by_foreign` foreign key (`responded_by`) references `users` (`id`) on delete restrict;

alter table `friendly_invitations` add unique `friendly_invitations_pair_key_pending_key_unique`(`pair_key`, `pending_key`);

alter table `friendly_invitations` add index `friendly_invitations_source_club_id_status_index`(`source_club_id`, `status`);

alter table `friendly_invitations` add index `friendly_invitations_target_club_id_status_index`(`target_club_id`, `status`);

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000015_club_discovery_friendlies', 1);

-- 2026_11_18_000016_create_club_matches
create table `club_matches` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `team_id` bigint unsigned null,
  `friendly_invitation_id` bigint unsigned null,
  `opponent` varchar(120) not null,
  `match_date` date not null,
  `start_time` varchar(5) not null,
  `end_time` varchar(5) null,
  `location` varchar(160) null,
  `match_type` varchar(16) not null,
  `status` varchar(16) not null,
  `our_score` smallint unsigned null,
  `opponent_score` smallint unsigned null,
  `notes` text null,
  `created_by` bigint unsigned not null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `club_matches` add constraint `club_matches_club_id_foreign` foreign key (`club_id`) references `clubs` (`id`) on delete restrict;

alter table `club_matches` add constraint `club_matches_club_id_team_id_foreign` foreign key (`club_id`, `team_id`) references `teams` (`club_id`, `id`) on delete restrict;

alter table `club_matches` add constraint `club_matches_friendly_invitation_id_foreign` foreign key (`friendly_invitation_id`) references `friendly_invitations` (`id`) on delete restrict;

alter table `club_matches` add constraint `club_matches_created_by_foreign` foreign key (`created_by`) references `users` (`id`) on delete restrict;

alter table `club_matches` add unique `club_matches_club_id_friendly_invitation_id_unique`(`club_id`, `friendly_invitation_id`);

alter table `club_matches` add index `club_matches_club_id_match_date_index`(`club_id`, `match_date`);

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000016_create_club_matches', 1);

-- 2026_11_18_000017_create_inventory
create table `inventory_items` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `name` varchar(255) not null,
  `category` varchar(100) null,
  `unit` varchar(30) not null default 'cái',
  `kind` varchar(20) not null default 'consumable',
  `quantity` int unsigned not null default '0',
  `low_stock_threshold` int unsigned not null default '0',
  `notes` text null,
  `archived` tinyint(1) not null default '0',
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `inventory_items` add constraint `inventory_items_club_id_foreign` foreign key (`club_id`) references `clubs` (`id`) on delete restrict;

alter table `inventory_items` add unique `inventory_items_club_id_id_unique`(`club_id`, `id`);

alter table `inventory_items` add index `inventory_items_club_id_archived_name_index`(`club_id`, `archived`, `name`);

create table `inventory_movements` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `inventory_item_id` bigint unsigned not null,
  `actor_id` bigint unsigned not null,
  `type` varchar(20) not null,
  `change` int not null,
  `balance_after` int unsigned not null,
  `reason` varchar(500) not null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `inventory_movements` add constraint `inventory_movements_club_id_foreign` foreign key (`club_id`) references `clubs` (`id`) on delete restrict;

alter table `inventory_movements` add constraint `inventory_movements_inventory_item_id_foreign` foreign key (`inventory_item_id`) references `inventory_items` (`id`) on delete restrict;

alter table `inventory_movements` add constraint `inventory_movements_actor_id_foreign` foreign key (`actor_id`) references `users` (`id`) on delete restrict;

alter table `inventory_movements` add constraint `inventory_movements_club_id_inventory_item_id_foreign` foreign key (`club_id`, `inventory_item_id`) references `inventory_items` (`club_id`, `id`) on delete restrict;

alter table `inventory_movements` add index `inventory_movements_club_id_inventory_item_id_id_index`(`club_id`, `inventory_item_id`, `id`);

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000017_create_inventory', 1);

-- 2026_11_18_000018_inventory_assignments
alter table `player_profiles` add unique `player_profiles_club_id_id_unique`(`club_id`, `id`);

create table `inventory_assignments` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `inventory_item_id` bigint unsigned not null,
  `player_id` bigint unsigned not null,
  `player_name` varchar(120) not null,
  `quantity` int unsigned not null,
  `remaining` int unsigned not null,
  `actor_id` bigint unsigned not null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `inventory_assignments` add constraint `inventory_assignments_club_id_foreign` foreign key (`club_id`) references `clubs` (`id`) on delete restrict;

alter table `inventory_assignments` add constraint `inventory_assignments_actor_id_foreign` foreign key (`actor_id`) references `users` (`id`) on delete restrict;

alter table `inventory_assignments` add unique `inventory_assignments_club_id_inventory_item_id_id_unique`(`club_id`, `inventory_item_id`, `id`);

alter table `inventory_assignments` add constraint `inventory_assignments_club_id_inventory_item_id_foreign` foreign key (`club_id`, `inventory_item_id`) references `inventory_items` (`club_id`, `id`) on delete restrict;

alter table `inventory_assignments` add constraint `inventory_assignments_club_id_player_id_foreign` foreign key (`club_id`, `player_id`) references `player_profiles` (`club_id`, `id`) on delete restrict;

alter table `inventory_assignments` add index `inventory_assignments_club_id_player_id_index`(`club_id`, `player_id`);

alter table `inventory_movements` add `inventory_assignment_id` bigint unsigned null;

alter table `inventory_movements` add constraint `inventory_movement_assignment_fk` foreign key (`club_id`, `inventory_item_id`, `inventory_assignment_id`) references `inventory_assignments` (`club_id`, `inventory_item_id`, `id`) on delete restrict;

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000018_inventory_assignments', 1);

-- 2026_11_18_000019_match_attendance
alter table `club_matches` add unique `club_matches_club_id_id_unique`(`club_id`, `id`);

create table `match_attendances` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `match_id` bigint unsigned not null,
  `player_id` bigint unsigned not null,
  `rsvp` varchar(12) not null default 'pending',
  `attendance` varchar(12) not null default 'unmarked',
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `match_attendances` add constraint `match_attendances_club_id_foreign` foreign key (`club_id`) references `clubs` (`id`) on delete restrict;

alter table `match_attendances` add constraint `match_attendances_club_id_match_id_foreign` foreign key (`club_id`, `match_id`) references `club_matches` (`club_id`, `id`) on delete restrict;

alter table `match_attendances` add constraint `match_attendances_club_id_player_id_foreign` foreign key (`club_id`, `player_id`) references `player_profiles` (`club_id`, `id`) on delete restrict;

alter table `match_attendances` add unique `match_attendances_match_id_player_id_unique`(`match_id`, `player_id`);

alter table `match_attendances` add index `match_attendances_club_id_match_id_index`(`club_id`, `match_id`);

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000019_match_attendance', 1);

-- 2026_11_18_000020_create_club_polls
create table `club_polls` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `created_by` bigint unsigned not null,
  `title` varchar(200) not null,
  `description` text null,
  `deadline` timestamp not null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `club_polls` add constraint `club_polls_club_id_foreign` foreign key (`club_id`) references `clubs` (`id`) on delete restrict;

alter table `club_polls` add constraint `club_polls_created_by_foreign` foreign key (`created_by`) references `users` (`id`) on delete restrict;

alter table `club_polls` add unique `club_polls_club_id_id_unique`(`club_id`, `id`);

create table `club_poll_options` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `poll_id` bigint unsigned not null,
  `label` varchar(200) not null,
  `sort_order` int unsigned not null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `club_poll_options` add constraint `club_poll_options_club_id_poll_id_foreign` foreign key (`club_id`, `poll_id`) references `club_polls` (`club_id`, `id`) on delete restrict;

alter table `club_poll_options` add unique `club_poll_options_club_id_poll_id_id_unique`(`club_id`, `poll_id`, `id`);

create table `club_poll_electors` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `poll_id` bigint unsigned not null,
  `user_id` bigint unsigned not null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `club_poll_electors` add constraint `club_poll_electors_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete restrict;

alter table `club_poll_electors` add constraint `club_poll_electors_club_id_poll_id_foreign` foreign key (`club_id`, `poll_id`) references `club_polls` (`club_id`, `id`) on delete restrict;

alter table `club_poll_electors` add unique `club_poll_electors_club_id_poll_id_user_id_unique`(`club_id`, `poll_id`, `user_id`);

create table `club_poll_votes` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `poll_id` bigint unsigned not null,
  `user_id` bigint unsigned not null,
  `option_id` bigint unsigned not null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `club_poll_votes` add constraint `club_poll_votes_club_id_poll_id_user_id_foreign` foreign key (`club_id`, `poll_id`, `user_id`) references `club_poll_electors` (`club_id`, `poll_id`, `user_id`) on delete restrict;

alter table `club_poll_votes` add constraint `club_poll_votes_club_id_poll_id_option_id_foreign` foreign key (`club_id`, `poll_id`, `option_id`) references `club_poll_options` (`club_id`, `poll_id`, `id`) on delete restrict;

alter table `club_poll_votes` add unique `club_poll_votes_poll_id_user_id_unique`(`poll_id`, `user_id`);

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000020_create_club_polls', 1);

-- 2026_11_18_000021_player_match_stats
create table `player_match_stats` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `match_id` bigint unsigned not null,
  `player_id` bigint unsigned not null,
  `goals` smallint unsigned not null default '0',
  `assists` smallint unsigned not null default '0',
  `yellow_cards` tinyint unsigned not null default '0',
  `red_cards` tinyint unsigned not null default '0',
  `goals_conceded` smallint unsigned null,
  `rating` decimal(3, 1) null,
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `player_match_stats` add constraint `player_match_stats_club_id_foreign` foreign key (`club_id`) references `clubs` (`id`) on delete restrict;

alter table `player_match_stats` add constraint `player_match_stats_club_id_match_id_foreign` foreign key (`club_id`, `match_id`) references `club_matches` (`club_id`, `id`) on delete restrict;

alter table `player_match_stats` add constraint `player_match_stats_club_id_player_id_foreign` foreign key (`club_id`, `player_id`) references `player_profiles` (`club_id`, `id`) on delete restrict;

alter table `player_match_stats` add unique `player_match_stats_match_id_player_id_unique`(`match_id`, `player_id`);

alter table `player_match_stats` add index `player_match_stats_club_id_player_id_index`(`club_id`, `player_id`);

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000021_player_match_stats', 1);

-- 2026_11_18_000022_recruitment
create table `recruitment_posts` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `title` varchar(255) not null,
  `position` varchar(100) null,
  `requirements` text null,
  `deadline` date null,
  `status` varchar(20) not null default 'draft',
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `recruitment_posts` add constraint `recruitment_posts_club_id_foreign` foreign key (`club_id`) references `clubs` (`id`) on delete restrict;

alter table `recruitment_posts` add unique `recruitment_posts_club_id_id_unique`(`club_id`, `id`);

alter table `recruitment_posts` add index `recruitment_posts_club_id_status_index`(`club_id`, `status`);

create table `recruitment_applications` (
  `id` bigint unsigned not null auto_increment primary key,
  `club_id` bigint unsigned not null,
  `post_id` bigint unsigned not null,
  `full_name` varchar(255) not null,
  `date_of_birth` date null,
  `email` varchar(255) null,
  `phone` varchar(30) null,
  `notes` text null,
  `status` varchar(20) not null default 'new',
  `created_at` timestamp null,
  `updated_at` timestamp null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `recruitment_applications` add constraint `recruitment_applications_club_id_foreign` foreign key (`club_id`) references `clubs` (`id`) on delete restrict;

alter table `recruitment_applications` add constraint `recruitment_applications_club_id_post_id_foreign` foreign key (`club_id`, `post_id`) references `recruitment_posts` (`club_id`, `id`) on delete restrict;

alter table `recruitment_applications` add index `recruitment_applications_club_id_post_id_status_index`(`club_id`, `post_id`, `status`);

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000022_recruitment', 1);

-- 2026_11_18_000023_link_lineups_to_matches
alter table `match_lineups` add `club_match_id` bigint unsigned null;

alter table `match_lineups` add constraint `match_lineups_club_id_club_match_id_foreign` foreign key (`club_id`, `club_match_id`) references `club_matches` (`club_id`, `id`) on delete restrict;

alter table `match_lineups` add unique `match_lineups_club_id_club_match_id_unique`(`club_id`, `club_match_id`);

insert into `migrations` (`migration`, `batch`) values ('2026_11_18_000023_link_lineups_to_matches', 1);

