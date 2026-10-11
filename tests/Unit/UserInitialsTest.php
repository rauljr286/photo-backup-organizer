<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserInitialsTest extends TestCase
{
    private function initials(string $name): string
    {
        return (new User(['name' => $name]))->initials();
    }

    public function test_it_uses_the_first_and_last_letters(): void
    {
        $this->assertSame('RC', $this->initials('Raul M. Colot Jr'));
        $this->assertSame('JD', $this->initials('Jane Doe'));
        $this->assertSame('RC', $this->initials('Raul Colot'));
    }

    public function test_it_skips_middle_initials_and_extensions(): void
    {
        $this->assertSame('RC', $this->initials('Raul M. Colot Jr.'));
        $this->assertSame('RC', $this->initials('Raul M Colot III'));
        $this->assertSame('MS', $this->initials('Maria Jose Santos Sr.'));
    }

    public function test_it_handles_compound_given_and_family_names(): void
    {
        $this->assertSame('JC', $this->initials('Juan Dela Cruz'));
        $this->assertSame('JC', $this->initials('Juan Dela Cruz Jr.'));
    }

    public function test_it_falls_back_to_a_single_initial(): void
    {
        $this->assertSame('R', $this->initials('Raul'));
    }

    public function test_it_returns_an_empty_string_for_a_blank_name(): void
    {
        $this->assertSame('', $this->initials(''));
        $this->assertSame('', $this->initials('   '));
    }
}
