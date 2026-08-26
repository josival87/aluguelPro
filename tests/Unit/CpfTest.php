<?php

namespace Tests\Unit;

use App\Support\Cpf;
use PHPUnit\Framework\TestCase;

class CpfTest extends TestCase
{
    public function test_it_normalizes_and_formats_cpf_without_losing_leading_zeroes(): void
    {
        $this->assertSame('01234567890', Cpf::digits('012.345.678-90'));
        $this->assertSame('012.345.678-90', Cpf::format('01234567890'));
        $this->assertNull(Cpf::digits(null));
        $this->assertNull(Cpf::digits(''));
    }
}
