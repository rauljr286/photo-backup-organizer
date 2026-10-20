<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_primary_nav_marks_the_active_item(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('aria-current="page"', false);
    }

    public function test_the_archive_badge_reflects_the_archived_photo_count(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 3) as $i) {
            $this->archivedPhoto($user, "archived-{$i}.jpg");
        }

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('tabnav__badge', false)
            ->assertSee('bottom-nav__badge', false)
            ->assertSee('3</span>', false);
    }

    public function test_private_is_hidden_from_the_desktop_pill_but_kept_in_mobile_more(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/dashboard')->getContent();

        $pill = Str::before(Str::after($html, '<nav class="tabnav'), '</nav>');
        $this->assertStringNotContainsString(route('private'), $pill);

        $more = Str::before(Str::after($html, 'id="bottom-more-panel"'), '</nav>');
        $this->assertStringContainsString(route('private'), $more);
    }

    public function test_the_desktop_pill_nav_lists_the_five_primary_items(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/dashboard')->getContent();
        $pill = Str::before(Str::after($html, '<nav class="tabnav'), '</nav>');

        foreach (['Photos', 'Albums', 'Archive', 'Insights', 'Cleanup'] as $label) {
            $this->assertStringContainsString($label, $pill);
        }
    }

    public function test_the_mobile_more_menu_excludes_profile(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/dashboard')->getContent();
        $more = Str::before(Str::after($html, 'id="bottom-more-panel"'), '</nav>');

        $this->assertStringContainsString('Sign out', $more);
        $this->assertStringNotContainsString('Profile &amp; settings', $more);
    }

    private function archivedPhoto(User $user, string $name): Photo
    {
        return Photo::create([
            'user_id' => $user->id,
            'file_path' => "photos/{$user->id}/{$name}",
            'original_filename' => $name,
            'size_bytes' => 512,
            'mime_type' => 'image/jpeg',
            'archived_at' => now(),
        ]);
    }
}
