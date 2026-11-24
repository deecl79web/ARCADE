<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\GameFilesService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GameController extends Controller
{
    public function __construct(private GameFilesService $files) {}

    public function index()
    {
        return Game::withCount('sessions')->latest('id')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:games,slug'],
            'description' => ['nullable', 'string', 'max:1000'],
            'max_score' => ['required', 'integer', 'min:1', 'max:2000000000'],
            'points_per_100_score' => ['required', 'integer', 'min:0', 'max:100000'],
            'min_duration_seconds' => ['required', 'integer', 'min:0', 'max:3600'],
            'active' => ['sometimes', 'boolean'],
            'zip' => ['required', 'file', 'mimes:zip', 'max:'.config('arcade.max_zip_kb')],
            'thumbnail' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ]);

        if (! empty($data['slug'])) {
            $slug = $data['slug'];
        } else {
            $base = Str::slug(Str::limit($data['title'], 50, ''));
            if ($base === '') {
                throw ValidationException::withMessages(['title' => 'Titre invalide pour générer une adresse (slug).']);
            }
            $slug = $this->uniqueSlug($base);
        }

        try {
            $this->files->install($request->file('zip'), $slug, 1);

            $thumbnail = $request->hasFile('thumbnail')
                ? $this->files->saveThumbnail($request->file('thumbnail'), $slug)
                : null;

            $game = Game::create([
                'slug' => $slug,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'thumbnail_url' => $thumbnail,
                'entry_url' => "/api/play/{$slug}/v1/index.html",
                'version' => 1,
                'active' => $request->boolean('active', true),
                'max_score' => $data['max_score'],
                'points_per_100_score' => $data['points_per_100_score'],
                'min_duration_seconds' => $data['min_duration_seconds'],
            ]);
        } catch (\Throwable $e) {
            $this->files->deleteAll($slug); // ne laisse pas de fichiers orphelins
            throw $e;
        }

        return response()->json($game->loadCount('sessions'), 201);
    }

    public function update(Request $request, Game $game)
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'max_score' => ['sometimes', 'integer', 'min:1', 'max:2000000000'],
            'points_per_100_score' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'min_duration_seconds' => ['sometimes', 'integer', 'min:0', 'max:3600'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $game->update($data);

        return $game->loadCount('sessions');
    }

    /** Remplace le ZIP (nouvelle version) et/ou la miniature. */
    public function files(Request $request, Game $game)
    {
        $request->validate([
            'zip' => ['nullable', 'file', 'mimes:zip', 'max:'.config('arcade.max_zip_kb')],
            'thumbnail' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ]);

        if (! $request->hasFile('zip') && ! $request->hasFile('thumbnail')) {
            throw ValidationException::withMessages(['zip' => 'Envoie un ZIP et/ou une miniature.']);
        }

        if ($request->hasFile('zip')) {
            $old = $game->version;
            $new = $old + 1;

            $this->files->install($request->file('zip'), $game->slug, $new);

            $game->update([
                'version' => $new,
                'entry_url' => "/api/play/{$game->slug}/v{$new}/index.html",
            ]);

            $this->files->deleteVersion($game->slug, $old);
        }

        if ($request->hasFile('thumbnail')) {
            $game->update([
                'thumbnail_url' => $this->files->saveThumbnail($request->file('thumbnail'), $game->slug),
            ]);
        }

        return $game->loadCount('sessions');
    }

    public function destroy(Request $request, Game $game)
    {
        if (! $request->boolean('force') && $game->sessions()->exists()) {
            return response()->json([
                'message' => 'Ce jeu a des parties enregistrées : le supprimer effacera aussi cet historique de scores.',
            ], 409);
        }

        $slug = $game->slug;
        $game->delete();
        $this->files->deleteAll($slug);

        return response()->noContent();
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 2;
        while (Game::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
