<?php

namespace TeamSpeak3\Transport;

use TeamSpeak3\Adapter\ServerQuery\ServerQueryException;
use TeamSpeak3\Helpers\SignalHelper;
use TeamSpeak3\Helpers\StringHelper;
use Throwable;

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
 * @class TeamSpeak3_Transport_TCP
 * @brief Class for connecting to a remote server through TCP.
 */
class Tcp extends TransportAbstract
{
    /**
     * Connects to a remote server.
     *
     * @return void
     * @throws ServerQueryException
     * @throws TransportException
     */
  public function connect(): void
  {
    if($this->stream !== null) {
        return;
    }

    $host     = (string)$this->config["host"];
    $port     = (string)$this->config["port"];
    $timeout  = (int)$this->config["timeout"];
    $blocking = (int)$this->config["blocking"];

    if(empty($this->config["ssh"]))
    {
      $address = "tcp://" . (str_contains($host,":") ? "[" . $host . "]" : $host) . ":" . $port;
      $options = empty($this->config["tls"]) ? array() : array("ssl" => array("allow_self_signed" => TRUE, "verify_peer" => FALSE, "verify_peer_name" => FALSE));

      $this->stream = @stream_socket_client($address, $errno, $errstr, $this->config["timeout"], STREAM_CLIENT_CONNECT, stream_context_create($options));

      if($this->stream === FALSE)
      {
        throw new TransportException(StringHelper::factory($errstr)->toUtf8()->toString(), $errno);
      }

      if(!empty($this->config["tls"]))
      {
        stream_socket_enable_crypto($this->stream, TRUE, STREAM_CRYPTO_METHOD_SSLv23_CLIENT);
      }
    }
    else
    {
		//TODO: I don't like the @ stuff
      $this->session = @ssh2_connect($host, $port);

      if($this->session === FALSE)
      {
        throw new TransportException("failed to establish secure shell connection to server '" . $this->config["host"] . ":" . $this->config["port"] . "'");
      }

      if(!@ssh2_auth_password($this->session, $this->config["username"], $this->config["password"]))
      {
        throw new ServerQueryException("invalid loginname or password", 0x208);
      }

      $this->stream = @ssh2_shell($this->session, "raw");

      if($this->stream === FALSE)
      {
        throw new TransportException("failed to open a secure shell on server '" . $this->config["host"] . ":" . $this->config["port"] . "'");
      }
    }

    @stream_set_timeout($this->stream, $timeout);
    @stream_set_blocking($this->stream, $blocking ? 1 : 0);
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

    if(is_resource($this->session))
    {
      @ssh2_disconnect($this->session);
    }

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

    $data = @stream_get_contents($this->stream, $length);

    SignalHelper::getInstance()->emit(strtolower($this->getAdapterType()) . "DataRead", $data);

    if($data === false)
    {
      throw new TransportException("connection to server '" . $this->config["host"] . ":" . $this->config["port"] . "' lost");
    }

    return new StringHelper($data);
  }

    /**
     * Reads a single line of data from the stream.
     *
     * @param StringHelper|string $token
     * @return StringHelper
     * @throws ServerQueryException
     * @throws TransportException
     */
  public function readLine(StringHelper|string $token = "\n"): StringHelper
  {
    $this->connect();

    $line = StringHelper::factory("");

    while(!$line->endsWith($token))
    {
      $this->waitForReadyRead();

      $data = @fgets($this->stream, 4096);

      SignalHelper::getInstance()->emit(strtolower($this->getAdapterType()) . "DataRead", $data);

      if($data === FALSE)
      {
        if($line->count())
        {
          $line->append($token);
        }
        else
        {
          throw new TransportException("connection to server '" . $this->config["host"] . ":" . $this->config["port"] . "' lost");
        }
      }
      else
      {
        $line->append($data);
      }
    }

    return $line->trim();
  }

  /**
   * Writes data to the stream.
   *
   * @param  string $data
   * @return void
   * @throws Throwable
   */
  public function send(string $data): void
  {
      try {
          $this->connect();
          @fwrite($this->stream, $data);
          SignalHelper::getInstance()->emit(strtolower($this->getAdapterType()) . "DataSend", $data);
      } catch (ServerQueryException $e) {
          // TODO: implement
          throw $e;
      } catch (TransportException $e) {
          // TODO: implement
          throw $e;
      }
  }

  /**
   * Writes a line of data to the stream.
   *
   * @param  string $data
   * @param  string $separator
   * @return void
   */
  public function sendLine($data, $separator = "\n")
  {
    $size = strlen($data);
    $pack = 4096;

    for($seek = 0 ;$seek < $size;)
    {
      $rest = $size-$seek;
      $pack = $rest < $pack ? $rest : $pack;
      $buff = substr($data, $seek, $pack);
      $seek = $seek+$pack;

      if($seek >= $size) $buff .= $separator;

      $this->send($buff);
    }
  }
}
