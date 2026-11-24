<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdController extends Controller
{
    public function index()
    {
        return Ad::latest('id')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'position' => ['required', Rule::in(Ad::POSITIONS)],
            'link_url' => ['required', 'url:http,https', 'max:500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'active' => ['sometimes', 'boolean'],
            'image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,gif', 'max:1024'],
        ]);

        $start = $this->parseDate($data['starts_at'] ?? null);
        $end = $this->parseDate($data['ends_at'] ?? null);
        $this->assertDates($start, $end);

        $ad = Ad::create([
            'title' => $data['title'],
            'position' => $data['position'],
            'link_url' => $data['link_url'],
            'starts_at' => $start,
            'ends_at' => $end,
            'active' => $request->boolean('active', true),
            'image_path' => $this->storeImage($request->file('image')),
        ]);

        return response()->json($ad, 201);
    }

    public function update(Request $request, Ad $ad)
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:100'],
            'position' => ['sometimes', Rule::in(Ad::POSITIONS)],
            'link_url' => ['sometimes', 'url:http,https', 'max:500'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'active' => ['sometimes', 'boolean'],
        ]);

        foreach (['starts_at', 'ends_at'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = $this->parseDate($data[$key]);
            }
        }

        $this->assertDates(
            array_key_exists('starts_at', $data) ? $data['starts_at'] : $ad->starts_at,
            array_key_exists('ends_at', $data) ? $data['ends_at'] : $ad->ends_at,
        );

        $ad->update($data);

        return $ad;
    }

    public function image(Request $request, Ad $ad)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,gif', 'max:1024'],
        ]);

        $this->deleteImage($ad);
        $ad->update(['image_path' => $this->storeImage($request->file('image'))]);

        return $ad;
    }

    public function destroy(Ad $ad)
    {
        $this->deleteImage($ad);
        $ad->delete();

        return response()->noContent();
    }

    /** Convertit n'importe quelle date reçue (ISO avec fuseau, etc.) vers le fuseau de l'application. */
    private function parseDate(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value)->timezone(config('app.timezone')) : null;
    }

    private function assertDates(?Carbon $start, ?Carbon $end): void
    {
        if ($start && $end && $end->lte($start)) {
            throw ValidationException::withMessages(['ends_at' => 'La date de fin doit être après la date de début.']);
        }
    }

    private function storeImage(UploadedFile $file): string
    {
        File::ensureDirectoryExists(public_path('ads'));

        $name = Str::uuid().'.'.strtolower($file->extension() ?: 'png');
        $file->move(public_path('ads'), $name);

        return '/ads/'.$name;
    }

    private function deleteImage(Ad $ad): void
    {
        $path = public_path(ltrim($ad->image_path, '/'));
        if (str_starts_with($ad->image_path, '/ads/') && is_file($path)) {
            @unlink($path);
        }
    }
}
