<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactSupportComponentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test contact support component renders correctly with SUPPORT_EMAIL and mailto link.
     */
    public function test_contact_support_component_renders_support_email_and_mailto_link(): void
    {
        $supportEmail = config('mail.support_email', env('SUPPORT_EMAIL', 'raishivamrai837@gmail.com'));

        $view = $this->blade('<x-contact-support />');

        $view->assertSee('Need help?');
        $view->assertSee($supportEmail);
        $view->assertSee('mailto:' . $supportEmail, false);
        $view->assertSee('Your email will automatically create a support ticket, and our AI or support team will respond as soon as possible.');
    }

    /**
     * Test welcome page contains contact support section.
     */
    public function test_welcome_page_contains_contact_support_section(): void
    {
        $supportEmail = config('mail.support_email', env('SUPPORT_EMAIL', 'raishivamrai837@gmail.com'));

        $response = $this->followingRedirects()->get('/');

        $response->assertStatus(200);
        $response->assertSee($supportEmail);
        $response->assertSee('mailto:' . $supportEmail, false);
    }

    /**
     * Test login page contains contact support section.
     */
    public function test_login_page_contains_contact_support_section(): void
    {
        $supportEmail = config('mail.support_email', env('SUPPORT_EMAIL', 'raishivamrai837@gmail.com'));

        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee($supportEmail);
        $response->assertSee('mailto:' . $supportEmail, false);
    }
}
