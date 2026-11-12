<?php

namespace Tests\Feature;

use App\Filament\Pages\SmtpSettings;
use App\Models\SiteSetting;
use App\Support\MailServer;
use App\Support\SecretSetting;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/** admin → SMTP Settings: site settings with the password encrypted, moved from the old key/value table. */
class SmtpSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type')->default('text');
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->string('managed_by')->nullable();
            $table->timestamps();
        });
        SiteSetting::clearCache();

        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin', 'email' => 'admin@example.com']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_old_plain_text_settings_move_over_encrypted(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->nullable();
            $table->timestamps();
        });
        DB::table('settings')->insert([
            ['key' => 'mail_host', 'value' => 'smtp.example.com'],
            ['key' => 'mail_username', 'value' => 'mailer'],
            ['key' => 'mail_password', 'value' => 'pl4in-secret'],
            ['key' => 'mail_from_address', 'value' => 'noreply@example.com'],
        ]);

        (require database_path('migrations/2026_11_12_000001_move_smtp_settings_to_site_settings.php'))->up();

        $this->assertSame(['smtp.example.com', 'mailer', 'pl4in-secret'], array_values(array_intersect_key(MailServer::settings(), array_flip(['host', 'username', 'password']))));
        $this->assertStringNotContainsString('pl4in-secret', DB::table('site_settings')->pluck('value')->implode('|'));
        $this->assertNull(DB::table('settings')->where('key', 'mail_password')->value('value'));
    }

    public function test_the_page_keeps_the_password_off_the_page_and_blank_keeps_it(): void
    {
        Livewire::test(SmtpSettings::class)
            ->fillForm(['host' => 'smtp.example.com', 'port' => 587, 'username' => 'mailer', 'password' => 's3cret', 'encryption' => 'tls', 'from_address' => 'noreply@example.com', 'from_name' => 'Muhra'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('s3cret', SecretSetting::get('smtp.password'));

        Livewire::test(SmtpSettings::class)
            ->assertFormSet(['host' => 'smtp.example.com', 'password' => ''])
            ->assertDontSeeHtml('s3cret')
            ->fillForm(['from_name' => 'Muhra Equine'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('s3cret', SecretSetting::get('smtp.password'));

        MailServer::apply(MailServer::settings());
        $this->assertSame(['smtp.example.com', 's3cret', 'Muhra Equine'], [config('mail.mailers.smtp.host'), config('mail.mailers.smtp.password'), config('mail.from.name')]);
    }
}
