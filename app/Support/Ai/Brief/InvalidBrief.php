<?php

namespace App\Support\Ai\Brief;

use RuntimeException;

/**
 * The model answered, but not with a brief FlowPilot can trust.
 */
class InvalidBrief extends RuntimeException {}
