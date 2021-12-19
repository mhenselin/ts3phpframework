<?php

namespace Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Constraint\IsType as PHPUnit_IsType;

use TeamSpeak3\Helpers\Signal\Handler;
use TeamSpeak3\Helpers\Signal\HelpersSignalException;
use TeamSpeak3\Helpers\SignalHelper;

class SignalTest extends TestCase
{
  protected static $cTriggers;
  
  protected static $signal = 'notifyEvent';
  protected static $callback = __CLASS__ . '::onEvent';
  protected static $testString = '!@w~//{tI_8G77<qS+g*[Gb@u`pJ^2>rO*f=KS:8Yj';
  
  protected function setUp(): void {
    static::$cTriggers = [];
    foreach(SignalHelper::getInstance()->getSignals() as $signal) {
		SignalHelper::getInstance()->clearHandlers($signal);
	}
  }
  
  public function testGetInstance(): void {
    $snapshot = clone SignalHelper::getInstance();
    $this->assertEquals($snapshot, SignalHelper::getInstance());
    $this->assertEmpty(SignalHelper::getInstance()->getSignals());
  }
  
  public function testGetCallbackHash(): void {
    $this->assertEquals(
      md5(static::$callback),
      SignalHelper::getInstance()->getCallbackHash(static::$callback));
  }
  
  public function testGetCallbackHashException(): void {
    $this->expectException(HelpersSignalException::class);
    $this->expectExceptionMessage('invalid callback specified');
    SignalHelper::getInstance()->getCallbackHash([]);
  }
  
  public function testSubscribe(): void {
    $snapshot = clone SignalHelper::getInstance();
    $instSignal = SignalHelper::getInstance();
    
    $signalHandler = $instSignal->subscribe(static::$signal, static::$callback);
    // Test state: returned TeamSpeak3_Helper_Signal_Handler
    $this->assertInstanceOf(Handler::class, $signalHandler);
    $this->assertNotEquals($snapshot, SignalHelper::getInstance());
  
    // Test state: subscribed signals
    $signals  = $instSignal->getSignals();
    $this->assertInternalType(PHPUnit_IsType::TYPE_ARRAY, $signals);
    $this->assertEquals(1, count($signals));
    $this->assertEquals(static::$signal, $signals[0]);
  
    // Test state: subscribed signal handlers
    $handlers = $instSignal->getHandlers(static::$signal);
    $this->assertInternalType(PHPUnit_IsType::TYPE_ARRAY, $handlers);
    $this->assertEquals(1, count($handlers));
    $this->assertArrayHasKey(
      SignalHelper::getInstance()->getCallbackHash(static::$callback),
      $handlers);
    $handler = $handlers[SignalHelper::getInstance()->getCallbackHash(static::$callback)];
    $this->assertInstanceOf(TeamSpeak3_Helper_Signal_Handler::class, $handler); 
    $this->assertEquals($signalHandler, $handler);
  }
  
  public function testEmit(): void {
    $callbackHash = SignalHelper::getInstance()
      ->getCallbackHash(__CLASS__ . '::onEvent');
    SignalHelper::getInstance()->subscribe(static::$signal, static::$callback);
    $response = SignalHelper::getInstance()->emit(static::$signal, static::$testString);
    $this->assertEquals(static::$testString, $response);
    $this->assertInternalType(gettype(static::$testString), $response);
    
    // Verify correct count of callback executions
    $this->assertEquals(1, count(static::$cTriggers));
    $this->assertEquals(
      '0-'.static::$testString,
      static::$cTriggers[$callbackHash]);
  }
  
  public function testSubscribeTwo(): void {
    $instSignal = SignalHelper::getInstance();
    $signalHandler1 = $instSignal->subscribe(
      static::$signal, static::$callback);
    $signalHandler2 = $instSignal->subscribe(
      static::$signal, static::$callback.'2');
  
    // Test state: subscribed signals
    $signals = $instSignal->getSignals();
    $this->assertEquals(1, count($signals));
    $this->assertEquals(static::$signal, $signals[0]);
  
    // Test state: subscribed signal handlers
    $handlers = $instSignal->getHandlers(static::$signal);
    $this->assertEquals(2, count($handlers));
    $this->assertArrayHasKey(
      $instSignal->getCallbackHash(static::$callback),
      $handlers);
    $this->assertArrayHasKey(
      $instSignal->getCallbackHash(static::$callback.'2'),
      $handlers);
    
    $handler1 = $handlers[$instSignal->getCallbackHash(static::$callback)];
    $this->assertEquals($signalHandler1, $handler1);
    $handler2 = $handlers[$instSignal->getCallbackHash(static::$callback.'2')];
    $this->assertEquals($signalHandler2, $handler2);
  }
  
  public function testEmitToTwoSubscribers(): void {
    $instSignal = SignalHelper::getInstance();
    $callbackHash1 = $instSignal->getCallbackHash(__CLASS__ . '::onEvent');
    $callbackHash2 = $instSignal->getCallbackHash(__CLASS__ . '::onEvent2');
    
    $instSignal->subscribe(static::$signal, static::$callback);
    $instSignal->subscribe(static::$signal, static::$callback.'2');
  
    $response = $instSignal->emit(static::$signal, static::$testString);
    $this->assertEquals(static::$testString, $response);
    $this->assertInternalType(gettype(static::$testString), $response);
  
    // Verify correct count of callback executions
    $this->assertEquals(2, count(static::$cTriggers));
    $this->assertEquals(
      '0-' . static::$testString,
      static::$cTriggers[$callbackHash1]);
    $this->assertEquals(
      '1-' . static::$testString,
      static::$cTriggers[$callbackHash2]);
  }

  public static function onEvent($data) {
    $signature = SignalHelper::getInstance()
      ->getCallbackHash(__CLASS__ . '::onEvent');
    
    static::$cTriggers[$signature] = count(static::$cTriggers).'-'.$data;
    return $data;
  }
  
  public static function onEvent2($data) {
    $signature = SignalHelper::getInstance()
      ->getCallbackHash(__CLASS__ . '::onEvent2');
  
    static::$cTriggers[$signature] = count(static::$cTriggers).'-'.$data;
    return $data;
  }
}

