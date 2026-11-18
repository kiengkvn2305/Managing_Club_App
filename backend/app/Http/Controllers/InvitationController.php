<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvitationRequest;
use App\Http\Resources\ClubResource;
use App\Services\ClubService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InvitationController extends Controller
{
    public function store(InvitationRequest $request, ClubService $service): mixed
    {
        $club = $request->attributes->get('club');
        Gate::authorize('invite', [$club, $request->validated('role')]);

        return response()->json(['data' => $service->invite($club, $request->user(), $request->validated())], 201);
    }

    public function accept(Request $request, ClubService $service): mixed
    {
        abort_unless($request->user()->hasVerifiedEmail(), 403, 'Vui lòng xác minh email trước khi nhận lời mời.');
        $data = $request->validate(['token' => ['required', 'string', 'size:64']]);

        return new ClubResource($service->accept($request->user(), $data['token']));
    }
}
