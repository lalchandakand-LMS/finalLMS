<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class LandingBladeSyntaxTest extends TestCase
{
    public function test_landing_view_compiles_to_valid_php(): void
    {
        $source = file_get_contents(resource_path('views/landing.blade.php'));
        $compiled = Blade::compileString($source);

        try {
            token_get_all($compiled, TOKEN_PARSE);
        } catch (\ParseError $error) {
            $lines = explode("\n", $compiled);
            $start = max(0, $error->getLine() - 18);
            $end = min(count($lines), $error->getLine() + 5);
            $context = [];

            for ($line = $start; $line < $end; $line++) {
                $context[] = ($line + 1).': '.$lines[$line];
            }

            fwrite(STDERR, implode("\n", $context)."\n");
            throw $error;
        }

        $this->assertTrue(true);
    }
}
