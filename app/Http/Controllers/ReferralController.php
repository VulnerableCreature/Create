<?php

namespace App\Http\Controllers;

use App\Models\Master;
use App\Models\Referral;
use App\Models\ReferralEarning;
use App\Services\Referral\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(
        private readonly ReferralService $referralService,
    ) {}

    // My referrals
    public function referrals(Request $request): JsonResponse
    {
        $master = $request->attributes->get('current_master');

        if (!$master instanceof Master) {
            return response()->json([
                'message' => 'Master not found'
            ], 404);
        }

        $referrals = Referral::query()
            ->with([
                'referredMaster:name',
            ])
            ->withSum('earnings', 'amount')
            ->where('referrer_master_id', '=', $master->id)
            ->get();

        return response()->json([
            'data' => $referrals
        ]);
    }

    // Attach referral
    public function attach(Request $request): JsonResponse
    {
        $master = $request->attributes->get('current_master');

        if (!$master instanceof Master) {
            return response()->json([
                'message' => 'Master not found'
            ], 404);
        }

        $validated = $request->validate([
            'code' => 'required|string',
        ]);

        //        $existingReferral = Referral::query()
        //            ->where('referred_master_id', '=', $master->id)
        //            ->exists();
        //
        //        if($existingReferral){
        //            return response()->json([
        //                'message' => 'Referral already exists'
        //            ]);
        //        }

        $referred = Master::query()->where('referral_code', '=', $validated['code'])->first();

        if (!$referred instanceof Master) {
            return response()->json([
                'message' => 'Master not found'
            ], 404);
        }

        if ($master->id === $referred->id) {
            return response()->json([
                'message' => 'Вы не можете пригласить сами себя (стать своим рефералом)'
            ]);
        }

        $referral = $this->referralService->registerReferral($master, $validated['code']);

        if (is_null($referral)) {
            return response()->json([
                'message' => 'Error attach referral'
            ]);
        }

        return response()->json([
            'message' => 'Referral attached',
            'data' => $referral
        ]);
    }

    // Earning
    public function earnings(Request $request): JsonResponse
    {
        $currentMaster = $request->attributes->get('current_master');

        if (!$currentMaster instanceof Master) {
            return response()->json([
                'message' => 'Master not found'
            ]);
        }

        $earnings = ReferralEarning::query()
            ->where('referrer_master_id', '=', $currentMaster->id)
            ->get();

        return response()->json([
            'total' => $earnings->sum('amount'),
            'pending' => $earnings->where('status', '=', ReferralEarning::STATUS_PENDING)->sum('amount'),
            'paid' => $earnings->where('status', '=', ReferralEarning::STATUS_PAID)->sum('amount'),
            'referrals' => Referral::query()
                ->where('referrer_master_id', '=', $currentMaster->id)
                ->where('status', '=', Referral::STATUS_REWARDED)
                ->count()
        ]);
    }
}
