<?php

namespace Tests\Unit;

use App\Models\Dealer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DealerWhatsappNumberTest extends TestCase
{
    #[Test]
    #[DataProvider('validPhones')]
    public function returns_uk_whatsapp_digits_for_common_formats(string $phone, string $expected): void
    {
        $dealer = new Dealer(['phone' => $phone]);

        $this->assertSame($expected, $dealer->whatsappNumber());
    }

    #[Test]
    #[DataProvider('invalidPhones')]
    public function returns_null_for_placeholder_or_incomplete_numbers(?string $phone): void
    {
        $dealer = new Dealer(['phone' => $phone]);

        $this->assertNull($dealer->whatsappNumber());
    }

    public static function validPhones(): array
    {
        return [
            'leading zero mobile' => ['07907669002', '447907669002'],
            'mobile without leading zero' => ['7907669002', '447907669002'],
            'plus forty four mobile' => ['+44 7907 669002', '447907669002'],
            'spaced landline' => ['020 8592 1234', '442085921234'],
        ];
    }

    public static function invalidPhones(): array
    {
        return [
            'empty' => [''],
            'null' => [null],
            'placeholder landline' => ['02000000000'],
            'too short' => ['79076'],
            'non uk' => ['15551234567'],
        ];
    }
}
