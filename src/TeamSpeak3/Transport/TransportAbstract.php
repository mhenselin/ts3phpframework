<?php

namespace TeamSpeak3\Transport;

use TeamSpeak3\Adapter\AdapterAbstract;
use TeamSpeak3\Helpers\SignalHelper;
use TeamSpeak3\Helpers\StringHelper;

/**
 * @file
 * TeamSpeak 3 PHP Framework
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package   TeamSpeak3
 * @author    Sven 'ScP' Paulsen
 * @copyright Copyright (c) Planet TeamSpeak. All rights reserved.
 */

/**
 * @class TeamSpeak3_Transport_Abstract
 * @brief Abstract class for connecting to a TeamSpeak 3 Server through different ways of transport.
 */
abstract class TransportAbstract
{
  /**
   * Stores user-provided configuration settings.
   *
   * @var array $config
   */
  protected array $config = [];

  /**
   * Stores the stream resource of the connection.
   *
   * @var resource
   */
  protected $stream = null;

  /**
   * Stores an optional stream session for the connection.
   * 
   * @var $session
   */
  protected $session = null;

  /**
   * Stores the TeamSpeak3_Adapter_Abstract object using this transport.
   *
   * @var AdapterAbstract|null
   */
  protected ?AdapterAbstract $adapter = null;

  /**
   * The TeamSpeak3_Transport_Abstract constructor.
   *
   * @param  array $config
   * @throws TransportException
   */
  public function __construct(array $config)
  {
    if(!array_key_exists("host", $config))
    {
      throw new TransportException("config must have a key for 'host' which specifies the server host name");
    }

    if(!array_key_exists("port", $config))
    {
      throw new TransportException("config must have a key for 'port' which specifies the server port number");
    }

    $config["timeout"] = $config["timeout"] ?? 10;
    $config["blocking"] = $config["blocking"] ?? 1;

    $this->config = $config;
  }

  /**
   * Commit pending data.
   *
   * @return array
   */
  public function __sleep()
  {
    return array("config");
  }

    /**
     * Reconnects to the remote server.
     *
     * @return void
     * @throws TransportException
     */
  public function __wakeup(): void
  {
    $this->connect();
  }

  /**
   * The TeamSpeak3_Transport_Abstract destructor.
   *
   * @return void
   */
  public function __destruct()
  {
//      try {
//          // $this->adapter->__destruct();
//      } catch (\Exception $e) {
//          //TODO: what to do here?
//      }
      $this->disconnect();
  }

  /**
   * Connects to a remote server.
   *
   * @throws TransportException
   * @return void
   */
  abstract public function connect(): void;

  /**
   * Disconnects from a remote server.
   *
   * @return void
   */
  abstract public function disconnect(): void;

  /**
   * Reads data from the stream.
   *
   * @param  integer $length
   * @throws TransportException
   * @return StringHelper
   */
  abstract public function read(int $length = 4096): StringHelper;

  /**
   * Writes data to the stream.
   *
   * @param  string $data
   * @return void
   */
  abstract public function send(string $data): void;

  /**
   * Returns the underlying stream resource.
   *
   * @return resource
   */
  public function getStream()
  {
    return $this->stream;
  }

    /**
    * Returns the configuration variables in this adapter.
    *
    * @param  string $key
    * @param array $default
    * @return array
    */
    public function getConfig(string $key = '', array $default = []): array
    {
        if($key !== '')
        {
            if (array_key_exists($key, $this->config)) {
                return [$key => $this->config[$key]];
            }
            return $default;
        }
        return $this->config;
    }

  /**
   * Sets the AdapterAbstract object using this transport.
   *
   * @param  AdapterAbstract $adapter
   * @return void
   */
  public function setAdapter(AdapterAbstract $adapter): void
  {
    $this->adapter = $adapter;
  }

  /**
   * Returns the TeamSpeak3_Adapter_Abstract object using this transport.
   *
   * @return AdapterAbstract|null
   */
  public function getAdapter(): ?AdapterAbstract
  {
      return $this->adapter;
  }

  /**
   * Returns the adapter type.
   *
   * @return string
   */
  public function getAdapterType(): string
  {
	  $string = StringHelper::factory(get_class($this->adapter));
	  return $string
		  ->substr($string->findLast("_"))
		  ->replace(["_", " "], "")
		  ->toString();
  }

  /**
   * Returns header/meta data from stream pointer.
   *
   * @throws TransportException
   * @return array
   */
  public function getMetaData(): array
  {
    if($this->stream === null)
    {
      throw new TransportException("unable to retrieve header/meta data from stream pointer");
    }

    return stream_get_meta_data($this->stream);
  }

	/**
	 * Returns TRUE if the transport is connected.
	 *
	 * @return bool
	 */
  public function isConnected(): bool
  {
    return is_resource($this->stream);
  }

  /**
   * Blocks a stream until data is available for reading if the stream is connected
   * in non-blocking mode.
   *
   * @param integer $time
   * @return void
   */
  protected function waitForReadyRead(int $time = 0): void {
    if($this->config["blocking"] || !$this->isConnected()) {
		return;
	}

    do
    {
      $read = array($this->stream);
      $null = null;

      if($time)
      {
        SignalHelper::getInstance()->emit(strtolower($this->getAdapterType()) . "WaitTimeout", $time, $this->getAdapter());
      }

      $time += $this->config["timeout"];
    }
    while(@stream_select($read, $null, $null, $this->config["timeout"]) === 0);
  }
}
