<?php
namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubPoll;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PollController extends Controller
{
    private function requireMember(Request $request, Club $club): void
    {
        abort_unless(DB::table('club_members')->where('club_id', $club->id)->where('user_id', $request->user()->id)->where('status', 'active')->exists(), 403);
    }

    public function index(Request $request): mixed
    {
        $club = $request->attributes->get('club'); $this->requireMember($request, $club);
        $polls = ClubPoll::where('club_id', $club->id)->orderByDesc('id')->paginate(20);
        $polls->getCollection()->transform(fn ($poll) => $this->present($poll, $request->user()->id));
        return response()->json($polls);
    }

    public function store(Request $request, AuditService $audit): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('update', $club);
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:2000'], 'deadline' => ['required', 'date', 'after:now'], 'options' => ['required', 'array', 'min:2', 'max:20'], 'options.*' => ['required', 'string', 'max:200', 'distinct:ignore_case']]);
        $poll = DB::transaction(function () use ($club, $request, $data, $audit) {
            $locked = Club::whereKey($club->id)->lockForUpdate()->firstOrFail(); Gate::authorize('update', $locked);
            abort_unless($locked->status === 'active', 403);
            $poll = ClubPoll::create(['club_id' => $club->id, 'created_by' => $request->user()->id, 'title' => $data['title'], 'description' => $data['description'] ?? null, 'deadline' => $data['deadline']]);
            foreach ($data['options'] as $i => $label) DB::table('club_poll_options')->insert(['club_id' => $club->id, 'poll_id' => $poll->id, 'label' => $label, 'sort_order' => $i, 'created_at' => now(), 'updated_at' => now()]);
            $members = DB::table('club_members')->where('club_id', $club->id)->where('status', 'active')->select('user_id')->get();
            foreach ($members as $member) DB::table('club_poll_electors')->insert(['club_id' => $club->id, 'poll_id' => $poll->id, 'user_id' => $member->user_id, 'created_at' => now(), 'updated_at' => now()]);
            $audit->record($club, $request->user(), 'poll.created', $poll);
            return $poll;
        });
        return response()->json(['data' => $this->present($poll, $request->user()->id)], 201);
    }

    public function vote(Request $request, string $club, int $poll, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club'); $this->requireMember($request, $tenant);
        $data = $request->validate(['option_id' => ['required', 'integer']]);
        $result = DB::transaction(function () use ($tenant, $request, $poll, $data, $audit) {
            $lockedClub = Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $this->requireMember($request, $lockedClub); abort_unless($lockedClub->status === 'active', 403);
            $record = ClubPoll::where('club_id', $tenant->id)->whereKey($poll)->lockForUpdate()->firstOrFail();
            if ($record->deadline->isPast() || $record->deadline->equalTo(now())) throw ValidationException::withMessages(['option_id' => ['Cuộc biểu quyết đã hết hạn.']]);
            if (! DB::table('club_poll_electors')->where('club_id', $tenant->id)->where('poll_id', $poll)->where('user_id', $request->user()->id)->exists()) abort(403, 'Bạn không thuộc danh sách cử tri khi tạo biểu quyết.');
            if (DB::table('club_poll_votes')->where('poll_id', $poll)->where('user_id', $request->user()->id)->exists()) throw ValidationException::withMessages(['option_id' => ['Bạn đã bỏ phiếu.']]);
            if (! DB::table('club_poll_options')->where('club_id', $tenant->id)->where('poll_id', $poll)->where('id', $data['option_id'])->exists()) throw ValidationException::withMessages(['option_id' => ['Lựa chọn không hợp lệ.']]);
            DB::table('club_poll_votes')->insert(['club_id' => $tenant->id, 'poll_id' => $poll, 'user_id' => $request->user()->id, 'option_id' => $data['option_id'], 'created_at' => now(), 'updated_at' => now()]);
            $audit->record($tenant, $request->user(), 'poll.voted', $record);
            return $this->present($record, $request->user()->id);
        });
        return response()->json(['data' => $result], 201);
    }

    private function present(ClubPoll $poll, int $userId): array
    {
        $counts = DB::table('club_poll_votes')->where('club_id', $poll->club_id)->where('poll_id', $poll->id)->selectRaw('option_id, count(*) as total')->groupBy('option_id')->pluck('total', 'option_id');
        $options = DB::table('club_poll_options')->where('club_id', $poll->club_id)->where('poll_id', $poll->id)->orderBy('sort_order')->get(['id', 'label'])->map(function ($option) use ($counts) {
            return ['id' => $option->id, 'label' => $option->label, 'votes' => (int) ($counts[$option->id] ?? 0)];
        });
        return ['id' => $poll->id, 'title' => $poll->title, 'description' => $poll->description, 'deadline' => $poll->deadline->toISOString(), 'closed' => $poll->deadline->lessThanOrEqualTo(now()), 'eligible' => DB::table('club_poll_electors')->where('poll_id', $poll->id)->where('user_id', $userId)->exists(), 'voted' => DB::table('club_poll_votes')->where('poll_id', $poll->id)->where('user_id', $userId)->exists(), 'eligible_count' => DB::table('club_poll_electors')->where('poll_id', $poll->id)->count(), 'options' => $options];
    }
}
