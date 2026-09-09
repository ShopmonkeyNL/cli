<?php

namespace Shopmonkeynl\ShopmonkeyCli\Services;

use Symfony\Component\Console\Style\SymfonyStyle;

class InputOutput extends SymfonyStyle
{
    /**
     * Ask a question and return the answer.
     */
    public function question(string $question, ?string $default = null): string
    {
        return (string) $this->ask(sprintf(' ✍️  %s', $question), $default);
    }

    /**
     * Display a message in case of right answer.
     */
    public function right(string $message): void
    {
        $this->block(sprintf(' 🎉  %s', $message), null, 'fg=white;bg=green', ' ', true);
    }

    /**
     * Display a message in case of wrong answer.
     */
    public function wrong(string $message): void
    {
        $this->block(sprintf(' 😮  %s', $message), null, 'fg=white;bg=red', ' ', true);
    }

    public function info($message): void
    {
        $this->block($message, null, 'fg=white;bg=blue', ' ', true);
    }
}