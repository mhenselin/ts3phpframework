<?php

namespace TeamSpeak3\Helpers;

use TeamSpeak3\Helpers\Signal\Handler;

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
 * @class TeamSpeak3_Helper_Signal
 * @brief Helpers class for signal slots.
 */
class SignalHelper
{
  /**
   * Stores the TeamSpeak3_Helper_Signal object.
   *
   * @var SignalHelper
   */
  protected static $instance = null;

  /**
   * Stores subscribed signals and their slots.
   *
   * @var array
   */
  protected $sigslots = array();

  /**
   * Emits a signal with a given set of parameters.
   * 
   * @param string|StringHelper $signal
   * @param mixed|null $params
   * @return mixed
   *@todo: Confirm / fix $return is set to last $slot->call() return value.
   *      It appears all previous calls before last are lost / ignored.
   *
   */
  public function emit(StringHelper|string $signal, mixed $params = null): mixed
  {
    if(!$this->hasHandlers($signal))
    {
      return null;
    }

    if(!is_array($params))
    {
      $params = func_get_args();
      $params = array_slice($params, 1);
    }

    foreach($this->sigslots[$signal] as $slot)
    {
      $return = $slot->call($params);
    }

    return $return;
  }
  
  /**
   * Generates a MD5 hash based on a given callback.
   *
   * @param  mixed  $callback
   * @param  StringHelper
   * @return StringHelper
   */
  public function getCallbackHash($callback)
  {
    if(!is_callable($callback, TRUE, $callable_name))
    {
      throw new TeamSpeak3_Helper_Signal_Exception("invalid callback specified");
    }
    
    return md5($callable_name);
  }

  /**
   * Subscribes to a signal and returns the signal handler.
   *
   * @param  StringHelper $signal
   * @param  mixed  $callback
   * @return Handler
   */
  public function subscribe($signal, $callback)
  {
    if(empty($this->sigslots[$signal]))
    {
      $this->sigslots[$signal] = array();
    }

    $index = $this->getCallbackHash($callback);

    if(!array_key_exists($index, $this->sigslots[$signal]))
    {
      $this->sigslots[$signal][$index] = new TeamSpeak3_Helper_Signal_Handler($signal, $callback);
    }

    return $this->sigslots[$signal][$index];
  }

  /**
   * Unsubscribes from a signal.
   *
   * @param  StringHelper $signal
   * @param  mixed  $callback
   * @return void
   */
  public function unsubscribe($signal, $callback = null)
  {
    if(!$this->hasHandlers($signal))
    {
      return;
    }

    if($callback !== null)
    {
      $index = $this->getCallbackHash($callback);

      if(!array_key_exists($index, $this->sigslots[$signal]))
      {
        return;
      }

      unset($this->sigslots[$signal][$index]);
    }
    else
    {
      unset($this->sigslots[$signal]);
    }
  }

  /**
   * Returns all registered signals.
   *
   * @return array
   */
  public function getSignals()
  {
    return array_keys($this->sigslots);
  }

  /**
   * Returns TRUE there are slots subscribed for a specified signal.
   *
   * @param  StringHelper $signal
   * @return boolean
   */
  public function hasHandlers($signal)
  {
    return empty($this->sigslots[$signal]) ? FALSE : TRUE;
  }

  /**
   * Returns all slots for a specified signal.
   *
   * @param  StringHelper $signal
   * @return array
   */
  public function getHandlers($signal)
  {
    if($this->hasHandlers($signal))
    {
      return $this->sigslots[$signal];
    }

    return array();
  }

  /**
   * Clears all slots for a specified signal.
   *
   * @param  StringHelper $signal
   * @return void
   */
  public function clearHandlers($signal)
  {
    if($this->hasHandlers($signal))
    {
      unset($this->sigslots[$signal]);
    }
  }

  /**
   * Returns a singleton instance of TeamSpeak3_Helper_Signal.
   *
   * @return TeamSpeak3_Helper_Signal
   */
  public static function getInstance()
  {
    if(self::$instance === null)
    {
      self::$instance = new self();
    }

    return self::$instance;
  }
}
