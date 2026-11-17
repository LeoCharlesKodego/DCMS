<?php

namespace Tests\Feature;

use Tests\TestCase;

class SharedFrontendAssetsTest extends TestCase
{
    public function test_shared_frontend_assets_use_explicit_content_types(): void
    {
        config(['app.env' => 'ngrok']);

        $expected = ['.css' => 'text/css', '.js' => 'text/javascript', '.svg' => 'image/svg+xml'];

        $checked = 0;
        foreach ($expected as $suffix => $mime) {
            $files = glob(base_path('../frontend/dist/assets/*'.$suffix));
            if (! $files) continue;
            $checked++;

            $response = $this->get('/assets/'.basename($files[0]));

            $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertSame($mime, strtok((string) $response->headers->get('Content-Type'), ';'));
        }

        $this->assertGreaterThanOrEqual(2, $checked, 'Expected at least CSS and JS assets in the production build.');
    }
}
