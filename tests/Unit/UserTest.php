<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UserTest extends TestCase
{
    /**
     * Test that user name is automatically trimmed and title-cased.
     */
    public function test_user_name_is_automatically_formatted(): void
    {
        $user = new User();

        // shivam -> Shivam
        $user->name = '  shivam  ';
        $this->assertEquals('Shivam', $user->name);

        // agent2 -> Agent2
        $user->name = 'agent2';
        $this->assertEquals('Agent2', $user->name);

        // john doe -> John Doe
        $user->name = ' john doe ';
        $this->assertEquals('John Doe', $user->name);

        // mary jane watson -> Mary Jane Watson
        $user->name = 'mary jane watson';
        $this->assertEquals('Mary Jane Watson', $user->name);
    }
}
