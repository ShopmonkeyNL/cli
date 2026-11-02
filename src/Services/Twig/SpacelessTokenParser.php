<?php

namespace Shopmonkeynl\ShopmonkeyCli\Services\Twig;

use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * {% spaceless %}...{% endspaceless %}, removed in Twig 3 but still used by
 * Lightspeed themes. Parse-only: it just has to be accepted and closed.
 */
class SpacelessTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): Node
    {
        $stream = $this->parser->getStream();
        $stream->expect(Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse([$this, 'decideEnd'], true);
        $stream->expect(Token::BLOCK_END_TYPE);

        return new Node([$body], [], $token->getLine());
    }

    public function decideEnd(Token $token): bool
    {
        return $token->test('endspaceless');
    }

    public function getTag(): string
    {
        return 'spaceless';
    }
}
