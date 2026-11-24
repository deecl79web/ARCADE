<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\ArcadeTestCase;
use ZipArchive;

class GameUploadTest extends ArcadeTestCase
{
    private function zip(array $files): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        $bytes = file_get_contents($path);
        @unlink($path);

        return UploadedFile::fake()->createWithContent('game.zip', $bytes);
    }

    private function payload(UploadedFile $zip, string $slug): array
    {
        return [
            'title' => 'Jeu de test',
            'slug' => $slug,
            'max_score' => 1000,
            'points_per_100_score' => 10,
            'min_duration_seconds' => 5,
            'zip' => $zip,
        ];
    }

    private function slug(): string
    {
        return 'test-'.Str::lower(Str::random(8));
    }

    public function test_zip_containing_a_php_file_is_refused(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $slug = $this->slug();

        $this->postJson('/api/admin/games', $this->payload(
            $this->zip(['index.html' => '<h1>ok</h1>', 'shell.php' => '<?php echo 1;']),
            $slug
        ))->assertStatus(422)->assertJsonValidationErrors('zip');

        $this->assertDatabaseCount('games', 0);
        $this->assertDirectoryDoesNotExist(public_path("games/{$slug}"));
    }

    public function test_zip_without_index_html_is_refused(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $slug = $this->slug();

        $this->postJson('/api/admin/games', $this->payload(
            $this->zip(['game.js' => 'console.log(1)']),
            $slug
        ))->assertStatus(422)->assertJsonValidationErrors('zip');

        $this->assertDatabaseCount('games', 0);
    }

    public function test_non_admin_cannot_upload_a_game(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/admin/games', $this->payload(
            $this->zip(['index.html' => '<h1>ok</h1>']),
            $this->slug()
        ))->assertForbidden();
    }

    public function test_valid_zip_is_installed_served_with_cors_and_replaced_by_a_new_version(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $slug = $this->slug();

        try {
            // Le ZIP contient un dossier racine : il doit être retiré automatiquement
            $this->postJson('/api/admin/games', $this->payload(
                $this->zip(['mon-jeu/index.html' => '<h1>v1</h1>', 'mon-jeu/game.js' => 'var a = 1;']),
                $slug
            ))->assertCreated()->assertJsonPath('version', 1);

            $this->assertFileExists(public_path("games/{$slug}/v1/index.html"));

            $this->get("/api/play/{$slug}/v1/index.html")
                ->assertOk()
                ->assertHeader('Access-Control-Allow-Origin', '*');

            // Tentative de sortie du dossier du jeu
            $this->get("/api/play/{$slug}/v1/..%2F..%2F..%2F.env")->assertNotFound();

            $game = Game::where('slug', $slug)->firstOrFail();

            // Remplacement : v2 installée, v1 supprimée
            $this->postJson("/api/admin/games/{$game->id}/files", [
                'zip' => $this->zip(['index.html' => '<h1>v2</h1>']),
            ])->assertOk()->assertJsonPath('version', 2);

            $this->assertFileExists(public_path("games/{$slug}/v2/index.html"));
            $this->assertDirectoryDoesNotExist(public_path("games/{$slug}/v1"));
            $this->assertStringContainsString("/api/play/{$slug}/v2/index.html", $game->fresh()->entry_url);
        } finally {
            File::deleteDirectory(public_path("games/{$slug}"));
        }
    }
}
