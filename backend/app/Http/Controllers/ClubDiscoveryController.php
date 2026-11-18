<?php
namespace App\Http\Controllers;

use App\Models\Club;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClubDiscoveryController extends Controller
{
    public function index(Request $request): mixed
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = Club::where('is_discoverable', true)->where('status', 'active')->whereDoesntHave('memberships', fn ($q) => $q->where('user_id', $request->user()->id)->where('status', 'active'));
        if (! empty($data['q'])) { $term = '%'.addcslashes($data['q'], '%_\\').'%'; $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('city', 'like', $term)); }
        $clubs = $query->orderBy('name')->orderBy('id')->paginate(12);
        return response()->json(['data' => $clubs->getCollection()->map(fn ($club) => ['id' => $club->id, 'name' => $club->name, 'city' => $club->city, 'formats' => $club->formats ?: [$club->format], 'avatar_version' => $club->avatar_path ? hash('sha256', $club->avatar_path) : null]), 'meta' => ['current_page' => $clubs->currentPage(), 'last_page' => $clubs->lastPage(), 'total' => $clubs->total()]]);
    }

    public function avatar(int $club): mixed
    {
        $target = Club::where('is_discoverable', true)->where('status', 'active')->findOrFail($club); $disk = Storage::disk('local');
        abort_unless($target->avatar_path && $disk->exists($target->avatar_path), 404);
        return $disk->response($target->avatar_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
