<?php

namespace App\Http\Requests;

use App\Models\Group;
use App\Models\UnderstandingCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class AssignUnderstandingCampaignRequest extends FormRequest
{
    private function scopeGroupIds(): Collection
    {
        $role = $this->user()?->leaderRoles()
            ->where('is_active', true)
            ->whereNotNull('group_id')
            ->whereHas('roleDefinition', fn ($q) => $q->whereRaw('LOWER(slug) = ?', ['understanding-campaign']))
            ->with('group')
            ->first();

        return $role && $role->group ? $role->group->allGroupIds() : collect();
    }

    public function authorize(): bool
    {
        // The record must be inside the rep's own gathering-service subtree.
        $record = UnderstandingCampaign::find($this->route('id'));

        return $record !== null && $this->scopeGroupIds()->contains($record->stream_id);
    }

    public function rules(): array
    {
        $scopeIds = $this->scopeGroupIds()->all();

        return [
            'allocated_group_id' => [
                'present',
                'nullable',
                'integer',
                // Target must be a group inside the subtree ...
                Rule::exists('groups', 'id')->where(fn ($q) => $q->whereIn('id', $scopeIds ?: [0])),
                // ... and it must be a bacenta (tracks_attendance).
                function ($attribute, $value, $fail) {
                    if ($value === null) {
                        return;
                    }
                    $group = Group::with('groupType')->find($value);
                    if (! $group || ! optional($group->groupType)->tracks_attendance) {
                        $fail('The selected group is not a bacenta.');
                    }
                },
            ],
        ];
    }
}
