<?php

namespace Shopmonkeynl\ShopmonkeyCli\Services\Twig;

use Twig\Extension\AbstractExtension;

/**
 * The bits of Lightspeed's (older) Twig that Twig 3 no longer knows.
 */
class LightspeedExtension extends AbstractExtension
{
    public function getTokenParsers(): array
    {
        return [new SpacelessTokenParser(), new GlobalTokenParser()];
    }
}
