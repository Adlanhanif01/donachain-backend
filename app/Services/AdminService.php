<?php

namespace App\Services;

use App\Models\Milestone;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AdminService
{
    public function pendingCreators(): LengthAwarePaginator
    {
        return User::query()
            ->where('role', 'creator')
            ->where('verification_status', 'pending')
            ->latest()
            ->paginate(20);
    }

    public function reviewCreator(User $creator, array $data): User
    {
        if ($creator->role !== 'creator' || $creator->verification_status !== 'pending') {
            throw ValidationException::withMessages([
                'creator' => ['Akun ini bukan creator pending yang dapat ditinjau.'],
            ]);
        }

        $creator->update([
            'verification_status' => $data['decision'] === 'approve' ? 'approved' : 'rejected',
        ]);

        return $creator->fresh();
    }

    public function idCard(User $creator)
    {
        abort_unless($creator->role === 'creator' && $creator->id_card_path, 404);

        return Storage::disk('private')->download($creator->id_card_path);
    }

    public function milestoneProof(Milestone $milestone)
    {
        abort_unless($milestone->proof_path, 404);

        return Storage::disk('private')->download($milestone->proof_path);
    }
}
