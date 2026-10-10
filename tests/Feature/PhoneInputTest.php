<?php

namespace Tests\Feature;

use App\Support\PhoneNumber;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneInputTest extends TestCase
{
    public static function typedNumbers(): array
    {
        return [
            'plain' => ['+96895522928', '+96895522928'],
            'local' => ['95522928', '95522928'],
            'arabic keyboard' => ['٩٥٥٢٢٩٢٨', '95522928'],
            'arabic with code' => ['+٩٦٨٩٥٥٢٢٩٢٨', '+96895522928'],
            'persian digits' => ['۹۵۵۲۲۹۲۸', '95522928'],
            'pasted with direction marks' => ["\u{202A}+968 9552 2928\u{202C}", '+968 9552 2928'],
            'non-breaking spaces' => ["+968\u{00A0}9552\u{00A0}2928", '+968 9552 2928'],
        ];
    }

    #[DataProvider('typedNumbers')]
    public function test_a_tel_input_accepts_what_people_type(string $typed, string $stored): void
    {
        $input = TextInput::make('phone')->tel();

        $validated = $input->mutateStateForValidation($typed);

        $this->assertSame($stored, $validated);
        $this->assertSame($stored, $input->mutateDehydratedState($typed));
        $this->assertTrue(Validator::make(['phone' => $validated], ['phone' => ['regex:'.$input->getTelRegex()]])->passes());
    }

    public function test_other_text_inputs_are_left_alone(): void
    {
        $this->assertSame('٩٥', TextInput::make('name')->mutateStateForValidation('٩٥'));
    }

    public function test_normalize_reads_arabic_digits(): void
    {
        $this->assertSame('96895522928', PhoneNumber::normalize('٩٥٥٢٢٩٢٨'));
    }
}
