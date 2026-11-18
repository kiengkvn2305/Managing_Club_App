<?php
namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubMatch;
use App\Models\FriendlyInvitation;
use App\Services\AuditService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubMatchController extends Controller
{
    public function calendar(Request $request): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('viewRoster', $club);
        $data = $request->validate(['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $from = CarbonImmutable::parse($data['from']); $to = CarbonImmutable::parse($data['to']);
        if ($from->diffInDays($to) > 41) throw ValidationException::withMessages(['to' => ['Chỉ xem tối đa 42 ngày mỗi lần.']]);
        $items = DB::table('club_matches')->leftJoin('teams', function ($join) { $join->on('teams.id', '=', 'club_matches.team_id')->on('teams.club_id', '=', 'club_matches.club_id'); })
            ->where('club_matches.club_id', $club->id)->whereBetween('club_matches.match_date', [$data['from'], $data['to']])->select('club_matches.*', 'teams.name as team_name')
            ->orderBy('club_matches.match_date')->orderBy('club_matches.start_time')->orderBy('club_matches.id')->limit(501)->get();
        if ($items->count() > 500) throw ValidationException::withMessages(['from' => ['Có quá nhiều trận trong khoảng đã chọn; hãy xem theo tuần.']]);
        return response()->json(['data' => $items]);
    }
    public function index(Request $request): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('viewRoster', $club);
        $data = $request->validate(['month' => ['required', 'date_format:Y-m'], 'page' => ['nullable', 'integer', 'min:1']]);
        $month = CarbonImmutable::createFromFormat('!Y-m', $data['month']);
        $matches = DB::table('club_matches')->leftJoin('teams', function ($join) { $join->on('teams.id', '=', 'club_matches.team_id')->on('teams.club_id', '=', 'club_matches.club_id'); })
            ->where('club_matches.club_id', $club->id)->whereBetween('club_matches.match_date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
            ->select('club_matches.*', 'teams.name as team_name')->orderBy('club_matches.match_date')->orderBy('club_matches.start_time')->orderBy('club_matches.id')->paginate(20);
        return response()->json(['data' => $matches->items(), 'meta' => ['current_page' => $matches->currentPage(), 'last_page' => $matches->lastPage(), 'total' => $matches->total()]]);
    }

    public function save(Request $request, AuditService $audit, string $club, ?int $match = null): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('manageRoster', $tenant);
        $data = $request->validate([
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->where('club_id', $tenant->id)],
            'friendly_invitation_id' => ['nullable', 'integer', 'min:1'], 'opponent' => ['required', 'string', 'max:120'],
            'match_date' => ['required', 'date_format:Y-m-d'], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location' => ['nullable', 'string', 'max:160'], 'match_type' => ['required', Rule::in(['friendly', 'tournament', 'practice'])],
            'status' => ['required', Rule::in(['scheduled', 'completed', 'cancelled'])], 'our_score' => ['nullable', 'integer', 'between:0,999'], 'opponent_score' => ['nullable', 'integer', 'between:0,999'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $ours = $data['our_score'] ?? null; $theirs = $data['opponent_score'] ?? null;
        if ($data['status'] === 'completed' && ($ours === null || $theirs === null)) throw ValidationException::withMessages(['our_score' => ['Cần nhập tỷ số cho trận đã kết thúc.']]);
        if ($data['status'] !== 'completed' && ($ours !== null || $theirs !== null)) throw ValidationException::withMessages(['status' => ['Chỉ nhập tỷ số khi trận đã kết thúc.']]);
        $saved = DB::transaction(function () use ($tenant, $request, $data, $match, $audit) {
            $locked = Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail(); Gate::authorize('manageRoster', $locked);
            $locked->teams()->findOrFail($data['team_id']);
            $existing = $match === null ? null : ClubMatch::where('club_id', $tenant->id)->whereKey($match)->lockForUpdate()->firstOrFail();
            if ($existing) {
                $stats = DB::table('player_match_stats')->where('club_id', $tenant->id)->where('match_id', $existing->id);
                if ((clone $stats)->exists()) {
                    if ($data['status'] !== 'completed' || (int) $existing->team_id !== (int) $data['team_id']) throw ValidationException::withMessages(['status' => ['Không thể đổi đội hoặc trạng thái khi trận đã có thống kê cầu thủ.']]);
                    if ((int) (clone $stats)->sum('goals') > (int) $data['our_score'] || (int) (clone $stats)->sum('assists') > (int) $data['our_score'] || (int) (clone $stats)->max('goals_conceded') > (int) $data['opponent_score']) throw ValidationException::withMessages(['our_score' => ['Tỷ số không thể thấp hơn thống kê đã ghi.']]);
                }
            }
            if ($existing && (int) $existing->team_id !== (int) $data['team_id'] && DB::table('match_attendances')->where('club_id', $tenant->id)->where('match_id', $existing->id)->exists()) throw ValidationException::withMessages(['team_id' => ['Không thể đổi đội khi trận đã có đăng ký hoặc điểm danh.']]);
            if ($existing && (int) $existing->team_id !== (int) $data['team_id'] && DB::table('match_lineups')->where('club_id', $tenant->id)->where('club_match_id', $existing->id)->exists()) throw ValidationException::withMessages(['team_id' => ['Không thể đổi đội khi trận đã có đội hình.']]);
            $friendly = $data['friendly_invitation_id'] ?? null;
            if ($friendly) {
                $invite = FriendlyInvitation::whereKey($friendly)->where('status', 'accepted')->where(fn ($q) => $q->where('source_club_id', $tenant->id)->orWhere('target_club_id', $tenant->id))->first();
                if (! $invite || $data['match_type'] !== 'friendly') throw ValidationException::withMessages(['friendly_invitation_id' => ['Chỉ liên kết lời mời giao hữu đã được chấp nhận của CLB.']]);
                if (ClubMatch::where('club_id', $tenant->id)->where('friendly_invitation_id', $friendly)->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))->exists()) throw ValidationException::withMessages(['friendly_invitation_id' => ['Lời mời này đã có lịch trận của CLB.']]);
            }
            $values = $data + ['friendly_invitation_id' => null, 'end_time' => null, 'location' => null, 'our_score' => null, 'opponent_score' => null, 'notes' => null];
            if ($existing) { $existing->update($values); $result = $existing; }
            else $result = ClubMatch::create($values + ['club_id' => $tenant->id, 'created_by' => $request->user()->id]);
            if ($existing) DB::table('match_lineups')->where('club_id', $tenant->id)->where('club_match_id', $existing->id)->update(['opponent' => $result->opponent, 'match_date' => $result->match_date, 'updated_at' => now()]);
            $audit->record($tenant, $request->user(), $existing ? 'match.updated' : 'match.created', $result); return $result;
        });
        return response()->json(['data' => $saved], $match === null ? 201 : 200);
    }
}
