<?php

namespace Tests\Unit;

use App\Support\Identifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IdentifierTest extends TestCase
{
    public function test_email_is_lowercased_and_trimmed(): void
    {
        $id = Identifier::parse('  Admin@CaseHub.Test ');

        $this->assertSame(Identifier::EMAIL, $id->type);
        $this->assertSame('admin@casehub.test', $id->value);
    }

    #[DataProvider('mobiles')]
    public function test_mobile_formats_normalise_to_ten_digits(string $input): void
    {
        $id = Identifier::parse($input);

        $this->assertSame(Identifier::MOBILE, $id->type);
        $this->assertSame('9876543210', $id->value);
    }

    public static function mobiles(): array
    {
        return [
            ['9876543210'],
            ['+91 98765 43210'],
            ['+91-9876543210'],
            ['098765 43210'],
            ['(987) 654-3210'],
        ];
    }

    #[DataProvider('invalid')]
    public function test_invalid_input_is_rejected(?string $input): void
    {
        $this->assertNull(Identifier::parse($input));
    }

    public static function invalid(): array
    {
        return [
            [null],
            [''],
            ['   '],
            ['not-an-email'],
            ['12345'],
            ['5876543210'],          // Indian mobiles start 6-9
            ['98765432101'],         // 11 digits, no leading 0
            ["admin@x.com' OR 1=1"],
        ];
    }

    public function test_masked_never_reveals_the_full_value(): void
    {
        $this->assertSame('a****@casehub.test', Identifier::parse('admin@casehub.test')->masked());
        $this->assertSame('98******10', Identifier::parse('9876543210')->masked());
    }
}
