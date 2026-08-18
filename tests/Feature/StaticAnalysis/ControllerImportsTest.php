<?php

namespace Tests\Feature\StaticAnalysis;

use Tests\TestCase;

/**
 * A controller that writes `Response::HTTP_*` without importing a Response
 * class resolves it against its own namespace — e.g.
 * App\Http\Controllers\Api\Response — and fatals at runtime.
 *
 * It fatals only on whichever branch happens to use the constant, so the file
 * looks fine until a rare path executes. That is exactly how the storefront
 * checkout's graceful 422 became a production 500: the error branch itself
 * threw, and because it is an Error rather than an Exception, the
 * catch (\Exception) around it did not catch it.
 */
class ControllerImportsTest extends TestCase
{
    public function test_every_controller_using_response_constants_imports_a_response_class(): void
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

            // `Response::HTTP_` not preceded by a name character, \, $ or ->,
            // so `JsonResponse::`, `$response::` and `Foo\Response::` do not match.
            if (! preg_match('/(?<![\w\\\\$>])Response::HTTP_/', $source)) {
                continue;
            }

            $importsResponse = preg_match('/^use\s+[^;]*\\\\Response;/m', $source)
                || preg_match('/^use\s+[^;]*\s+as\s+Response;/m', $source);

            if (! $importsResponse) {
                $offenders[] = str_replace(app_path().'/', '', $file->getPathname());
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "These controllers use Response::HTTP_* without importing a Response class:\n".implode("\n", $offenders)
        );
    }
}
