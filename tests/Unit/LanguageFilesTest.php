<?php

namespace Tests\Unit;

use Illuminate\Support\Arr;
use PHPUnit\Framework\TestCase;

/**
 * Every text is in English and Arabic (the site, the admin, the app's API): each lang/en file has
 * an Arabic twin with the same keys, and every file loads (a stray apostrophe breaks a whole file).
 */
class LanguageFilesTest extends TestCase
{
    public function test_english_and_arabic_have_the_same_texts(): void
    {
        $root = dirname(__DIR__, 2).'/lang';
        $missing = [];

        foreach (glob("{$root}/en/*.php") as $file) {
            $name = basename($file);
            $en = Arr::dot(require $file);
            $arFile = "{$root}/ar/{$name}";

            if (! is_file($arFile)) {
                $missing[] = "ar/{$name}: missing file";

                continue;
            }

            $ar = Arr::dot(require $arFile);

            foreach (array_diff(array_keys($en), array_keys($ar)) as $key) {
                $missing[] = "ar/{$name}: {$key}";
            }

            foreach (array_diff(array_keys($ar), array_keys($en)) as $key) {
                $missing[] = "en/{$name}: {$key}";
            }
        }

        $this->assertSame([], $missing, count($missing)." text(s) in one language only:\n".implode("\n", array_slice($missing, 0, 80)));
    }
}
