<?php

namespace Tests\Unit\Transport;

use \PHPUnit\Framework\TestCase;
use TeamSpeak3\Adapter\ServerQueryAdapter;
use TeamSpeak3\Transport\TransportException;
use TeamSpeak3\Transport\Udp;

class UDPTest extends TestCase
{
  
  public function testConstructorNoException(): void {
    $adapter = new Udp([
		'host' => 'test',
		'port' => 12345
	]);
    $this->assertInstanceOf(Udp::class, $adapter);
    
    $this->assertArrayHasKey('host', $adapter->getConfig());
    $this->assertEquals(['host' => 'test'], $adapter->getConfig('host'));
    
    $this->assertArrayHasKey('port', $adapter->getConfig());
    $this->assertEquals(['port' => 12345], $adapter->getConfig('port'));
    
    $this->assertArrayHasKey('timeout', $adapter->getConfig());
	$this->assertIsInt($adapter->getConfig('timeout')['timeout']);

    $this->assertArrayHasKey('blocking', $adapter->getConfig());
	$this->assertIsInt($adapter->getConfig('blocking')['blocking']);
  }
  
  public function testConstructorExceptionNoHost(): void {
    $this->expectException(TransportException::class);
    $this->expectExceptionMessage("config must have a key for 'host'");
    
    $adapter = new Udp(['port' => 12345]);
  }
  
  public function testConstructorExceptionNoPort(): void {
    $this->expectException(TransportException::class);
    $this->expectExceptionMessage("config must have a key for 'port'");
    
    $adapter = new Udp(['host' => 'test']);
  }
  
  public function testGetConfig(): void {
    $adapter = new Udp(
      ['host' => 'test', 'port' => 12345]
    );
    $this->assertIsArray($adapter->getConfig());
    $this->assertCount(4, $adapter->getConfig());
    $this->assertArrayHasKey('host', $adapter->getConfig());
    $this->assertEquals('test', $adapter->getConfig()['host']);
    $this->assertEquals('test', $adapter->getConfig('host'));
  }
  
  public function testSetGetAdapter(): void {
    $transport = new Udp(
      ['host' => 'test', 'port' => 12345]
    );
    // Mocking adaptor since `stream_socket_client()` depends on running server
    $adaptor = $this->createMock(ServerQueryAdapter::class);
    $transport->setAdapter($adaptor);
    
    $this->assertSame($adaptor, $transport->getAdapter());
  }
  
  public function testGetStream(): void {
    $transport = new Udp(
      ['host' => 'test', 'port' => 12345]
    );
    $this->assertNull($transport->getStream());
  }
  
  public function testConnect(): void {
    $transport = new Udp([
		'host' => '127.0.0.1',
		'port' => 12345
	]);
	$this->assertIsNotResource($transport->getStream());
  }
  
  public function testConnectBadHost(): void {
    $transport = new Udp([
			'host' => 'test',
			'port' => 12345
		]);
    $this->expectException(TransportException::class);
    $this->expectExceptionMessage('getaddrinfo failed');
  }

    /**
     * @throws TransportException
     */
    public function testDisconnect(): void {
        $transport = new Udp(['host' => '127.0.0.1', 'port' => 12345]);
        $transport->connect();
        $this->assertNull($transport->getStream());
    }

    /**
     * @throws TransportException
     */
    public function testDisconnectNoConnection(): void {
        $transport = new Udp(['host' => 'test', 'port' => 12345]);
        $transport->connect();
        $this->assertNull($transport->getStream());
    }
  
  public function testReadNoConnection(): void {
    $transport = new Udp(['host' => 'test', 'port' => 12345]);
    $this->expectException(TransportException::class);
    $this->expectExceptionMessage('getaddrinfo failed');
    $transport->read();
  }
  
  public function testSendNoConnection(): void {
    $transport = new Udp(
      ['host' => 'test', 'port' => 12345]
    );
    $this->expectException(TransportException::class);
    $this->expectExceptionMessage('getaddrinfo failed');
    $transport->send('test.send');
  }
}
