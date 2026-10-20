<?php
declare(strict_types=1);

// JSON files in data/: the only way stages talk to each other.
final class Files
{
    public static function read(string $path, $default = null)
    {
        if (!is_file($path)) {
            return $default;
        }
        $data = json_decode((string) file_get_contents(str_ends_with($path, '.gz') ? 'compress.zlib://' . $path : $path), true);
        return $data ?? $default;
    }

    // Writes via a temp file and rename, so readers never see half a file.
    public static function write(string $path, $data): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $tmp = $path . '.tmp';
        file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        rename($tmp, $path);
    }

    // Snapshots are never overwritten: a second run on the same day must say so explicitly.
    public static function writeOnce(string $path, $data, bool $replace = false): void
    {
        if (is_file($path) && !$replace) {
            throw new RuntimeException(basename($path) . ' already exists; past files are never edited (use --replace for today only).');
        }
        self::write($path, $data);
    }
}
