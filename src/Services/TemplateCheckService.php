<?php

namespace Shopmonkeynl\ShopmonkeyCli\Services;

use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;
use Twig\Source;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;
use Shopmonkeynl\ShopmonkeyCli\Services\Twig\LightspeedExtension;

/**
 * Static checks for .rain templates, run before anything is uploaded.
 *
 * The templates are Twig with Lightspeed's own filters and functions on top.
 * We only parse them (never render), and accept every filter/function name so
 * that only real syntax problems are reported.
 */
class TemplateCheckService
{

    const ERROR   = 'error';
    const WARNING = 'warning';

    private $themeDirectory;
    private $sizeService;

    public function __construct($themeDirectory = null) {

        $this->themeDirectory = $themeDirectory ?: getcwd() . '/theme';
        $this->sizeService    = new FileSizeService();

    }

    /**
     * A parse-only Twig that accepts every filter, function and test name.
     * Tests can't be added once Twig has parsed something, so each template
     * gets a fresh environment that already knows the tests it uses.
     */
    private function twigFor($content) {

        $twig = new Environment(new ArrayLoader([]), ['autoescape' => false, 'cache' => false]);
        $twig->addExtension(new LightspeedExtension());
        $twig->registerUndefinedFilterCallback(function ($name) {
            return new TwigFilter($name, function ($value) {
                return $value;
            });
        });
        $twig->registerUndefinedFunctionCallback(function ($name) {
            return new TwigFunction($name, function () {
                return null;
            });
        });

        // Lightspeed has its own tests ("is active") and older names ("divisibleby").
        if (preg_match_all('/\bis\s+(?:not\s+)?([a-z_][a-z0-9_]*)/i', $content, $matches)) {
            foreach (array_unique($matches[1]) as $name) {
                $twig->addTest(new TwigTest($name, function () {
                    return true;
                }));
            }
        }

        return $twig;

    }

    /**
     * Older Twig allowed {% for x in y if condition %}. The condition doesn't
     * matter for a syntax check, so drop it (keeping the line count intact).
     */
    private function stripForConditions($content) {

        return preg_replace_callback(
            '/\{%(-?)(\s*for\b)((?:(?!%\}).)*?)\s+if\b((?:(?!%\}).)*?)(-?)%\}/s',
            function ($m) {
                return '{%' . $m[1] . $m[2] . $m[3] . str_repeat("\n", substr_count($m[4], "\n")) . ' ' . $m[5] . '%}';
            },
            $content
        );

    }

    /**
     * Whether a theme file is something we can check.
     */
    public function supports($key) {

        return substr($key, -5) === '.rain';

    }

    /**
     * Check a file on disk. $key is the theme key, e.g. "snippets/header.rain".
     *
     * @return array<int, array{level: string, message: string, line: ?int}>
     */
    public function checkFile($path, $key) {

        $content = @file_get_contents($path);
        if ($content === false) {
            return [$this->issue(self::ERROR, 'Could not read the file.')];
        }

        return $this->checkContent($key, $content);

    }

    /**
     * @return array<int, array{level: string, message: string, line: ?int}>
     */
    public function checkContent($key, $content) {

        if (!$this->supports($key)) {
            return [];
        }

        return array_merge(
            $this->checkSize($key, $content),
            $this->checkSyntax($key, $content),
            $this->checkIncludes($content),
            $this->checkHtmlBalance($content)
        );

    }

    public function hasErrors(array $issues) {

        foreach ($issues as $issue) {
            if ($issue['level'] === self::ERROR) {
                return true;
            }
        }

        return false;

    }

    private function checkSize($key, $content) {

        $bytes  = strlen($content);
        $status = $this->sizeService->status($bytes);

        if ($status === FileSizeService::TRUNCATED) {
            return [$this->issue(self::ERROR, 'Exactly 64 KB: this looks like a file that Lightspeed cut off during a pull.')];
        }
        if ($status === FileSizeService::OVER) {
            return [$this->issue(self::ERROR, sprintf('%s is over the 64 KB template limit. Split it into snippets.', $this->sizeService->format($bytes)))];
        }
        if ($status === FileSizeService::NEAR) {
            return [$this->issue(self::WARNING, sprintf('%s is close to the 64 KB template limit.', $this->sizeService->format($bytes)))];
        }

        return [];

    }

    private function checkSyntax($key, $content) {

        try {
            $twig = $this->twigFor($content);
            $twig->parse($twig->tokenize(new Source($this->stripForConditions($content), $key)));
        } catch (SyntaxError $e) {
            return [$this->issue(self::ERROR, $this->cleanMessage($e->getRawMessage()), $e->getTemplateLine() > 0 ? $e->getTemplateLine() : null)];
        }

        return [];

    }

    /**
     * Literal {% include 'snippets/x.rain' %} targets must exist locally.
     */
    private function checkIncludes($content) {

        $issues = [];

        if (!preg_match_all('/\{%-?\s*include\s+([\'"])([^\'"]+)\1(?!\s*~)/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return $issues;
        }

        foreach ($matches[2] as $match) {
            [$target, $offset] = $match;
            if (strpos($target, 'blocks/') === 0) {
                continue; // content blocks come from the Lightspeed admin, not from the theme files
            }
            if (!is_file($this->themeDirectory . '/' . ltrim($target, '/'))) {
                $issues[] = $this->issue(
                    self::WARNING,
                    sprintf("Includes '%s', which does not exist in the local theme (run a pull?).", $target),
                    substr_count(substr($content, 0, $offset), "\n") + 1
                );
            }
        }

        return $issues;

    }

    /**
     * A file that ends inside an unclosed <script> or <style> is how a cut-off
     * file shows. Only the last opening tag counts, so templates that open a
     * <script> in both branches of an if/else are fine.
     */
    private function checkHtmlBalance($content) {

        $issues = [];

        // Twig code and comments are not HTML (they may contain "<script" in strings)
        $html = preg_replace('/\{%.*?%\}|\{\{.*?\}\}|\{#.*?#\}/s', '', $content);

        foreach (['script', 'style'] as $tag) {
            $lastOpen  = $this->lastMatchOffset('/<' . $tag . '\b/i', $html);
            $lastClose = $this->lastMatchOffset('/<\/' . $tag . '\s*>/i', $html);

            if ($lastOpen !== null && ($lastClose === null || $lastOpen > $lastClose)) {
                $issues[] = $this->issue(self::ERROR, sprintf('The file ends inside an unclosed <%s> block (cut off?).', $tag));
            }
        }

        return $issues;

    }

    private function lastMatchOffset($pattern, $subject) {

        if (!preg_match_all($pattern, $subject, $matches, PREG_OFFSET_CAPTURE) || !$matches[0]) {
            return null;
        }

        return end($matches[0])[1];

    }

    private function cleanMessage($message) {

        // Twig appends " in "x.rain" at line 3" itself; we report the line separately.
        return trim(preg_replace('/\s+in\s+"[^"]*"(\s+at line \d+)?\.?$/', '', $message));

    }

    private function issue($level, $message, $line = null) {

        return ['level' => $level, 'message' => $message, 'line' => $line];

    }

}
