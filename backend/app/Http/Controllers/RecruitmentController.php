<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RecruitmentController extends Controller
{
    public function index(Request $request): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('viewRoster', $club);
        $data = $request->validate(['status' => ['nullable', 'in:draft,open,closed']]);
        return response()->json(DB::table('recruitment_posts')->where('club_id', $club->id)
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('id')->paginate(30));
    }

    public function storePost(Request $request, AuditService $audit): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('manageRoster', $club);
        $data = $this->postData($request);
        $id = DB::transaction(function () use ($club, $request, $audit, $data) {
            $locked = Club::whereKey($club->id)->lockForUpdate()->firstOrFail(); Gate::authorize('manageRoster', $locked);
            $id = DB::table('recruitment_posts')->insertGetId($data + ['club_id' => $club->id, 'created_at' => now(), 'updated_at' => now()]);
            $audit->record($club, $request->user(), 'recruitment.post_created', $club);
            return $id;
        });
        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function updatePost(Request $request, string $club, int $post, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('manageRoster', $tenant);
        $data = $this->postData($request);
        DB::transaction(function () use ($tenant, $post, $request, $audit, $data) {
            $locked = Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail(); Gate::authorize('manageRoster', $locked);
            $record = DB::table('recruitment_posts')->where('club_id', $tenant->id)->where('id', $post)->lockForUpdate()->first();
            if (! $record) abort(404);
            DB::table('recruitment_posts')->where('id', $post)->update($data + ['updated_at' => now()]);
            $audit->record($tenant, $request->user(), 'recruitment.post_updated', $tenant);
        });
        return response()->noContent();
    }

    public function applications(Request $request, string $club, int $post): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('viewRoster', $tenant);
        $this->findPost($tenant, $post);
        $data = $request->validate(['status' => ['nullable', 'in:new,reviewing,trial,accepted,rejected']]);
        return response()->json(DB::table('recruitment_applications')->where('club_id', $tenant->id)->where('post_id', $post)
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('id')->paginate(30));
    }

    public function storeApplication(Request $request, string $club, int $post, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('manageRoster', $tenant);
        $data = $this->applicationData($request);
        $id = DB::transaction(function () use ($tenant, $post, $request, $audit, $data) {
            $locked = Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail(); Gate::authorize('manageRoster', $locked);
            $this->findPost($tenant, $post);
            $id = DB::table('recruitment_applications')->insertGetId($data + ['club_id' => $tenant->id, 'post_id' => $post, 'created_at' => now(), 'updated_at' => now()]);
            $audit->record($tenant, $request->user(), 'recruitment.application_created', $tenant);
            return $id;
        });
        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function updateApplication(Request $request, string $club, int $post, int $application, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('manageRoster', $tenant);
        $data = $this->applicationData($request);
        DB::transaction(function () use ($tenant, $post, $application, $request, $audit, $data) {
            $locked = Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail(); Gate::authorize('manageRoster', $locked);
            $this->findPost($tenant, $post);
            $record = DB::table('recruitment_applications')->where('club_id', $tenant->id)->where('post_id', $post)->where('id', $application)->lockForUpdate()->first();
            if (! $record) abort(404);
            DB::table('recruitment_applications')->where('id', $application)->update($data + ['updated_at' => now()]);
            $audit->record($tenant, $request->user(), 'recruitment.application_updated', $tenant);
        });
        return response()->noContent();
    }

    private function findPost(Club $club, int $post): object
    {
        return DB::table('recruitment_posts')->where('club_id', $club->id)->where('id', $post)->first() ?? abort(404);
    }

    private function postData(Request $request): array
    {
        return $request->validate(['title' => ['required', 'string', 'max:255'], 'position' => ['nullable', 'string', 'max:100'], 'requirements' => ['nullable', 'string', 'max:5000'], 'deadline' => ['nullable', 'date_format:Y-m-d'], 'status' => ['required', 'in:draft,open,closed']]);
    }

    private function applicationData(Request $request): array
    {
        return $request->validate(['full_name' => ['required', 'string', 'max:255'], 'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'], 'notes' => ['nullable', 'string', 'max:5000'], 'status' => ['required', 'in:new,reviewing,trial,accepted,rejected']]);
    }
}
