<?php

namespace Tests\Feature;

use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_forgot_password_and_verify_pages(): void
    {
        $this->get(route('password.request'))->assertOk();

        $this->get(route('password.verify.form'))
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('message', 'Please enter your email first.');
    }

    public function test_sending_reset_code_for_registered_email_stores_hashed_code_and_sends_mail(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'farmer@example.test']);

        $response = $this->from(route('password.request'))->post(route('password.send'), [
            'email' => 'farmer@example.test',
        ]);

        $response->assertRedirect(route('password.verify.form'));
        $response->assertSessionHas('status');
        $response->assertSessionHas('password_reset_email', 'farmer@example.test');

        $this->get(route('password.verify.form'))
            ->assertOk()
            ->assertSee('farmer@example.test', false);
        Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        $user->refresh();
        $this->assertNotNull($user->password_reset_code);
        $this->assertNotNull($user->password_reset_expires_at);
    }

    public function test_sending_reset_code_for_unknown_email_does_not_send_mail(): void
    {
        Mail::fake();

        $response = $this->post(route('password.send'), [
            'email' => 'nobody@example.test',
        ]);

        $response->assertRedirect(route('password.verify.form'));
        $response->assertSessionHas('password_reset_email', 'nobody@example.test');
        Mail::assertNothingSent();

        $this->get(route('password.verify.form'))
            ->assertOk()
            ->assertSee('nobody@example.test', false);
    }

    public function test_invalid_verification_code_shows_error(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'u@example.test']);
        $this->post(route('password.send'), ['email' => $user->email]);

        $user->refresh();
        $this->assertNotNull($user->password_reset_code);

        $response = $this->from(route('password.verify.form'))->post(route('password.verify'), [
            'email' => $user->email,
            'code' => '000000',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_expired_code_is_rejected(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'exp@example.test']);
        $this->post(route('password.send'), ['email' => $user->email]);
        $user->refresh();

        $plain = '123456';
        User::query()->whereKey($user->id)->update([
            'password_reset_code' => Hash::make($plain),
            'password_reset_expires_at' => now()->subMinute(),
        ]);

        $this->from(route('password.verify.form'))->post(route('password.verify'), [
            'email' => $user->email,
            'code' => $plain,
        ])->assertSessionHasErrors('code');
    }

    public function test_successful_verify_and_reset_updates_password_and_allows_login(): void
    {
        Mail::fake();
        $user = User::factory()->create([
            'email' => 'reset@example.test',
            'password' => 'Old-Password-1',
        ]);

        $this->post(route('password.send'), ['email' => $user->email]);
        $user->refresh();

        $sent = Mail::sent(PasswordResetCodeMail::class);
        $this->assertCount(1, $sent);
        $mailable = $sent->first();
        $this->assertInstanceOf(PasswordResetCodeMail::class, $mailable);
        $code = $mailable->code;
        $this->assertSame(6, strlen($code));

        $this->post(route('password.verify'), [
            'email' => $user->email,
            'code' => $code,
        ])->assertRedirect(route('password.reset.form'));

        $this->get(route('password.reset.form'))->assertOk();

        $this->post(route('password.reset'), [
            'password' => 'New-Password-2',
            'password_confirmation' => 'New-Password-2',
        ])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('New-Password-2', $user->password));
        $this->assertNull($user->password_reset_code);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'Old-Password-1',
        ])->assertSessionHasErrors();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'New-Password-2',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_reset_password_form_requires_verified_session(): void
    {
        $this->get(route('password.reset.form'))
            ->assertRedirect(route('password.verify.form'));

        $this->get(route('password.verify.form'))
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('message', 'Please enter your email first.');
    }

    public function test_new_reset_request_replaces_previous_code(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'twice@example.test']);

        $this->post(route('password.send'), ['email' => $user->email]);
        $firstSent = Mail::sent(PasswordResetCodeMail::class);
        $this->assertCount(1, $firstSent);
        $firstMailable = $firstSent->first();
        $this->assertInstanceOf(PasswordResetCodeMail::class, $firstMailable);
        $firstCode = $firstMailable->code;

        Mail::fake();
        $this->post(route('password.send'), ['email' => $user->email]);
        $secondSent = Mail::sent(PasswordResetCodeMail::class);
        $this->assertCount(1, $secondSent);
        $secondMailable = $secondSent->first();
        $this->assertInstanceOf(PasswordResetCodeMail::class, $secondMailable);
        $secondCode = $secondMailable->code;

        $this->assertNotSame($firstCode, $secondCode);

        $this->from(route('password.verify.form'))->post(route('password.verify'), [
            'email' => $user->email,
            'code' => $firstCode,
        ])->assertSessionHasErrors('code');

        $this->post(route('password.verify'), [
            'email' => $user->email,
            'code' => $secondCode,
        ])->assertRedirect(route('password.reset.form'));
    }
}
