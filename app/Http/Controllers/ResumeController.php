<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Resume\StoreResume;
use App\DTOs\ResumeUploadData;
use App\Http\Requests\Resume\StoreResumeRequest;
use App\Http\Resources\ResumeResource;
use App\Jobs\ParseResumeJob;
use App\Models\Company;
use App\Models\Resume;
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
            'resume' => new ResumeResource($resume->load('company')),
            'canDownload' => $resume->uploaded_by === auth()->id(),
        ]);
    }

    /**
     * Re-run the parser (e.g. after the sidecar was down).
     */
    public function reparse(Resume $resume): RedirectResponse
    {
        $this->authorize('reparse', $resume);

        ParseResumeJob::dispatch($resume->id);

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
}
