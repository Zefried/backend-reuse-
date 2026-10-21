<?php

namespace App\Http\Controllers\ChatEngine;

use App\Http\Controllers\Controller;
use App\Models\CampaignInformation;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChatEnginerController extends Controller
{
    /**
     * Helper to decode token and derive business and campaign information.
     * The backend derives the business and campaign strictly from the token;
     * any frontend-provided business_id is never trusted as the source of truth.
     */
    private function resolveBusinessAndCampaign(string $token, ?string $campaignLink = null): array
    {
        $decoded = base64_decode($token, true);
        if (!$decoded || !str_contains($decoded, '||')) {
            return ['error' => 'Invalid or malformed chat token.', 'code' => 400];
        }

        [$businessId, $randomStr] = explode('||', $decoded, 2);
        $businessId = trim($businessId);

        $business = User::where('business_id', $businessId)->first();
        if (!$business) {
            return ['error' => 'Business associated with this token not found.', 'code' => 404];
        }

        $campaign = null;
        if ($campaignLink) {
            $campaign = CampaignInformation::where('business_id', $businessId)
                ->where('campaign_link', $campaignLink)
                ->first();
        }

        if (!$campaign) {
            $campaign = CampaignInformation::where('business_id', $businessId)
                ->where('campaign_link', 'LIKE', '%/chat/' . $token)
                ->first();
        }

        return [
            'business'   => $business,
            'businessId' => $businessId,
            'campaign'   => $campaign,
        ];
    }

    /**
     * POST /api/public/chat/session/init
     * Initializes the public chat session, resolves nullable service,
     * and provides the two initial message options.
     */
    public function initSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token'         => 'required|string',
            'campaign_link' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'code'    => 422,
            ], 422);
        }

        $resolved = $this->resolveBusinessAndCampaign($request->input('token'), $request->input('campaign_link'));
        if (isset($resolved['error'])) {
            return response()->json([
                'status'  => false,
                'message' => $resolved['error'],
                'code'    => $resolved['code'],
            ], $resolved['code']);
        }

        $business = $resolved['business'];
        $businessId = $resolved['businessId'];
        $campaign = $resolved['campaign'];

        // ── Nullable Service Resolution ───────────────────────────────────────
        $serviceId = null;
        $serviceName = null;

        if ($campaign && !empty($campaign->service_id)) {
            $service = Service::where('id', $campaign->service_id)
                ->where('business_id', $businessId)
                ->first();

            if ($service) {
                $serviceId = $service->id;
                $serviceName = $service->name;
            }
        }

        // If service_id is null or service record not found:
        // keep service_name as null. Never fall back to another service.
        if ($serviceName) {
            $options = [
                "Tell me about the price of {$serviceName}.",
                "Share the portfolio of {$serviceName}.",
            ];
        } else {
            $options = [
                "Tell me about your pricing.",
                "Share your portfolio.",
            ];
        }

        // TODO: Implement chat history and session resumption logic:
        // 1. Look up existing conversation history for this visitor/session/token.
        // 2. If existing conversation history is found, restore messages and resume conversation state.
        // 3. If no history exists, initialize a new conversation session with initial options.

        return response()->json([
            'status' => true,
            'data'   => [
                'business_id'   => $businessId,
                'business_name' => $business->name ?? '',
                'bio'           => $business->bio ?? '',
                'service_id'    => $serviceId,
                'service_name'  => $serviceName,
                'options'       => $options,
            ],
            'code'   => 200,
        ], 200);
    }
}
