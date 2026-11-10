<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\StoreMilestoneProofRequest;
use App\Models\Campaign;
use App\Models\Milestone;
use App\Services\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CampaignController extends Controller
{
    public function __construct(private readonly CampaignService $campaignService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'category' => ['sometimes', 'string', 'max:100'],
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Daftar campaign berhasil dimuat.',
            'data' => $this->campaignService->publicIndex($filters),
        ]);
    }

    public function show(int $campaign): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Detail campaign berhasil dimuat.',
            'data' => $this->campaignService->showPublic($campaign),
        ]);
    }

    public function thumbnail(Campaign $campaign)
    {
        abort_unless($campaign->thumbnail_path, 404);

        return Storage::disk('public')->response($campaign->thumbnail_path);
    }

    public function verifiedMilestoneProof(Campaign $campaign, Milestone $milestone)
    {
        return $this->campaignService->verifiedMilestoneProof($campaign, $milestone);
    }

    public function creatorIndex(Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Campaign Anda berhasil dimuat.',
            'data' => $this->campaignService->creatorIndex($request->user()),
        ]);
    }

    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $campaign = $this->campaignService->create($request->user(), $request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Campaign berhasil diajukan untuk persetujuan Admin.',
            'data' => $campaign,
        ], 201);
    }

    public function submitProof(
        StoreMilestoneProofRequest $request,
        Campaign $campaign,
        Milestone $milestone,
    ): JsonResponse {
        return response()->json([
            'status' => true,
            'message' => 'Bukti milestone berhasil dikirim untuk ditinjau.',
            'data' => $this->campaignService->submitProof(
                $request->user(),
                $campaign,
                $milestone,
                $request->file('proof'),
            ),
        ]);
    }
}
