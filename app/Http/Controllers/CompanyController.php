<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Company\CreateCompany;
use App\Actions\Company\DeleteCompany;
use App\Actions\Company\UpdateCompany;
use App\DTOs\CompanyData;
use App\Enums\LogoPlacement;
use App\Enums\LogoSize;
use App\Enums\ResumeTemplate;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\CompanyCardResource;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\ResumeCardResource;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Thin by contract: validate (FormRequest) → one Action → response
 * (ARCHITECTURE §3).
 */
final class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Company::class);

        $search = $request->string('search')->toString();

        $companies = Company::query()
            ->select(['id', 'public_id', 'slug', 'name', 'industry', 'logo_path', 'logo_placement', 'logo_size', 'brand_color', 'resume_template', 'is_active'])
            ->withCount('resumes')
            ->search($search)
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('companies/index', [
            'companies' => CompanyCardResource::collection($companies),
            'filters' => ['search' => $search === '' ? null : $search],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Company::class);

        return Inertia::render('companies/create', [
            'templates' => ResumeTemplate::options(),
            'logoPlacements' => LogoPlacement::options(),
            'logoSizes' => LogoSize::options(),
        ]);
    }

    public function store(StoreCompanyRequest $request, CreateCompany $action): RedirectResponse
    {
        $company = $action->handle(CompanyData::fromRequest($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company created.')]);

        return to_route('companies.show', $company);
    }

    public function show(Request $request, Company $company): Response
    {
        $this->authorize('view', $company);

        $resumes = $company->resumes()
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('companies/show', [
            'company' => new CompanyResource($company->loadCount('resumes')),
            'resumes' => ResumeCardResource::collection($resumes),
        ]);
    }

    public function edit(Company $company): Response
    {
        $this->authorize('update', $company);

        return Inertia::render('companies/edit', [
            'company' => new CompanyResource($company),
            'templates' => ResumeTemplate::options(),
            'logoPlacements' => LogoPlacement::options(),
            'logoSizes' => LogoSize::options(),
        ]);
    }

    public function update(UpdateCompanyRequest $request, Company $company, UpdateCompany $action): RedirectResponse
    {
        $action->handle($company, CompanyData::fromRequest($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company updated.')]);

        return to_route('companies.show', $company);
    }

    public function destroy(Company $company, DeleteCompany $action): RedirectResponse
    {
        $this->authorize('delete', $company);

        $action->handle($company);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company archived.')]);

        return to_route('companies.index');
    }
}
