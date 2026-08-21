<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores the sample resume a template was built from. It is a real candidate CV,
 * so it lives on the **private** disk with a random filename and is served to
 * nobody — only the parser reads it (RULES §5.7, CLAUDE.md §6.1).
 */
final readonly class TemplateSampleStorage
{
    public function __construct(
        private FilesystemFactory $filesystem,
        private string $directory = 'template-samples',
    ) {}

    /**
     * @return string the path on the private disk
     */
    public function store(UploadedFile $sample, string $templatePublicId): string
    {
        $path = $this->filesystem->disk($this->disk())->putFileAs(
            $this->directory.'/'.$templatePublicId,
            $sample,
            Str::random(40).'.pdf',
        );

        if ($path === false) {
            throw new RuntimeException('The sample resume could not be stored.');
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $this->filesystem->disk($this->disk())->delete($path);
    }

    private function disk(): string
    {
        return (string) config('crm.resume_disk', 'local');
    }
}
