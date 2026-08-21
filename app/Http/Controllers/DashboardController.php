<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ResumeCardResource;
use App\Models\Company;
use App\Services\Dashboard\DashboardSummary;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Thin by contract: one service for the figures, Resources for the entity lists
 * (ARCHITECTURE §3).
 */
final class DashboardController extends Controller
{
    public function __invoke(DashboardSummary $summary): Response
    {
        $this->authorize('viewAny', Company::class);

        return Inertia::render('dashboard', [
            'summary' => $summary->build(),
            'attention' => ResumeCardResource::collection($summary->needsAttention()),
            'recent' => ResumeCardResource::collection($summary->recent()),
        ]);
    }
}
