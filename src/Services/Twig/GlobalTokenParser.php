<?php

namespace Shopmonkeynl\ShopmonkeyCli\Services\Twig;

use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Lightspeed's {% global name = value %} tag. Parse-only.
 */
class GlobalTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): Node
    {
        $stream = $this->parser->getStream();
        $stream->expect(Token::NAME_TYPE);

        $nodes = [];
        if ($stream->nextIf(Token::OPERATOR_TYPE, '=')) {
            $nodes[] = method_exists($this->parser, 'parseExpression')
                ? $this->parser->parseExpression()
                : $this->parser->getExpressionParser()->parseExpression();
        }

        $stream->expect(Token::BLOCK_END_TYPE);

        return new Node($nodes, [], $token->getLine());
    }

    public function getTag(): string
    {
        return 'global';
    }
}
