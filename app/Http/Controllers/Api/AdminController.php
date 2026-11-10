<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewRequest;
use App\Models\Campaign;
use App\Models\Milestone;
use App\Models\User;
use App\Services\AdminService;
use App\Services\CampaignService;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    public function __construct(
        private readonly AdminService $adminService,
        private readonly CampaignService $campaignService,
    ) {}

    public function creators(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Daftar creator pending berhasil dimuat.',
            'data' => $this->adminService->pendingCreators(),
        ]);
    }

    public function idCard(User $creator)
    {
        return $this->adminService->idCard($creator);
    }

    public function milestoneProof(Milestone $milestone)
    {
        return $this->adminService->milestoneProof($milestone);
    }

    public function reviewCreator(ReviewRequest $request, User $creator): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Keputusan verifikasi creator berhasil disimpan.',
            'data' => $this->adminService->reviewCreator($creator, $request->validated()),
        ]);
    }

    public function campaigns(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Daftar campaign untuk Admin berhasil dimuat.',
            'data' => $this->campaignService->adminIndex(),
        ]);
    }

    public function reviewCampaign(ReviewRequest $request, Campaign $campaign): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Keputusan campaign berhasil disimpan.',
            'data' => $this->campaignService->reviewCampaign($campaign, $request->validated()),
        ]);
    }

    public function reviewMilestone(ReviewRequest $request, Milestone $milestone): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Keputusan bukti milestone berhasil disimpan.',
            'data' => $this->campaignService->reviewMilestone($milestone, $request->validated()),
        ]);
    }
}
