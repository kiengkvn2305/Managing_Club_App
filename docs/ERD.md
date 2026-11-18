# ERD toàn hệ thống

Thiết kế trước implementation. Nhóm core được triển khai trước; các nhóm PLANNED chưa có migrations. Migration là nguồn chuẩn cho chi tiết field/index.

```mermaid
erDiagram
  users ||--o{ club_members : access
  clubs ||--|{ club_members : membership
  clubs ||--o{ teams : organizes
  clubs ||--o{ player_profiles : roster
  teams o|--o{ player_profiles : assigned
  users o|--o{ player_profiles : optionally_linked
  clubs ||--o{ club_invitations : invites
  users ||--o{ club_invitations : sends
  clubs ||--o{ audit_logs : changes
  users ||--o{ sessions : authenticates
```

Core constraints: unique users.email, clubs.slug, club_members(club_id,user_id), teams(club_id,name), teams(club_id,id); profile composite FK `(club_id,team_id)`; invitation token SHA-256 unique, expiry and accepted_at; roles belong to membership. Roster profiles and access memberships are separate entities. Club creation + owner membership + audit share a transaction.

## Football / people — PLANNED

```mermaid
erDiagram
  clubs ||--o{ seasons : owns
  player_profiles ||--o{ player_team_assignments : history
  teams ||--o{ player_team_assignments : assigns
  clubs ||--o{ events : schedules
  events ||--o| matches : details
  matches ||--o{ match_participants : selects
  player_profiles ||--o{ match_participants : plays
  matches ||--o| match_results : records
  match_participants ||--o{ player_statistics : aggregates
  events ||--o{ attendance : records
  player_profiles ||--o{ attendance : attends
  player_profiles ||--o{ disciplinary_cases : receives
  clubs ||--o{ recruitment_posts : opens
  recruitment_posts ||--o{ recruitment_applications : receives
```

RSVP separate from actual attendance; unique attendance(club_id,event_id,player_id). Statistics derive from recorded results/events. Personal birthdays require privacy controls.

## Finance / inventory — PLANNED

```mermaid
erDiagram
  clubs ||--o{ funds : owns
  clubs ||--o{ financial_periods : locks
  financial_periods ||--o{ financial_transactions : dates
  transaction_categories ||--o{ financial_transactions : categorizes
  financial_transactions ||--o{ transaction_approvals : approves
  financial_transactions ||--o{ ledger_entries : posts
  funds ||--o{ ledger_entries : balance
  financial_transactions o|--o| financial_transactions : reverses
  player_profiles ||--o{ receivables : owes
  receivables ||--o{ receivable_adjustments : adjusts
  receivables ||--o{ payment_allocations : settles
  payments ||--o{ payment_allocations : allocates
  matches ||--o{ cost_splits : costs
  cost_splits ||--o{ cost_split_lines : distributes
  cost_split_lines o|--o| receivables : creates
  clubs ||--o{ inventory_items : stocks
  inventory_items ||--o{ stock_movements : changes
  inventory_items ||--o{ equipment_assignments : lends
  player_profiles ||--o{ equipment_assignments : receives
  stocktakes ||--o{ stocktake_lines : counts
  inventory_items ||--o{ stocktake_lines : verifies
  sponsors ||--o{ sponsorships : contracts
  sponsorships o|--o{ financial_transactions : cash
  sponsorships o|--o{ stock_movements : in_kind
```

Amounts DECIMAL, exact arithmetic, no float. Ledger is append-only after posting. Internal transfers excluded from external income/expense totals. Payment confirmation is manual until bank verification exists. Period locks, row locks, idempotency and reversal are required and must be tested before finance acceptance.

## Other modules — PLANNED

```mermaid
erDiagram
  clubs ||--o{ polls : asks
  polls ||--o{ poll_options : offers
  polls ||--o{ poll_votes : records
  users ||--o{ poll_votes : votes
  clubs ||--o{ import_jobs : stages
  import_jobs ||--o{ import_rows : previews
  clubs ||--o{ notifications : notifies
  clubs ||--o| public_club_profiles : publishes
  clubs ||--o{ automation_rules : automates
  automation_rules ||--o{ automation_runs : executes
  clubs ||--o{ ai_requests : authorizes
  users ||--o| user_profiles : extends
  roles ||--o{ role_permissions : grants
  permissions ||--o{ role_permissions : defines
```

Roles initially fixed enums; configurable permission tables are a future evolution. Poll unique(club_id,poll_id,user_id). Import stages mapping/preview/errors and writes only after explicit confirm. AI requires explicit data-processing configuration; never changes balances or approves payments. Every planned tenant table must include club_id, scoped indexes and composite FK on cross-resource relations.
