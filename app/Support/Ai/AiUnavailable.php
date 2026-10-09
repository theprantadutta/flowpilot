<?php

namespace App\Support\Ai;

use RuntimeException;

/**
 * The provider could not give an answer. The message is safe to store and
 * show: it never contains the API key or the prompt.
 */
class AiUnavailable extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('No AI provider is set up.');
    }

    public static function unreachable(): self
    {
        return new self('The AI provider could not be reached.');
    }

    public static function timedOut(): self
    {
        return new self('The AI provider took too long to answer.');
    }

    public static function rateLimited(): self
    {
        return new self('The AI provider is busy. Try again in a few minutes.');
    }

    public static function rejected(int $status): self
    {
        return new self("The AI provider refused the request ({$status}).");
    }

    public static function emptyAnswer(): self
    {
        return new self('The AI provider returned an empty answer.');
    }
}
