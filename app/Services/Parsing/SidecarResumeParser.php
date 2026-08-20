<?php

declare(strict_types=1);

namespace App\Services\Parsing;

use App\Contracts\ResumeParser;
use App\DTOs\Parsing\ParsedResume;
use App\Exceptions\ResumeParsingFailedException;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;

/**
 * HTTP client for the Python sidecar (`sidecar/`). Injected everywhere as
 * ResumeParser — nothing else in the app knows Python exists.
 */
final readonly class SidecarResumeParser implements ResumeParser
{
    public function __construct(
        private HttpFactory $http,
        private FilesystemFactory $filesystem,
        private string $baseUrl,
        private string $token,
        private int $timeout,
        private string $disk,
    ) {}

    public function parse(string $storedPath, string $originalFilename): ParsedResume
    {
        $contents = $this->filesystem->disk($this->disk)->get($storedPath);

        if ($contents === null || $contents === '') {
            throw ResumeParsingFailedException::rejected(__('the stored file is empty or missing'));
        }

        try {
            $response = $this->http
                ->asMultipart()
                ->withToken($this->token)
                ->timeout($this->timeout)
                ->retry(2, 500, throw: false)
                ->attach('file', $contents, $originalFilename, ['Content-Type' => 'application/pdf'])
                ->post(rtrim($this->baseUrl, '/').'/v1/parse')
                ->throw();
        } catch (ConnectionException $exception) {
            throw ResumeParsingFailedException::unreachable($exception);
        } catch (RequestException $exception) {
            // 422 means the sidecar understood the request and rejected the document.
            if ($exception->response->status() === 422) {
                throw ResumeParsingFailedException::rejected(
                    $this->reasonFrom($exception->response->json('detail')),
                );
            }

            throw ResumeParsingFailedException::unreachable($exception);
        }

        /** @var array<string, mixed>|null $payload */
        $payload = $response->json();

        if (! is_array($payload)) {
            throw ResumeParsingFailedException::unreachable();
        }

        return ParsedResume::fromArray($payload);
    }

    private function reasonFrom(mixed $detail): string
    {
        if (is_string($detail) && trim($detail) !== '') {
            return $detail;
        }

        return __('the document format is not supported');
    }
}
