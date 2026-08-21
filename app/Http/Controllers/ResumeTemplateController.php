<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ResumeTemplate\CreateResumeTemplate;
use App\Actions\ResumeTemplate\DeleteResumeTemplate;
use App\Actions\ResumeTemplate\UpdateResumeTemplate;
use App\DTOs\ResumeTemplateData;
use App\Enums\TemplateLayout;
use App\Exceptions\ResumeTemplateInUseException;
use App\Http\Requests\ResumeTemplate\StoreResumeTemplateRequest;
use App\Http\Requests\ResumeTemplate\UpdateResumeTemplateRequest;
use App\Http\Resources\ResumeTemplateCardResource;
use App\Http\Resources\ResumeTemplateResource;
use App\Models\ResumeTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Thin by contract: validate (FormRequest) → one Action → response
 * (ARCHITECTURE §3).
 */
final class ResumeTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ResumeTemplate::class);

        $search = $request->string('search')->toString();

        $templates = ResumeTemplate::query()
            ->select(['id', 'public_id', 'slug', 'name', 'description', 'layout', 'section_order', 'is_active'])
            ->withCount(['companies', 'resumes'])
            ->search($search)
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('resume-templates/index', [
            'templates' => ResumeTemplateCardResource::collection($templates),
            'filters' => ['search' => $search === '' ? null : $search],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ResumeTemplate::class);

        return Inertia::render('resume-templates/create', [
            'layouts' => TemplateLayout::options(),
        ]);
    }

    public function store(StoreResumeTemplateRequest $request, CreateResumeTemplate $action): RedirectResponse
    {
        $action->handle(ResumeTemplateData::fromRequest($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template created.')]);

        return to_route('resume-templates.index');
    }

    public function edit(ResumeTemplate $resumeTemplate): Response
    {
        $this->authorize('update', $resumeTemplate);

        return Inertia::render('resume-templates/edit', [
            'template' => new ResumeTemplateResource($resumeTemplate->loadCount(['companies', 'resumes'])),
            'layouts' => TemplateLayout::options(),
        ]);
    }

    public function update(
        UpdateResumeTemplateRequest $request,
        ResumeTemplate $resumeTemplate,
        UpdateResumeTemplate $action,
    ): RedirectResponse {
        $action->handle($resumeTemplate, ResumeTemplateData::fromRequest($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template updated.')]);

        return to_route('resume-templates.index');
    }

    public function destroy(ResumeTemplate $resumeTemplate, DeleteResumeTemplate $action): RedirectResponse
    {
        $this->authorize('delete', $resumeTemplate);

        try {
            $action->handle($resumeTemplate);
        } catch (ResumeTemplateInUseException) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Reassign the companies using this template before archiving it.'),
            ]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template archived.')]);

        return to_route('resume-templates.index');
    }
}
