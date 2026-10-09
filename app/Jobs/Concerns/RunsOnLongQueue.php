<?php

namespace App\Jobs\Concerns;

/**
 * For jobs that may run for minutes (exports, AI calls): send them to the
 * "long" queue on the matching "-long" connection, whose retry window is
 * longer than any such job's timeout. Connections without a long twin (sync
 * in tests, for example) run the job where it is.
 */
trait RunsOnLongQueue
{
    protected function onLongQueue(): void
    {
        $default = (string) config('queue.default');

        if (array_key_exists("{$default}-long", (array) config('queue.connections'))) {
            $this->onConnection("{$default}-long");
        }

        $this->onQueue('long');
    }
}
