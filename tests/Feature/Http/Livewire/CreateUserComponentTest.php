<?php

namespace Tests\Feature\Http\Livewire;

use App\Http\Livewire\CreateUserComponent;
use App\Http\Livewire\LivewireAuth;
use App\Mail\InvitationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** @see \App\Http\Livewire\CreateUserComponent */
class CreateUserComponentTest extends TestCase
{
    use RefreshDatabase;

    /** @var \App\Models\User */
    protected $admin;

    public function setUp() : void
    {
        parent::setUp();

        $this->admin = create_admin();

        Mail::fake();
    }

    /** @test */
    public function assert_create_user_component_uses_livewire_auth_trait()
    {
        $this->assertContains(LivewireAuth::class, class_uses(CreateUserComponent::class));
    }

    /** @test */
    public function render()
    {
        Livewire::actingAs($this->admin)
            ->test(CreateUserComponent::class)
            ->assertSee('Save')
            ->assertStatus(Response::HTTP_OK);
    }

    /** @test */
    public function store_new_user()
    {
        Livewire::actingAs($this->admin)
            ->test(CreateUserComponent::class)
            ->set('roleId', $this->admin->role->id)
            ->set('email', 'joe@example.com')
            ->call('store')
            ->assertRedirect('users');

        $this->assertTrue(session()->has('flash'));

        $users = User::where('email', 'joe@example.com')
            ->where('role_id', $this->admin->role->id)
            ->whereNull('password')
            ->get();

        $this->assertCount(1, $users);
    }

    /** @test */
    public function invitation_email_is_sent_to_a_newly_create_user()
    {
        Livewire::actingAs($this->admin)
            ->test(CreateUserComponent::class)
            ->set('email', 'joe@example.com')
            ->set('roleId', $this->admin->role->id)
            ->call('store')
            ->assertRedirect('users');

        Mail::assertQueued(InvitationMail::class, 1);
    }

    /**
     * @test
     * @dataProvider clientFormValidationProvider
     */
    public function test_store_validation_rules($clientFormInput, $clientFormValue, $rule)
    {
        Livewire::actingAs($this->admin)
            ->test(CreateUserComponent::class)
            ->set($clientFormInput, $clientFormValue)
            ->call('store')
            ->assertHasErrors([$clientFormInput => $rule]);
    }

    public function clientFormValidationProvider()
    {
        return [
            'Test email is required' => ['email', '', 'required'],
            'Test email is valid' => ['email', 'not-an-email', 'email'],
            'Test email must be unique' => ['email', 'admin@admin.lte', 'unique'],
            'Test roleId is required' => ['roleId', '', 'required'],
            'Test roleId must exist' => ['roleId', 'invalid-role-id', 'exists'],
        ];
    }
}
