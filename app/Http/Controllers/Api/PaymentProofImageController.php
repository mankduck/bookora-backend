<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentProof;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofImageController extends Controller
{
    public function __invoke(
        Request $request,
        PaymentProof $proof
    ): StreamedResponse {
        $user = $request->user();

        $isOwner =
            (int) $proof->customer_id ===
            (int) $user->id;

        $isAdmin = $user
            ->roles()
            ->where('code', 'admin')
            ->exists();

        if (!$isOwner && !$isAdmin) {
            abort(403);
        }

        if (
            !$proof->image_path ||
            str_starts_with(
                $proof->image_path,
                'manual://'
            ) ||
            !Storage::disk('public')->exists(
                $proof->image_path
            )
        ) {
            abort(404);
        }

        return Storage::disk('public')->response(
            $proof->image_path,
            null,
            [
                'Cache-Control' =>
                    'private, max-age=300',
            ]
        );
    }
}
