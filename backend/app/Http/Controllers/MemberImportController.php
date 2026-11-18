<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlayerRequest;
use App\Services\ClubService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MemberImportController extends Controller
{
    private const FIELDS = ['full_name', 'email', 'phone', 'date_of_birth', 'position', 'preferred_foot', 'status', 'notes'];

    private function prepareRows(Request $request): array
    {
        $club = $request->attributes->get('club');
        $data = $request->validate(['players' => ['required', 'array', 'min:1', 'max:500'], 'players.*' => ['required', 'array:full_name,email,phone,date_of_birth,position,preferred_foot,status,notes']]);
        $rulesRequest = PlayerRequest::create('/', 'POST');
        $rulesRequest->attributes->set('club', $club);
        $rules = $rulesRequest->rules();
        // Conflicts are handled explicitly after preview rather than by validation.
        $rules['email'] = ['nullable', 'email', 'max:255'];
        $rows = []; $errors = [];
        foreach ($data['players'] as $index => $row) {
            $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row);
            if (! empty($row['email'])) $row['email'] = mb_strtolower($row['email']);
            $row['status'] = $row['status'] ?? 'active';
            $validator = Validator::make($row, $rules);
            if ($validator->fails()) foreach ($validator->errors()->messages() as $field => $messages) $errors["players.$index.$field"] = $messages;
            else $rows[] = $validator->validated();
        }
        if ($errors) throw ValidationException::withMessages($errors);
        return $rows;
    }

    private function matching($club, array $row): array
    {
        $query = $club->players()->with(['teams' => fn ($q) => $q->orderBy('teams.id')]);
        if (! empty($row['email']) && $club->players()->where('email', $row['email'])->exists()) {
            $query->where('email', $row['email']);
        } elseif (! empty($row['date_of_birth'])) {
            $query->where('full_name', $row['full_name'])->whereDate('date_of_birth', $row['date_of_birth']);
        } else return [];
        return $query->get()->map(function ($player) {
            $details = ['id' => $player->id, 'full_name' => $player->full_name, 'email' => $player->email, 'phone' => $player->phone, 'date_of_birth' => $player->date_of_birth?->format('Y-m-d'), 'position' => $player->position, 'preferred_foot' => $player->preferred_foot, 'status' => $player->status, 'notes' => $player->notes, 'teams' => $player->teams->map(fn ($team) => ['id' => $team->id, 'name' => $team->name])->values()->all()];
            $details['version'] = hash('sha256', json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return $details;
        })->all();
    }

    public function preview(Request $request): mixed
    {
        $club = $request->attributes->get('club');
        Gate::authorize('manageRoster', $club);
        $rows = $this->prepareRows($request);
        return response()->json(['data' => array_map(fn ($row) => ['matches' => $this->matching($club, $row)], $rows)]);
    }

    public function commit(Request $request, ClubService $service): mixed
    {
        $club = $request->attributes->get('club');
        Gate::authorize('manageRoster', $club);
        $rows = $this->prepareRows($request);
        $data = $request->validate(['decisions' => ['required', 'array', 'size:'.count($rows)], 'decisions.*.action' => ['required', Rule::in(['create', 'overwrite', 'skip'])], 'decisions.*.target_id' => ['nullable', 'integer'], 'decisions.*.match_versions' => ['present', 'array'], 'decisions.*.match_versions.*' => ['string', 'size:64']]);
        $result = DB::transaction(function () use ($club, $request, $rows, $data, $service) {
            DB::table('clubs')->where('id', $club->id)->lockForUpdate()->first();
            $count = ['created' => 0, 'overwritten' => 0, 'skipped' => 0];
            $targets = [];
            // Check every decision against the same snapshot before changing any rows.
            foreach ($rows as $index => $row) {
                $matches = $this->matching($club, $row);
                $actual = array_column($matches, 'id');
                $versions = array_column($matches, 'version'); sort($versions);
                $expected = $data['decisions'][$index]['match_versions']; sort($expected);
                if ($versions !== $expected) throw ValidationException::withMessages(["players.$index" => ['Dữ liệu thành viên đã thay đổi. Hãy kiểm tra lại file.']]);
                $decision = $data['decisions'][$index];
                if ($actual && ! in_array($decision['action'], ['create', 'overwrite', 'skip'], true)) throw ValidationException::withMessages(["players.$index" => ['Hãy chọn cách xử lý trùng.']]);
                if ($decision['action'] === 'overwrite' && ! in_array($decision['target_id'] ?? null, $actual)) throw ValidationException::withMessages(["players.$index" => ['Thành viên ghi đè không còn khớp.']]);
                if ($decision['action'] === 'overwrite') {
                    if (isset($targets[$decision['target_id']])) throw ValidationException::withMessages(["players.$index" => ['Hai dòng đang ghi đè cùng một hồ sơ; hãy bỏ qua hoặc tạo mới một dòng.']]);
                    $targets[$decision['target_id']] = true;
                }
            }
            $newEmails = [];
            foreach ($rows as $index => $row) {
                $decision = $data['decisions'][$index];
                if ($decision['action'] === 'skip' || empty($row['email'])) continue;
                $email = $row['email'];
                if (isset($newEmails[$email]) || $club->players()->where('email', $email)->when($decision['action'] === 'overwrite', fn ($q) => $q->where('id', '!=', $decision['target_id']))->exists()) {
                    throw ValidationException::withMessages(["players.$index.email" => ['Email đã tồn tại trong CLB hoặc trong các dòng được chọn.']]);
                }
                $newEmails[$email] = true;
            }
            foreach ($rows as $index => $row) {
                $decision = $data['decisions'][$index];
                if ($decision['action'] === 'skip') { $count['skipped']++; continue; }
                if ($decision['action'] === 'overwrite') { $service->savePlayer($club, $request->user(), $row, $decision['target_id']); $count['overwritten']++; }
                else { $service->savePlayer($club, $request->user(), $row); $count['created']++; }
            }
            return $count;
        });
        return response()->json(['data' => $result], 201);
    }
}
