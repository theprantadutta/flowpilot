<?php

namespace App\Workflows\Nodes;

use App\Enums\NodeType;
use App\Workflows\Definition\ValidationScope;

/**
 * Runs one type of workflow step and checks its configuration.
 */
interface NodeHandler
{
    public function type(): NodeType;

    /**
     * Problems with a node's configuration, in words for the builder.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public function validate(array $config, ValidationScope $scope): array;

    /**
     * The paths a node of this type can leave through, given its configuration.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public function handles(array $config): array;

    /**
     * Do the step. Throw StepFailed when it cannot be done.
     */
    public function execute(StepContext $step): StepResult;

    /**
     * Whether the step's changes and its completion are saved in one database
     * transaction. False for steps that talk to other systems, which must not
     * hold a transaction open while they wait.
     */
    public function transactional(): bool;
}
