<?php

namespace App\Services;

/**
 * Thrown when Ollama can't produce a usable response after all retries.
 *
 * Lives in its own file so PSR-4 can autoload it by name. While it was
 * declared alongside OllamaService, the class was only ever loaded as a side
 * effect of loading that file — which made `l5-swagger:generate` fail with
 * "Skipping unknown App\Services\OllamaException" when it resolved the
 * @throws annotations, and that failure broke the production image build.
 */
class OllamaException extends \RuntimeException
{
}
