<?php

namespace Tests\Feature;

use App\Filament\RichEditor\TextDirectionPlugin;
use App\Models\Video;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Tests\TestCase;

class VideoRichDescriptionTest extends TestCase
{
    private function video(?string $description): Video
    {
        $video = new Video;
        $video->setTranslation('description', 'en', $description);

        return $video;
    }

    public function test_direction_alignment_and_links_survive_rendering(): void
    {
        $html = $this->video(
            '<p dir="rtl" style="text-align: center">مرحبا</p>'
            .'<p dir="ltr">See <a href="https://example.com/x">the guide</a></p>'
        )->descriptionHtml();

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('text-align: center', $html);
        $this->assertStringContainsString('dir="ltr"', $html);
        $this->assertStringContainsString('href="https://example.com/x"', $html);
    }

    public function test_scripts_and_unknown_directions_are_dropped(): void
    {
        $html = $this->video(
            '<p dir="sideways" onclick="alert(1)">Hi<script>alert(2)</script></p>'
            .'<p><a href="javascript:alert(3)">x</a></p>'
        )->descriptionHtml();

        $this->assertStringNotContainsString('sideways', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_the_editor_keeps_dir_when_turning_html_into_its_document(): void
    {
        $json = RichContentRenderer::make('<h2 dir="rtl">عنوان</h2><ul dir="rtl"><li dir="rtl"><p dir="rtl">بند</p></li></ul>')
            ->plugins([TextDirectionPlugin::make()])
            ->toArray();

        $this->assertSame('rtl', $json['content'][0]['attrs']['dir']);
        $this->assertSame('rtl', $json['content'][1]['attrs']['dir']);
        $this->assertSame('rtl', $json['content'][1]['content'][0]['content'][0]['attrs']['dir']);
    }

    public function test_plain_text_for_cards_and_seo(): void
    {
        $video = $this->video('<p>First &amp; line<br>second</p><p>Third</p>');

        $this->assertSame('First & line second Third', $video->descriptionText());
        $this->assertNull($this->video('<p></p>')->descriptionText());
        $this->assertNull($this->video('<p></p>')->descriptionHtml());
        $this->assertNull($this->video(null)->descriptionHtml());
    }

    public function test_the_plugin_offers_both_direction_buttons(): void
    {
        $tools = collect(TextDirectionPlugin::make()->getEditorTools())->map->getName();

        $this->assertSame(['textDirectionLtr', 'textDirectionRtl'], $tools->all());
        $this->assertContains(['textDirectionLtr', 'textDirectionRtl'], TextDirectionPlugin::toolbar());
    }
}
