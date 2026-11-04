<?php

namespace Tests\Feature;

use App\Filament\Resources\StableResource\Pages\EditStable;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/** The placeholders and upload hints every form gets from App\Filament\Support\FieldPlaceholders. */
class AdminFieldPlaceholdersTest extends TestCase
{
    use PreparesStableBookings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings();
        $this->withoutVite();

        Gate::before(fn () => true);
        $this->actingAs($this->makeAdminUser());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_fields_get_placeholders_and_uploads_get_size_hints(): void
    {
        $stable = $this->makeStable();

        Livewire::test(EditStable::class, ['record' => $stable->slug])
            ->assertOk()
            // the model's own example beats the generic one
            ->assertFormFieldExists('en_name', fn (TextInput $f) => $f->getPlaceholder() === 'e.g. Al Noor Stables')
            ->assertFormFieldExists('map_link', fn (TextInput $f) => $f->getPlaceholder() === 'Paste the Google Maps link')
            ->assertFormFieldExists('city_id', fn (Select $f) => $f->getPlaceholder() === 'Choose city')
            ->assertFormFieldExists('cover_photo', fn (FileUpload $f) => $f->getHint() === '1600 × 900 px, landscape · JPG, PNG, WebP, GIF · up to 10 MB');

        App::setLocale('ar');

        Livewire::test(EditStable::class, ['record' => $stable->slug])
            ->assertFormFieldExists('ar_name', fn (TextInput $f) => $f->getPlaceholder() === 'مثل: إسطبلات النور')
            ->assertFormFieldExists('cover_photo', fn (FileUpload $f) => str_starts_with((string) $f->getHint(), '1600 × 900 بكسل'));
    }

    public function test_both_languages_have_the_same_keys(): void
    {
        $keys = fn (string $locale) => array_keys(Arr::dot(require lang_path("{$locale}/placeholders.php")));

        $this->assertSame($keys('en'), $keys('ar'));
    }
}
