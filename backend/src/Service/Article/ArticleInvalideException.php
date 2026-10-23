<?php

namespace App\Service\Article;

final class ArticleInvalideException extends \InvalidArgumentException
{
    /**
     * @param array<string, string> $messages un message par champ du formulaire
     */
    public function __construct(public readonly array $messages)
    {
        parent::__construct('Article invalide.');
    }
}
