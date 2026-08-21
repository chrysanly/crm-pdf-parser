<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Resume\ChangeResumeTemplate;
use App\Actions\Resume\RequeueResume;
use App\Actions\Resume\StoreResume;
use App\DTOs\ResumeUploadData;
use App\Http\Requests\Resume\ChangeResumeTemplateRequest;
use App\Http\Requests\Resume\StoreResumeRequest;
use App\Http\Resources\ResumeResource;
use App\Http\Resources\ResumeTemplateCardResource;
use App\Models\Company;
use App\Models\Resume;
use App\Models\ResumeTemplate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class ResumeController extends Controller
{
    /**
     * Upload a candidate PDF against a company. Parsing happens on the queue —
     * the response returns immediately (RULES §6.6).
     */
    public function store(StoreResumeRequest $request, Company $company, StoreResume $action): RedirectResponse
    {
        $resume = $action->handle(ResumeUploadData::fromRequest($request, $company));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Resume uploaded — parsing now.'),
        ]);

        return to_route('resumes.show', $resume);
    }

    public function show(Resume $resume): Response
    {
        $this->authorize('view', $resume);

        return Inertia::render('resumes/show', [
            'resume' => new ResumeResource($resume->load(['company.resumeTemplate', 'resumeTemplate'])),
            'canDownload' => $resume->uploaded_by === auth()->id(),
            // The styles this document can be switched to, without re-parsing it.
            'templates' => $this->templateOptions(),
        ]);
    }

    /**
     * Re-style this document with another template. Presentation only — the
     * parsed data is untouched, so no re-parse is needed.
     */
    public function template(
        ChangeResumeTemplateRequest $request,
        Resume $resume,
        ChangeResumeTemplate $action,
    ): RedirectResponse {
        $action->handle($resume, $request->template());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template applied.')]);

        return back();
    }

    /**
     * Re-run the parser (e.g. after the sidecar was down).
     */
    public function reparse(Resume $resume, RequeueResume $action): RedirectResponse
    {
        $this->authorize('reparse', $resume);

        $action->handle($resume);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Re-parsing queued.')]);

        return to_route('resumes.show', $resume);
    }

    public function destroy(Resume $resume): RedirectResponse
    {
        $this->authorize('delete', $resume);

        $company = $resume->company;

        $resume->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Resume removed.')]);

        return to_route('companies.show', $company);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function templateOptions(): array
    {
        /** @var list<array<string, mixed>> $options */
        $options = array_values(ResumeTemplateCardResource::collection(
            ResumeTemplate::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'public_id', 'slug', 'name', 'description', 'layout', 'section_order', 'is_active']),
        )->resolve());

        return $options;
    }
}
