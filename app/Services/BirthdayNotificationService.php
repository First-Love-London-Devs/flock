<?php

namespace App\Services;

use App\Models\BirthdayNotification;
use App\Models\Group;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BirthdayNotificationService
{
    protected PushNotificationService $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }

    public function processBirthdayNotifications(): array
    {
        $results = ['today' => 0, 'tomorrow' => 0, 'one_week' => 0];

        $results['today'] = $this->processBirthdaysForDate(Carbon::today(), 'today');
        $results['tomorrow'] = $this->processBirthdaysForDate(Carbon::tomorrow(), 'tomorrow');
        $results['one_week'] = $this->processBirthdaysForDate(Carbon::today()->addWeek(), 'one_week');

        return $results;
    }

    protected function processBirthdaysForDate(Carbon $date, string $type): int
    {
        $members = Member::active()
            ->whereNotNull('date_of_birth')
            ->whereMonth('date_of_birth', $date->month)
            ->whereDay('date_of_birth', $date->day)
            ->get();

        $sent = 0;

        foreach ($members as $member) {
            // Find leaders responsible for this member (via group assignments)
            $groupIds = $member->groups()->pluck('groups.id');
            $leaderIds = Group::whereIn('id', $groupIds)
                ->whereNotNull('leader_id')
                ->pluck('leader_id')
                ->unique();

            foreach ($leaderIds as $leaderId) {
                if (BirthdayNotification::hasBeenSent($member->id, $leaderId, $date->toDateString(), $type)) {
                    continue;
                }

                $notification = BirthdayNotification::create([
                    'member_id' => $member->id,
                    'leader_id' => $leaderId,
                    'notification_date' => $date->toDateString(),
                    'notification_type' => $type,
                    'status' => 'pending',
                ]);

                $title = match ($type) {
                    'today' => "Birthday Today!",
                    'tomorrow' => "Birthday Tomorrow!",
                    'one_week' => "Birthday Next Week!",
                };

                $body = "{$member->full_name}'s birthday is " . match ($type) {
                    'today' => "today! Don't forget to wish them well.",
                    'tomorrow' => "tomorrow. Plan a surprise!",
                    'one_week' => "in one week ({$date->format('M d')}). Time to prepare!",
                };

                $result = $this->pushService->sendToLeader($leaderId, $title, $body, [
                    'type' => 'birthday_reminder',
                    'member_id' => $member->id,
                ]);

                if ($result['success']) {
                    $notification->markAsSent();
                    $sent++;
                } else {
                    $notification->markAsFailed();
                }
            }
        }

        Log::info("Birthday notifications processed", ['type' => $type, 'sent' => $sent]);
        return $sent;
    }

    public function getUpcomingBirthdays(int $leaderId, int $days = 7): array
    {
        $leader = \App\Models\Leader::find($leaderId);
        if (!$leader) return [];

        // Get groups this leader manages
        $groupIds = Group::where('leader_id', $leaderId)->pluck('id');
        $roleGroupIds = $leader->leaderRoles()->where('is_active', true)->pluck('group_id')->filter();
        $allGroupIds = $groupIds->merge($roleGroupIds)->unique();

        // Get members in those groups
        $memberIds = \Illuminate\Support\Facades\DB::table('group_member')
            ->whereIn('group_id', $allGroupIds)
            ->pluck('member_id');

        $today = Carbon::today();
        $endDate = Carbon::today()->addDays($days);

        return Member::active()
            ->whereIn('id', $memberIds)
            ->whereNotNull('date_of_birth')
            ->get()
            ->filter(function ($member) use ($today, $endDate) {
                $birthday = $member->date_of_birth->copy()->year($today->year);
                if ($birthday->lt($today)) {
                    $birthday->addYear();
                }
                return $birthday->between($today, $endDate);
            })
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->full_name,
                'date_of_birth' => $m->date_of_birth->format('M d'),
                'days_until' => (int) Carbon::today()->diffInDays(
                    $m->date_of_birth->copy()->year(Carbon::today()->year)->lt(Carbon::today())
                        ? $m->date_of_birth->copy()->year(Carbon::today()->year + 1)
                        : $m->date_of_birth->copy()->year(Carbon::today()->year)
                ),
            ])
            ->sortBy('days_until')
            ->values()
            ->toArray();
    }
}
