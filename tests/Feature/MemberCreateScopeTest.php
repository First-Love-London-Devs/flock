<?php

namespace Tests\Feature;

use App\Filament\Resources\MemberResource;
use App\Filament\Resources\MemberResource\Pages\CreateMember;
use App\Filament\Resources\MemberResource\Pages\EditMember;
use App\Models\Group;
use App\Models\GroupType;
use App\Models\Member;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Adding a member by hand, as a country admin.
 *
 * Switzerland reported "it says error" when adding members manually. The
 * member was in fact being saved every time. A country admin only sees members
 * through the groups they belong to, a brand-new member belongs to none, so
 * Filament saved the record, redirected to the edit page, and that page
 * resolved the record through the scoped query and found nothing.
 *
 * Underneath it sat a second, older bug: the Bacenta picker on the member form
 * is dehydrated and nothing ever synced it, so choosing a bacenta had never
 * done anything on create or on edit.
 */
class MemberCreateScopeTest extends TestCase
{
    private Group $switzerland;

    private Group $bacenta;

    protected function setUp(): void
    {
        parent::setUp();

        $countryType = GroupType::create(['name' => 'Country', 'slug' => 'country', 'level' => 0,
            'tracks_attendance' => false, 'is_active' => true]);
        $bacentaType = GroupType::create(['name' => 'Bacenta', 'slug' => 'bacenta', 'level' => 4,
            'tracks_attendance' => true, 'is_active' => true]);

        $this->switzerland = Group::create(['name' => 'Switzerland', 'group_type_id' => $countryType->id, 'is_active' => true]);
        $this->bacenta = Group::create(['name' => 'Oerlikon', 'group_type_id' => $bacentaType->id,
            'parent_id' => $this->switzerland->id, 'is_active' => true]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function admin(?Group $scope): User
    {
        return User::create([
            'name' => 'Admin', 'email' => strtolower($scope?->name ?? 'wide').'@example.test',
            'password' => 'password', 'scope_group_id' => $scope?->id,
        ]);
    }

    private function create(array $form): Member
    {
        Livewire::test(CreateMember::class)
            ->fillForm($form)
            ->call('create')
            ->assertHasNoFormErrors();

        return Member::where('first_name', $form['first_name'])->firstOrFail();
    }

    /** The reported bug. */
    public function test_a_member_a_country_admin_creates_is_visible_to_them(): void
    {
        $this->actingAs($this->admin($this->switzerland));

        $member = $this->create(['first_name' => 'New', 'last_name' => 'Person']);

        $this->assertNotNull(
            MemberResource::getEloquentQuery()->find($member->id),
            'A member the admin just created must not vanish from their own panel.'
        );
    }

    public function test_choosing_a_bacenta_on_the_form_actually_saves_it(): void
    {
        $this->actingAs($this->admin($this->switzerland));

        $member = $this->create([
            'first_name' => 'Placed', 'last_name' => 'Person',
            'bacenta_groups' => [$this->bacenta->id],
        ]);

        $this->assertTrue(
            $member->groups()->where('groups.id', $this->bacenta->id)->exists(),
            'The Bacenta picker has never persisted anything; it should now.'
        );
    }

    /** No bacenta chosen: they still have to be reachable. */
    public function test_a_member_with_no_bacenta_falls_back_to_the_admins_own_scope(): void
    {
        $this->actingAs($this->admin($this->switzerland));

        $member = $this->create(['first_name' => 'Unplaced', 'last_name' => 'Person']);

        $this->assertTrue($member->groups()->where('groups.id', $this->switzerland->id)->exists());
    }

    public function test_the_group_wide_admin_is_not_given_a_fallback_group(): void
    {
        $this->actingAs($this->admin(null));

        $member = $this->create(['first_name' => 'Wide', 'last_name' => 'Person']);

        $this->assertSame(0, $member->groups()->count(), 'An unscoped admin sees everything and needs no holding group.');
    }

    public function test_editing_a_member_can_change_their_bacenta(): void
    {
        $this->actingAs($this->admin(null));

        $member = Member::factory()->create(['first_name' => 'Movable']);

        Livewire::test(EditMember::class, ['record' => $member->id])
            ->fillForm(['bacenta_groups' => [$this->bacenta->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($member->fresh()->groups()->where('groups.id', $this->bacenta->id)->exists());
    }
}
