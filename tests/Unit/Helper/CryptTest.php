<?php

namespace Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Constraint\IsType as PHPUnit_IsType;
use TeamSpeak3\Helpers\CryptHelper;

class CryptTest extends TestCase
{
  public function testEncrypt() {
    $crypto = new CryptHelper('My Secret Key');
    $this->assertEquals('b45xr3dIAI4=', $crypto->encrypt('password'));
    $this->assertEquals('password', $crypto->decrypt('b45xr3dIAI4='));
  }
}