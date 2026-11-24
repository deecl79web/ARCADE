<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Installe / supprime les fichiers des jeux dans public/games/{slug}/v{version}/.
 * Liste blanche d'extensions : aucun .php, .htaccess, .exe... ne peut être extrait.
 */
class GameFilesService
{
    public const ALLOWED_EXTENSIONS = [
        'html', 'htm', 'js', 'mjs', 'css', 'json', 'map', 'txt', 'xml', 'csv',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico',
        'mp3', 'ogg', 'wav', 'm4a', 'mp4', 'webm',
        'woff', 'woff2', 'ttf', 'otf',
        'wasm', 'data', 'bin', 'glb', 'gltf', 'atlas', 'tmx', 'tsx', 'fnt',
    ];

    private const MAX_ENTRIES = 3000;

    private const MAX_UNCOMPRESSED_BYTES = 200 * 1024 * 1024; // 200 Mo

    public function slugDir(string $slug): string
    {
        return public_path('games').DIRECTORY_SEPARATOR.$slug;
    }

    public function versionDir(string $slug, int $version): string
    {
        return $this->slugDir($slug).DIRECTORY_SEPARATOR.'v'.$version;
    }

    /** Extrait le ZIP dans games/{slug}/v{version}. Lève ValidationException si invalide. */
    public function install(UploadedFile $zipFile, string $slug, int $version): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipFile->getRealPath()) !== true) {
            $this->fail('Archive ZIP illisible.');
        }

        try {
            $entries = $this->collectEntries($zip); // [index zip => chemin relatif]

            File::ensureDirectoryExists($this->slugDir($slug));
            $tmp = $this->slugDir($slug).DIRECTORY_SEPARATOR.'.tmp-'.bin2hex(random_bytes(4));
            File::ensureDirectoryExists($tmp);

            try {
                $this->extractEntries($zip, $entries, $tmp);

                $final = $this->versionDir($slug, $version);
                if (is_dir($final)) {
                    File::deleteDirectory($final);
                }
                if (! rename($tmp, $final)) {
                    $this->fail("Impossible d'installer le jeu sur le disque.");
                }
            } catch (\Throwable $e) {
                File::deleteDirectory($tmp);
                throw $e;
            }
        } finally {
            $zip->close();
        }
    }

    public function saveThumbnail(UploadedFile $image, string $slug): string
    {
        $dir = $this->slugDir($slug);
        File::ensureDirectoryExists($dir);

        foreach (glob($dir.DIRECTORY_SEPARATOR.'thumbnail.*') ?: [] as $old) {
            @unlink($old);
        }

        $ext = strtolower($image->extension() ?: 'png');
        $image->move($dir, 'thumbnail.'.$ext);

        return "/games/{$slug}/thumbnail.{$ext}?v=".time();
    }

    public function deleteVersion(string $slug, int $version): void
    {
        if ($this->validSlug($slug)) {
            File::deleteDirectory($this->versionDir($slug, $version));
        }
    }

    public function deleteAll(string $slug): void
    {
        if ($this->validSlug($slug)) {
            File::deleteDirectory($this->slugDir($slug));
        }
    }

    private function validSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
    }

    /** Valide toutes les entrées AVANT d'extraire quoi que ce soit. */
    private function collectEntries(ZipArchive $zip): array
    {
        if ($zip->numFiles === 0) {
            $this->fail('Archive vide.');
        }
        if ($zip->numFiles > self::MAX_ENTRIES) {
            $this->fail('Archive trop volumineuse (trop de fichiers).');
        }

        $files = [];
        $declared = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = str_replace('\\', '/', $stat['name']);

            if (str_ends_with($name, '/')) {
                continue; // dossier
            }
            $base = basename($name);
            if (str_starts_with($name, '__MACOSX/') || $base === '.DS_Store' || $base === 'Thumbs.db') {
                continue;
            }

            // Anti zip-slip
            if (
                str_contains($name, "\0")
                || str_starts_with($name, '/')
                || preg_match('#^[A-Za-z]:#', $name)
                || preg_match('#(^|/)\.\.(/|$)#', $name)
            ) {
                $this->fail("Chemin interdit dans l'archive : {$name}");
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                $this->fail("Type de fichier non autorisé dans le ZIP : {$name}");
            }

            $declared += $stat['size'];
            $files[$stat['index']] = $name;
        }

        if (! $files) {
            $this->fail('Aucun fichier exploitable dans le ZIP.');
        }
        if ($declared > self::MAX_UNCOMPRESSED_BYTES) {
            $this->fail('Jeu trop volumineux une fois décompressé (200 Mo max).');
        }

        // index.html à la racine, ou dans un unique dossier racine (qu'on retire)
        $prefix = '';
        if (! in_array('index.html', $files, true)) {
            $first = explode('/', reset($files))[0].'/';
            $allInside = count(array_filter($files, fn ($n) => str_starts_with($n, $first))) === count($files);

            if ($allInside && in_array($first.'index.html', $files, true)) {
                $prefix = $first;
            } else {
                $this->fail('index.html introuvable à la racine du ZIP.');
            }
        }

        $result = [];
        foreach ($files as $index => $name) {
            $result[$index] = substr($name, strlen($prefix));
        }

        return $result;
    }

    private function extractEntries(ZipArchive $zip, array $entries, string $dest): void
    {
        $destReal = realpath($dest);
        $total = 0;

        foreach ($entries as $index => $relative) {
            $target = $dest.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            File::ensureDirectoryExists(dirname($target));

            // Défense en profondeur contre zip-slip
            $dirReal = realpath(dirname($target));
            if ($dirReal === false || ! str_starts_with($dirReal, $destReal)) {
                $this->fail("Chemin interdit dans l'archive : {$relative}");
            }

            $in = $zip->getStreamIndex($index);
            if (! $in) {
                $this->fail("Lecture impossible : {$relative}");
            }
            $out = fopen($target, 'wb');

            // On compte les octets réellement décompressés (ne pas se fier aux tailles déclarées)
            while (! feof($in)) {
                $chunk = fread($in, 65536);
                if ($chunk === false) {
                    break;
                }
                $total += strlen($chunk);
                if ($total > self::MAX_UNCOMPRESSED_BYTES) {
                    fclose($in);
                    fclose($out);
                    $this->fail('Jeu trop volumineux une fois décompressé (200 Mo max).');
                }
                fwrite($out, $chunk);
            }
            fclose($in);
            fclose($out);
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['zip' => $message]);
    }
}
