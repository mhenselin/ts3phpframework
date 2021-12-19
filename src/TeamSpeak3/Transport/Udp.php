<?php

namespace TeamSpeak3\Transport;

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
 * @class TeamSpeak3_Transport_UDP
 * @brief Class for connecting to a remote server through UDP.
 */
class Udp extends TransportAbstract
{
  /**
   * Connects to a remote server.
   *
   * @throws TransportException
   */
  public function connect(): void
  {
    if($this->stream !== null) {
		return;
	}

    $host = (string)$this->config["host"];
    $port = (string)$this->config["port"];

    $address = "udp://" . (str_contains($host,":") ? "[" . $host . "]" : $host) . ":" . $port;
    $timeout = (int) $this->config["timeout"];

    $this->stream = @stream_socket_client($address, $errno, $errstr, $timeout);

    if($this->stream === FALSE) {
		throw new TransportException(StringHelper::factory($errstr)->toUtf8()->toString(),
			$errno);
	}

    @stream_set_timeout($this->stream, $timeout);
    @stream_set_blocking($this->stream, $this->config["blocking"] ? 1 : 0);
  }

  /**
   * Disconnects from a remote server.
   *
   * @return void
   */
  public function disconnect(): void
  {
    if($this->stream === null) {
		return;
	}

    $this->stream = null;

    SignalHelper::getInstance()->emit(strtolower($this->getAdapterType()) . "Disconnected");
  }

  /**
   * Reads data from the stream.
   *
   * @param  integer $length
   * @throws TransportException
   * @return StringHelper
   */
  public function read(int $length = 4096): StringHelper
  {
    $this->connect();
    $this->waitForReadyRead();

    $data = @fread($this->stream, $length);

    SignalHelper::getInstance()->emit(strtolower($this->getAdapterType()) . "DataRead", $data);

    if($data === FALSE)
    {
      throw new TransportException("connection to server '" . $this->config["host"] . ":" . $this->config["port"] . "' lost");
    }

    return new StringHelper($data);
  }

	/**
	 * Writes data to the stream.
	 *
	 * @param string $data
	 * @return void
	 * @throws TransportException
	 */
  public function send($data): void
  {
    $this->connect();

    @stream_socket_sendto($this->stream, $data);

    SignalHelper::getInstance()->emit(strtolower($this->getAdapterType()) . "DataSend", $data);
  }
}
