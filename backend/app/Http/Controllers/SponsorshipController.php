<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SponsorshipController extends Controller
{
    public function sponsors(Request $request): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('viewFinance', $club);
        return response()->json(DB::table('sponsors')->where('club_id', $club->id)->orderBy('name')->paginate(50));
    }
    public function storeSponsor(Request $request, AuditService $audit): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('manageFinance', $club); $data = $this->sponsorData($request);
        $id = DB::transaction(function () use ($club, $request, $audit, $data) {
            $this->lock($club); $id = DB::table('sponsors')->insertGetId($data + ['club_id' => $club->id, 'created_at' => now(), 'updated_at' => now()]);
            $audit->record($club, $request->user(), 'sponsorship.sponsor_created', $club); return $id;
        });
        return response()->json(['data' => ['id' => $id]], 201);
    }
    public function updateSponsor(Request $request, string $club, int $sponsor, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('manageFinance', $tenant); $data = $this->sponsorData($request);
        DB::transaction(function () use ($tenant, $sponsor, $request, $audit, $data) {
            $this->lock($tenant); $this->find('sponsors', $tenant, $sponsor);
            DB::table('sponsors')->where('club_id', $tenant->id)->where('id', $sponsor)->update($data + ['updated_at' => now()]);
            $audit->record($tenant, $request->user(), 'sponsorship.sponsor_updated', $tenant);
        }); return response()->noContent();
    }
    public function deleteSponsor(Request $request, string $club, int $sponsor, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('manageFinance', $tenant);
        DB::transaction(function () use ($tenant, $sponsor, $request, $audit) {
            $this->lock($tenant); $this->find('sponsors', $tenant, $sponsor);
            if (DB::table('sponsorships')->where('club_id', $tenant->id)->where('sponsor_id', $sponsor)->exists()) throw ValidationException::withMessages(['sponsor' => ['Không thể xóa đơn vị đã có thỏa thuận.']]);
            DB::table('sponsors')->where('club_id', $tenant->id)->where('id', $sponsor)->delete(); $audit->record($tenant, $request->user(), 'sponsorship.sponsor_deleted', $tenant);
        }); return response()->noContent();
    }
    public function agreements(Request $request): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('viewFinance', $club);
        return response()->json(DB::table('sponsorships as s')->join('sponsors as p', function ($join) { $join->on('p.id', '=', 's.sponsor_id')->on('p.club_id', '=', 's.club_id'); })
            ->where('s.club_id', $club->id)->select('s.*', 'p.name as sponsor_name')->orderByDesc('s.id')->paginate(50));
    }
    public function storeAgreement(Request $request, AuditService $audit): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('manageFinance', $club); $data = $this->agreementData($request);
        $id = DB::transaction(function () use ($club, $request, $audit, $data) {
            $this->lock($club); $this->find('sponsors', $club, (int) $data['sponsor_id']);
            $id = DB::table('sponsorships')->insertGetId($data + ['club_id' => $club->id, 'created_at' => now(), 'updated_at' => now()]);
            $audit->record($club, $request->user(), 'sponsorship.agreement_created', $club); return $id;
        }); return response()->json(['data' => ['id' => $id]], 201);
    }
    public function updateAgreement(Request $request, string $club, int $agreement, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('manageFinance', $tenant); $data = $this->agreementData($request);
        DB::transaction(function () use ($tenant, $agreement, $request, $audit, $data) {
            $this->lock($tenant); $existing = $this->find('sponsorships', $tenant, $agreement); $this->find('sponsors', $tenant, (int) $data['sponsor_id']);
            if (($existing->type !== $data['type'] || $existing->sponsor_id != $data['sponsor_id']) && DB::table('sponsorship_receipts')->where('club_id', $tenant->id)->where('sponsorship_id', $agreement)->exists()) throw ValidationException::withMessages(['type' => ['Không thể đổi loại hoặc đơn vị khi đã ghi nhận tài trợ.']]);
            DB::table('sponsorships')->where('club_id', $tenant->id)->where('id', $agreement)->update($data + ['updated_at' => now()]);
            $audit->record($tenant, $request->user(), 'sponsorship.agreement_updated', $tenant);
        }); return response()->noContent();
    }
    public function deleteAgreement(Request $request, string $club, int $agreement, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('manageFinance', $tenant);
        DB::transaction(function () use ($tenant, $agreement, $request, $audit) {
            $this->lock($tenant); $this->find('sponsorships', $tenant, $agreement);
            if (DB::table('sponsorship_receipts')->where('club_id', $tenant->id)->where('sponsorship_id', $agreement)->exists()) throw ValidationException::withMessages(['agreement' => ['Không thể xóa thỏa thuận đã ghi nhận tài trợ.']]);
            DB::table('sponsorships')->where('club_id', $tenant->id)->where('id', $agreement)->delete(); $audit->record($tenant, $request->user(), 'sponsorship.agreement_deleted', $tenant);
        }); return response()->noContent();
    }
    public function receipts(Request $request, string $club, int $agreement): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('viewFinance', $tenant); $this->find('sponsorships', $tenant, $agreement);
        return response()->json(DB::table('sponsorship_receipts')->where('club_id', $tenant->id)->where('sponsorship_id', $agreement)->orderByDesc('id')->paginate(50));
    }
    public function storeReceipt(Request $request, string $club, int $agreement, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('manageFinance', $tenant);
        $data = $request->validate(['value' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'], 'received_on' => ['required', 'date_format:Y-m-d'], 'notes' => ['nullable', 'string', 'max:5000']]);
        $id = DB::transaction(function () use ($tenant, $agreement, $request, $audit, $data) {
            $this->lock($tenant); $this->find('sponsorships', $tenant, $agreement);
            $id = DB::table('sponsorship_receipts')->insertGetId($data + ['club_id' => $tenant->id, 'sponsorship_id' => $agreement, 'actor_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $audit->record($tenant, $request->user(), 'sponsorship.receipt_created', $tenant); return $id;
        }); return response()->json(['data' => ['id' => $id]], 201);
    }
    public function report(Request $request): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('viewFinance', $club);
        $rows = DB::table('sponsorships as s')->join('sponsors as p', function ($join) { $join->on('p.id', '=', 's.sponsor_id')->on('p.club_id', '=', 's.club_id'); })
            ->where('s.club_id', $club->id)->select('s.*', 'p.name as sponsor_name')
            ->selectRaw('(SELECT COALESCE(SUM(r.value), 0) FROM sponsorship_receipts r WHERE r.sponsorship_id = s.id AND r.club_id = s.club_id) as received_value')
            ->orderByDesc('s.id')->paginate(50);
        return response()->json($rows);
    }
    private function lock(Club $club): void { $locked = Club::whereKey($club->id)->lockForUpdate()->firstOrFail(); Gate::authorize('manageFinance', $locked); }
    private function find(string $table, Club $club, int $id): object { return DB::table($table)->where('club_id', $club->id)->where('id', $id)->first() ?? abort(404); }
    private function sponsorData(Request $request): array { return $request->validate(['name' => ['required', 'string', 'max:255'], 'contact_name' => ['nullable', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'], 'notes' => ['nullable', 'string', 'max:5000']]); }
    private function agreementData(Request $request): array { return $request->validate(['sponsor_id' => ['required', 'integer'], 'title' => ['required', 'string', 'max:255'], 'type' => ['required', 'in:cash,equipment'], 'value' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'], 'starts_on' => ['required', 'date_format:Y-m-d'], 'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'], 'benefits' => ['nullable', 'string', 'max:5000'], 'notes' => ['nullable', 'string', 'max:5000']]); }
}
