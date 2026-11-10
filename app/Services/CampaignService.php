<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Milestone;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CampaignService
{
    public function publicIndex(array $filters): LengthAwarePaginator
    {
        return Campaign::query()
            ->where('status', 'active')
            ->with(['creator:id,name,organization', 'milestones:id,campaign_id,title,description,amount_percentage,status'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->latest()
            ->paginate(12);
    }

    public function showPublic(int $id): Campaign
    {
        return Campaign::query()
            ->where('status', 'active')
            ->with(['creator:id,name,organization', 'milestones:id,campaign_id,title,description,amount_percentage,status'])
            ->findOrFail($id);
    }

    public function creatorIndex(User $creator): Collection
    {
        return $creator->campaigns()->with('milestones')->latest()->get();
    }

    public function create(User $creator, array $data): Campaign
    {
        if ($creator->verification_status !== 'approved') {
            throw ValidationException::withMessages([
                'verification' => ['Akun campaign creator harus disetujui Admin sebelum membuat campaign.'],
            ]);
        }

        $percentageTotal = collect($data['milestones'])->sum('amount_percentage');

        if ($percentageTotal !== 100) {
            throw ValidationException::withMessages([
                'milestones' => ['Total persentase seluruh milestone harus tepat 100%.'],
            ]);
        }

        $thumbnailPath = null;

        if (($data['thumbnail'] ?? null) instanceof UploadedFile) {
            $thumbnailPath = $data['thumbnail']->store('campaign-thumbnails', 'public');

            if ($thumbnailPath === false) {
                throw new RuntimeException('Thumbnail campaign gagal disimpan.');
            }
        }

        try {
            return DB::transaction(function () use ($creator, $data, $thumbnailPath): Campaign {
                $campaign = $creator->campaigns()->create([
                    'title' => $data['title'],
                    'category' => $data['category'] ?? null,
                    'description' => $data['description'],
                    'target_amount' => $data['target_amount'],
                    'thumbnail_path' => $thumbnailPath,
                    'status' => 'pending',
                ]);

                $campaign->milestones()->createMany($data['milestones']);

                return $campaign->load('milestones');
            });
        } catch (Throwable $exception) {
            if ($thumbnailPath !== null) {
                Storage::disk('public')->delete($thumbnailPath);
            }

            throw $exception;
        }
    }

    public function adminIndex(): Collection
    {
        return Campaign::with(['creator:id,name,email,organization', 'milestones'])
            ->whereIn('status', ['pending', 'active', 'rejected', 'completed'])
            ->latest()
            ->get();
    }

    public function reviewCampaign(Campaign $campaign, array $data): Campaign
    {
        if ($campaign->status !== 'pending') {
            throw ValidationException::withMessages([
                'campaign' => ['Hanya campaign berstatus pending yang dapat ditinjau.'],
            ]);
        }

        $campaign->update([
            'status' => $data['decision'] === 'approve' ? 'active' : 'rejected',
            'admin_note' => $data['note'] ?? null,
        ]);

        return $campaign->load(['creator:id,name,email', 'milestones']);
    }

    public function submitProof(
        User $creator,
        Campaign $campaign,
        Milestone $milestone,
        UploadedFile $proof,
    ): Milestone {
        abort_unless($campaign->creator_id === $creator->id, 403);
        abort_unless($milestone->campaign_id === $campaign->id, 404);
        abort_unless($campaign->status === 'active', 422, 'Bukti hanya dapat dikirim untuk campaign yang aktif.');

        if (! in_array($milestone->status, ['pending', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'milestone' => ['Milestone ini sedang menunggu atau sudah selesai ditinjau.'],
            ]);
        }

        $path = $proof->store('milestone-proofs', 'private');
        $oldPath = $milestone->proof_path;
        $milestone->update([
            'proof_path' => $path,
            'status' => 'waiting_approval',
            'admin_note' => null,
        ]);

        if ($oldPath !== null) {
            Storage::disk('private')->delete($oldPath);
        }

        return $milestone->fresh();
    }

    public function verifiedMilestoneProof(Campaign $campaign, Milestone $milestone)
    {
        abort_unless($campaign->status === 'active', 404);
        abort_unless($milestone->campaign_id === $campaign->id, 404);
        abort_unless($milestone->status === 'verified' && $milestone->proof_path, 404);

        return Storage::disk('private')->download($milestone->proof_path);
    }

    public function reviewMilestone(Milestone $milestone, array $data): Milestone
    {
        if ($milestone->status !== 'waiting_approval') {
            throw ValidationException::withMessages([
                'milestone' => ['Hanya bukti yang menunggu persetujuan yang dapat ditinjau.'],
            ]);
        }

        $milestone->update([
            'status' => $data['decision'] === 'approve' ? 'verified' : 'rejected',
            'admin_note' => $data['note'] ?? null,
        ]);

        return $milestone->fresh();
    }
}
