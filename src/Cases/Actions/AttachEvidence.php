<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Cases\Events\CaseEvidenceAttached;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Cases\Models\Evidence;
use Syriable\Casework\Exceptions\IncompleteBuilder;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Concerns\AuthorizesActions;
use Syriable\Casework\Support\ModelRegistry;

/**
 * Attach an immutable evidence record referencing a model and/or
 * structured data. No file storage.
 */
class AttachEvidence
{
    use AuthorizesActions;

    public function __construct(
        private readonly Recorder $recorder,
    ) {}

    /**
     * @param  array<string, mixed>|null  $data
     */
    public function execute(CaseFile $case, ActorRef $by, ?Model $subject = null, ?array $data = null): Evidence
    {
        $this->authorizeInScope($by, 'attachEvidence', $case, $case->subject()->first());

        if ($subject === null && $data === null) {
            throw IncompleteBuilder::missing(self::class, 'a referenced model or structured data');
        }

        return DB::transaction(function () use ($case, $by, $subject, $data): Evidence {
            $class = ModelRegistry::classFor('evidence');

            /** @var Evidence $evidence */
            $evidence = new $class([
                'case_id' => $case->getKey(),
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'data' => $data,
                'author_type' => $by->actor?->getMorphClass(),
                'author_id' => $by->actor?->getKey(),
                'origin' => $by->origin,
            ]);

            $evidence->save();

            $this->recorder->record($by, 'case.evidence_attached', $case, ['evidence_id' => $evidence->getKey()]);

            event(new CaseEvidenceAttached($evidence, $by));

            return $evidence;
        });
    }
}
