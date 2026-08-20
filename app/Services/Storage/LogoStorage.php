<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores company logos with a random filename and **re-encodes** the bitmap
 * (RULES §5.7) so nothing executable survives inside the image. SVG is refused
 * by validation, not here — SVG cannot be re-encoded safely.
 */
final readonly class LogoStorage
{
    private const int MAX_EDGE = 512;

    public function __construct(
        private FilesystemFactory $filesystem,
        private string $disk = 'public',
        private string $directory = 'company-logos',
    ) {}

    /**
     * @return string the path on the public disk
     */
    public function store(UploadedFile $logo): string
    {
        $path = $this->directory.'/'.Str::random(40).'.png';

        $this->filesystem->disk($this->disk)->put($path, $this->reencode($logo));

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $this->filesystem->disk($this->disk)->delete($path);
    }

    /**
     * Decode → downscale → re-encode as PNG. Any payload smuggled in the original
     * container is dropped because only pixels survive.
     */
    private function reencode(UploadedFile $logo): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($logo->getRealPath()));

        if ($source === false) {
            throw new RuntimeException('The uploaded logo could not be decoded as an image.');
        }

        try {
            $width = imagesx($source);
            $height = imagesy($source);
            $scale = min(1.0, self::MAX_EDGE / max($width, $height));

            $target = imagescale($source, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));

            if ($target === false) {
                throw new RuntimeException('The uploaded logo could not be resized.');
            }

            try {
                imagealphablending($target, false);
                imagesavealpha($target, true);

                ob_start();
                imagepng($target, null, 9);

                return (string) ob_get_clean();
            } finally {
                imagedestroy($target);
            }
        } finally {
            imagedestroy($source);
        }
    }
}
