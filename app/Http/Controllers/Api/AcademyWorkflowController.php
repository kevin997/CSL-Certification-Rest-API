<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademyWorkflow;
use App\Models\Environment;
use App\Services\WhatsApp\AcademyWhatsApp;
use App\Services\Workflows\WorkflowDefinition;
use App\Support\Tenancy\EnvironmentContext;
use App\Support\Tenancy\EnvironmentResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademyWorkflowController extends Controller
{
    public function __construct(private readonly WorkflowDefinition $definitions, private readonly AcademyWhatsApp $whatsapp) {}

    public function index(Request $request): JsonResponse
    {
        $academy = $this->academy($request);

        return $this->reply([
            'data' => AcademyWorkflow::where('environment_id', $academy->id)->latest('id')->get()->map->payload(),
            'can_edit' => WorkflowDefinition::canRun($academy),
            'options' => $this->definitions->resources($academy->id),
            'channels' => collect(WorkflowDefinition::CHANNELS)->mapWithKeys(fn ($channel) => [
                $channel => WorkflowDefinition::channelAvailable($channel, $this->whatsapp),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $academy = $this->academy($request, true);
        $definition = $this->definitions->normalize($request->except('environment'), $academy->id);
        $workflow = DB::transaction(function () use ($academy, $definition) {
            Environment::whereKey($academy->id)->lockForUpdate()->firstOrFail();
            if (AcademyWorkflow::where('environment_id', $academy->id)->count() >= 30) {
                throw ValidationException::withMessages(['name' => 'This academy already has 30 workflows.']);
            }

            return AcademyWorkflow::create(['environment_id' => $academy->id, 'draft' => $definition]);
        });

        return $this->reply(['data' => $workflow->refresh()->payload()], 201);
    }

    public function update(Request $request, int $workflow): JsonResponse
    {
        $academy = $this->academy($request, true);
        $definition = $this->definitions->normalize($request->except('environment'), $academy->id);

        return $this->change($academy, $workflow, function (AcademyWorkflow $row) use ($request, $definition): array {
            $this->checkVersion($request, $row);

            return ['draft' => $definition, 'draft_version' => $row->draft_version + 1];
        });
    }

    public function publish(Request $request, int $workflow): JsonResponse
    {
        $academy = $this->academy($request, true);

        return $this->change($academy, $workflow, function (AcademyWorkflow $row) use ($request, $academy): array {
            $this->checkVersion($request, $row);
            $definition = $this->definitions->normalize($row->draft, $academy->id);
            $this->definitions->requirePublishable($definition);
            foreach ($definition['actions'] as $action) {
                if (! WorkflowDefinition::channelAvailable($action['channel'], $this->whatsapp)) {
                    throw ValidationException::withMessages(['actions' => 'An action uses an unavailable channel.']);
                }
            }
            if ($row->active && $row->published === $definition) {
                return [];
            }

            $publishedAt = now();

            return [
                'published' => $definition, 'published_trigger' => $definition['trigger'],
                'revision' => $row->revision + 1, 'active' => true, 'published_at' => $publishedAt,
                'activated_at_us' => (int) $publishedAt->format('Uu'),
            ];
        });
    }

    public function pause(Request $request, int $workflow): JsonResponse
    {
        $academy = $this->academy($request);

        return $this->change($academy, $workflow, fn (): array => ['active' => false]);
    }

    public function runs(Request $request, int $workflow): JsonResponse
    {
        $academy = $this->academy($request);
        $row = AcademyWorkflow::where('environment_id', $academy->id)->findOrFail($workflow);
        $runs = DB::table('academy_workflow_runs')->where('academy_workflow_id', $row->id)->latest('id')
            ->limit(50)->get(['id', 'revision', 'channel', 'state', 'reason', 'created_at', 'updated_at']);

        return $this->reply(['data' => $runs]);
    }

    public function simulate(Request $request): JsonResponse
    {
        $academy = $this->academy($request);
        $definition = $this->definitions->normalize($request->except('environment'), $academy->id);
        $matched = $definition['filter_id'] === null || $request->boolean('sample_matches', true);
        $context = [
            'academy' => 'Example academy', 'name' => 'Example learner', 'event' => $definition['trigger'],
            'reference' => match ($definition['trigger']) {
                'form_submitted' => 'Example form', 'certificate_issued' => 'Example certificate', default => 'ORD-EXAMPLE',
            },
        ];

        return $this->reply(['data' => [
            'simulation' => true,
            'matched' => $matched,
            'actions' => array_map(fn ($action) => [
                'channel' => $action['channel'],
                'recipient' => $action['recipient'],
                'title' => WorkflowDefinition::render($action['title'], $context),
                'message' => WorkflowDefinition::render($action['message'], $context),
                'outcome' => ! $matched ? 'condition_not_met' : (
                    WorkflowDefinition::channelAvailable($action['channel'], $this->whatsapp) ? 'preview_only' : 'channel_unavailable'
                ),
            ], $definition['actions']),
        ]]);
    }

    private function change(Environment $academy, int $id, callable $callback): JsonResponse
    {
        $row = DB::transaction(function () use ($academy, $id, $callback) {
            $row = AcademyWorkflow::where('environment_id', $academy->id)->lockForUpdate()->findOrFail($id);
            $row->update($callback($row));

            return $row;
        });

        return $this->reply(['data' => $row->payload()]);
    }

    private function checkVersion(Request $request, AcademyWorkflow $row): void
    {
        $request->validate(['draft_version' => 'sometimes|integer|min:1', 'environment_id' => 'prohibited']);
        if ($request->has('draft_version') && (int) $request->input('draft_version') !== (int) $row->draft_version) {
            abort(409, 'The draft changed elsewhere; refresh before continuing.');
        }
    }

    private function academy(Request $request, bool $write = false): Environment
    {
        $context = $request->attributes->get(EnvironmentResolver::REQUEST_ATTRIBUTE);
        $academy = $context instanceof EnvironmentContext ? $context->environment : null;
        abort_unless($academy && $request->user()?->isStaffIn($academy->id), 403);
        abort_if($write && ! WorkflowDefinition::canRun($academy), 403, 'Automations are not available with this academy plan.');

        return $academy;
    }

    private function reply(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status, ['Cache-Control' => 'private, no-store']);
    }
}
