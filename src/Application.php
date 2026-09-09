<?php

namespace Shopmonkeynl\ShopmonkeyCli;

use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    /**
     * The current CLI version. Kept in sync with the git tag that is released.
     *
     * @var string
     */
    public const VERSION = '3.3.0';

    /**
     * The Composer package name, used by the "update" command.
     *
     * @var string
     */
    public const PACKAGE = 'shopmonkeynl/shopmonkey-cli';

    public function __construct()
    {
        parent::__construct('Shopmonkey CLI', self::VERSION);
    }
}
