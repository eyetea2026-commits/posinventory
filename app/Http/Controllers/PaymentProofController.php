<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Staff;
use App\Services\PaymentProof;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Shows a sale's proof of payment photo. Administrators can open any;
 * a Cashier only the ones on their own sales. The photos are on the
 * private disk, so this is the only way to see them.
 */
class PaymentProofController extends Controller
{
    public function __invoke(Payment $payment)
    {
        $user = Auth::user();
        abort_unless($user && ($user->isAdmin() || $user->isCashier()), 403);

        if (! $user->isAdmin()) {
            $staffId = Staff::where('UserID', $user->id)->value('StaffID');
            $ownSale = $staffId !== null && (int) $payment->billing?->transaction?->StaffID === (int) $staffId;
            abort_unless($ownSale, 403);
        }

        abort_unless($payment->ProofImagePath && Storage::disk(PaymentProof::DISK)->exists($payment->ProofImagePath), 404);

        return Storage::disk(PaymentProof::DISK)->response($payment->ProofImagePath, null, [
            'Cache-Control' => 'private, max-age=600',
        ]);
    }
}
