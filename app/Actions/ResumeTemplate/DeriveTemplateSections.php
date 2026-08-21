<?php

declare(strict_types=1);

namespace App\Actions\ResumeTemplate;

use App\Contracts\ResumeParser;
use App\Enums\ResumeStatus;
use App\Exceptions\ResumeParsingFailedException;
use App\Models\ResumeTemplate;
use App\Services\Ats\AtsResumeFormatter;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;

/**
 * Reads a sample resume and adopts the order it printed its sections in as the
 * template's section order.
 *
 * Called from DeriveTemplateSectionsJob — never from a request (RULES §6.6).
 * Only the *structure* is taken: which sections the document has and in what
 * order. Typography and header shape stay with the base layout, which is a code
 * decision (see CLAUDE.md "Templates are data, layouts are code").
 */
final readonly class DeriveTemplateSections
{
    public function __construct(
        private ResumeParser $parser,
        private DatabaseManager $db,
        private LoggerInterface $logger,
    ) {}

    public function handle(ResumeTemplate $template): ResumeTemplate
    {
        if ($template->sample_path === null) {
            return $template;
        }

        $template->update([
            'sample_status' => ResumeStatus::Processing,
            'sample_failure_reason' => null,
        ]);

        try {
            $parsed = $this->parser->parse(
                $template->sample_path,
                $template->sample_filename ?? 'sample.pdf',
            );
        } catch (ResumeParsingFailedException $exception) {
            // PII stays out of the log: identifiers only (RULES §5, SCHEMA §A7).
            $this->logger->warning('template.sample_parse_failed', [
                'template_id' => $template->public_id,
                'reason' => $exception->getMessage(),
            ]);

            $template->update([
                'sample_status' => ResumeStatus::Failed,
                'sample_failure_reason' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $order = $this->supported($parsed->sectionOrder);

        if ($order === []) {
            $template->update([
                'sample_status' => ResumeStatus::Failed,
                'sample_failure_reason' => __('No recognisable sections were found in the sample, so the layout default is kept.'),
            ]);

            return $template->refresh();
        }

        $this->db->transaction(function () use ($template, $order): void {
            $template->update([
                'section_order' => $order,
                'sample_status' => ResumeStatus::Parsed,
                'sample_failure_reason' => null,
            ]);
        });

        $this->logger->info('template.sample_parsed', [
            'template_id' => $template->public_id,
            'sections' => count($order),
        ]);

        return $template->refresh();
    }

    /**
     * Keep only the sections the formatter can emit, in the document's order and
     * without repeats.
     *
     * @param  list<string>  $order
     * @return list<string>
     */
    private function supported(array $order): array
    {
        return array_values(array_unique(array_filter(
            $order,
            static fn (string $key): bool => in_array($key, AtsResumeFormatter::SUPPORTED_SECTIONS, true),
        )));
    }
}
