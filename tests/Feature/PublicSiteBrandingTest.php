<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The public site (home, /support, /privacy) is branded Flock. The old
 * "Church Stack" name must not appear in any visible text. The church-stack.com
 * domain and email addresses are deliberately still in use until the domain
 * moves, so only the brand name is checked here.
 */
class PublicSiteBrandingTest extends TestCase
{
    public static function pages(): array
    {
        return [['/'], ['/support'], ['/privacy']];
    }

    /** @dataProvider pages */
    public function test_page_is_branded_flock_not_church_stack(string $path): void
    {
        $html = $this->get('http://church-stack.com'.$path)->assertOk()->getContent();

        $this->assertStringNotContainsStringIgnoringCase('Church Stack', $html);
        $this->assertStringNotContainsStringIgnoringCase('ChurchStack', $html);
        $this->assertStringContainsString('Flock. All rights reserved.', $html);
    }

    public function test_support_and_privacy_name_flock(): void
    {
        $this->get('http://church-stack.com/support')
            ->assertSee('get the most out of Flock')
            ->assertSee('New to Flock?')
            ->assertSee('Flock supports multi-tenancy');

        $this->get('http://church-stack.com/privacy')
            ->assertSee('Flock ("we"', false)
            ->assertSee('Each church on Flock');
    }
}
