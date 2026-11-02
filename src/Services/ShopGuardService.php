<?php

namespace Shopmonkeynl\ShopmonkeyCli\Services;

/**
 * Protects against syncing with the wrong shop: remembers which shop/theme a
 * folder was last synced with and asks before that changes.
 */
class ShopGuardService
{

    private $metadata;

    public function __construct(?FileMetadataService $metadata = null) {
        $this->metadata = $metadata ?: new FileMetadataService();
    }

    /**
     * Always show which shop and theme a command is about to touch.
     */
    public function announce($io, $settings) {

        $io->info(sprintf(' 🏬  Shop: %s  |  Theme: %s', $settings['shop_url'], $settings['theme_id']));

    }

    /**
     * Compare the configured shop with the one this folder was last synced
     * with. Returns true when it is fine to continue.
     */
    public function verify($io, $settings) {

        $origin = $this->metadata->getOrigin();

        if ($origin === null || !$this->differs($origin, $settings)) {
            return true;
        }

        $io->wrong(sprintf(
            'This folder was last synced with %s (theme %s), but config.json points to %s (theme %s).',
            $origin['shop_url'],
            $origin['theme_id'],
            $settings['shop_url'],
            $settings['theme_id']
        ));

        return $io->confirm('Continue with the new shop? Local files may be overwritten or removed.', false);

    }

    /**
     * Ask before credentials for a different shop replace the current ones.
     */
    public function confirmSwitch($io, $from, $to) {

        $io->wrong(sprintf(
            'You are about to switch from %s (theme %s) to %s (theme %s).',
            $from['shop_url'],
            $from['theme_id'],
            $to['shop_url'],
            $to['theme_id']
        ));

        return $io->confirm('Switch to the new shop?', false);

    }

    /**
     * Remember the shop and theme this folder is now synced with.
     */
    public function remember($settings) {

        $this->metadata->setOrigin($settings['shop_url'], $settings['theme_id']);

    }

    public function differs($a, $b) {

        return $this->normalise($a['shop_url']) !== $this->normalise($b['shop_url'])
            || (string) $a['theme_id'] !== (string) $b['theme_id'];

    }

    private function normalise($url) {

        return strtolower(rtrim((string) $url, '/'));

    }

}
