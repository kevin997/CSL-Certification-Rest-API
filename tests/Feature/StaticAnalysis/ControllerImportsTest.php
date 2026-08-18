<?php

namespace Tests\Feature\StaticAnalysis;

use Tests\TestCase;

/**
 * A controller that writes `Response::HTTP_*` without importing a Response
 * class resolves it against its own namespace and fatals at runtime -- but
 * only on whichever branch happens to use it. That is how a graceful 422
 * became a production 500 on the checkout path.
 */
class ControllerImportsTest extends TestCase
{
    public function test_every_controller_that_uses_response_constants_imports_response(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Http/Controllers'))
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            $usesConstant = preg_match('/(?<![\w\\\\$>])Response::HTTP_/', $source);
            if (! $usesConstant) {
                continue;
            }

            $imports = preg_match('/^use\s+[^;]*\\\\Response;/m', $source)
                || preg_match('/^use\s+[^;]*\s+as\s+Response;/m', $source);

            if (! $imports) {
                $offenders[] = str_replace(app_path().'/', '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, "These controllers use Response::HTTP_* without importing a Response class:\n".implode("\n", $offenders));
    }
}
