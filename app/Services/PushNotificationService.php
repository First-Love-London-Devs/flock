<?php

namespace App\Services;

use App\Models\Leader;
use App\Models\PushToken;
use App\Models\Group;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    protected string $expoUrl = 'https://exp.host/--/api/v2/push/send';

    public function sendToLeader(int $leaderId, string $title, string $body, array $data = []): array
    {
        $tokens = PushToken::where('leader_id', $leaderId)
            ->where('is_active', true)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            return ['success' => false, 'error' => 'No push tokens found'];
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    public function sendToGroupLeaders(int $groupId, string $title, string $body, array $data = []): array
    {
        $group = Group::find($groupId);
        if (!$group) {
            return ['success' => false, 'error' => 'Group not found'];
        }

        // Get all groups in subtree
        $groupIds = collect([$groupId])->merge($group->descendants()->pluck('id'));

        // Get leaders for these groups via leader_roles
        $leaderIds = \App\Models\LeaderRole::where('is_active', true)
            ->whereIn('group_id', $groupIds)
            ->pluck('leader_id')
            ->unique()
            ->toArray();

        // Also include direct group leaders
        $directLeaderIds = Group::whereIn('id', $groupIds)
            ->whereNotNull('leader_id')
            ->pluck('leader_id')
            ->toArray();

        $allLeaderIds = array_unique(array_merge($leaderIds, $directLeaderIds));

        $tokens = PushToken::whereIn('leader_id', $allLeaderIds)
            ->where('is_active', true)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            return ['success' => false, 'error' => 'No push tokens found for group leaders'];
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    public function sendToRoleHolders(string $roleSlug, string $title, string $body, array $data = []): array
    {
        $leaderIds = \App\Models\LeaderRole::where('is_active', true)
            ->whereHas('roleDefinition', fn ($q) => $q->where('slug', $roleSlug))
            ->pluck('leader_id')
            ->unique()
            ->toArray();

        $tokens = PushToken::whereIn('leader_id', $leaderIds)
            ->where('is_active', true)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            return ['success' => false, 'error' => 'No push tokens found for role holders'];
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    protected function sendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        $messages = collect($tokens)->map(fn ($token) => [
            'to' => $token,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'sound' => 'default',
        ])->toArray();

        try {
            $response = Http::post($this->expoUrl, $messages);

            if ($response->successful()) {
                Log::info('Push notifications sent', ['count' => count($tokens)]);
                return ['success' => true, 'sent' => count($tokens)];
            }

            Log::error('Push notification failed', ['response' => $response->body()]);
            return ['success' => false, 'error' => $response->body()];
        } catch (\Exception $e) {
            Log::error('Push notification exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
