<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfileImageController extends Controller
{
    public function account(Request $request): mixed
    {
        return $this->handle($request, $request->user(), 'avatar_path', 'account-avatars/'.$request->user()->id);
    }

    public function club(Request $request, string $club, string $kind): mixed
    {
        abort_unless(in_array($kind, ['avatar', 'background'], true), 404);
        $tenant = $request->attributes->get('club');
        if (! $request->isMethod('get')) { Gate::authorize('update', $tenant); abort_unless($tenant->status === 'active', 403); }
        return $this->handle($request, $tenant, $kind.'_path', 'club-images/'.$tenant->id.'/'.$kind);
    }

    public function player(Request $request, string $club, int $member): mixed
    {
        $tenant = $request->attributes->get('club');
        $player = $tenant->players()->findOrFail($member);
        $self = $player->user_id && (int) $player->user_id === (int) $request->user()->id;
        if ($request->isMethod('get')) { if (! $self) Gate::authorize('viewRoster', $tenant); }
        else { abort_unless($tenant->status === 'active', 403); if (! $self) Gate::authorize('manageRoster', $tenant); }
        if ($request->isMethod('get') && ! $player->avatar_path && $player->user_id) {
            $account = $player->user;
            if ($account && $account->avatar_path) return $this->handle($request, $account, 'avatar_path', '');
        }
        return $this->handle($request, $player, 'avatar_path', 'player-avatars/'.$tenant->id.'/'.$player->id);
    }

    private function handle(Request $request, Model $model, string $column, string $directory): mixed
    {
        $disk = Storage::disk('local');
        if ($request->isMethod('get')) {
            $path = $model->getAttribute($column);
            abort_unless($path && $disk->exists($path), 404);
            return $disk->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        }
        $path = null;
        if ($request->isMethod('post')) {
            $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000']]);
            $path = $request->file('image')->store($directory, 'local');
            if (! $path) throw ValidationException::withMessages(['image' => ['Không thể lưu ảnh.']]);
        }
        try {
            $old = DB::transaction(function () use ($model, $column, $path) {
                $locked = $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
                $previous = $locked->getAttribute($column); $locked->setAttribute($column, $path); $locked->save(); return $previous;
            });
        } catch (\Throwable $error) {
            if ($path) $disk->delete($path);
            throw $error;
        }
        if ($old && $old !== $path) $disk->delete($old);
        return response()->json(['data' => ['version' => $path ? hash('sha256', $path) : null]]);
    }
}
