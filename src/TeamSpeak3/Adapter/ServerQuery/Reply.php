<?php

namespace TeamSpeak3\Adapter\ServerQuery;

use TeamSpeak3;
use TeamSpeak3\Helpers\SignalHelper;
use TeamSpeak3\Helpers\StringHelper;
use TeamSpeak3\Node\Host;

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
 * @class TeamSpeak3_Adapter_ServerQuery_Reply
 * @brief Provides methods to analyze and format a ServerQuery reply.
 */
class Reply
{
  /**
   * Stores the command used to get this reply.
   *
   * @var StringHelper
   */
  protected $cmd = null;

  /**
   * Stores the servers reply (if available).
   *
   * @var StringHelper
   */
  protected $rpl = null;

  /**
   * Stores connected TeamSpeak3_Node_Host object.
   *
   * @var TeamSpeak3_Node_Host
   */
  protected $con = null;

  /**
   * Stores an assoc array containing the error info for this reply.
   *
   * @var array
   */
  protected $err = array();

  /**
   * Sotres an array of events that occured before or during this reply.
   *
   * @var array
   */
  protected $evt = array();

  /**
   * Indicates whether exceptions should be thrown or not.
   *
   * @var boolean
   */
  protected $exp = TRUE;

    /**
     * Creates a new TeamSpeak3_Adapter_ServerQuery_Reply object.
     *
     * @param array $rpl
     * @param string $cmd
     * @param Host|null $con
     * @param boolean $exp
     */
  public function __construct(array $rpl, string $cmd = '', Host $con = null, bool $exp = true)
  {
    $this->cmd = new StringHelper($cmd);
    $this->con = $con;
    $this->exp = (bool) $exp;
    
    $this->fetchError(array_pop($rpl));
    $this->fetchReply($rpl);
  }

  /**
   * Returns the reply as an TeamSpeak3_Helper_String object.
   *
   * @return StringHelper
   */
  public function toString()
  {
    return (!func_num_args()) ? $this->rpl->unescape() : $this->rpl;
  }

  /**
   * Returns the reply as a standard PHP array where each element represents one item.
   *
   * @return array
   */
  public function toLines()
  {
    if(!count($this->rpl)) {
        return array();
    }

    $list = $this->toString(0)->split(TeamSpeak3::SEPARATOR_LIST);

    if(!func_num_args())
    {
      for($i = 0, $iMax = count($list); $i < $iMax; $i++) {
          $list[$i]->unescape();
      }
    }

    return $list;
  }

  /**
   * Returns the reply as a standard PHP array where each element represents one item in table format.
   *
   * @return array
   */
  public function toTable()
  {
    $table = array();

    foreach($this->toLines(0) as $cells)
    {
      $pairs = $cells->split(TeamSpeak3::SEPARATOR_CELL);

      if(!func_num_args())
      {
        for($i = 0, $iMax = count($pairs); $i < $iMax; $i++) {
            $pairs[$i]->unescape();
        }
      }

      $table[] = $pairs;
    }

    return $table;
  }

  /**
   * Returns a multi-dimensional array containing the reply splitted in multiple rows and columns.
   *
   * @return array
   */
  public function toArray()
  {
    $array = array();
    $table = $this->toTable(1);

    for($i = 0; $i < count($table); $i++)
    {
      foreach($table[$i] as $pair)
      {
        if(!count($pair))
        {
          continue;
        }
        
        if(!$pair->contains(TeamSpeak3::SEPARATOR_PAIR))
        {
          $array[$i][$pair->toString()] = null;
        }
        else
        {
          list($ident, $value) = $pair->split(TeamSpeak3::SEPARATOR_PAIR, 2);

          $array[$i][$ident->toString()] = $value->isInt() ? $value->toInt() : (!func_num_args() ? $value->unescape() : $value);
        }
      }
    }

    return $array;
  }

  /**
   * Returns a multi-dimensional assoc array containing the reply splitted in multiple rows and columns.
   * The identifier specified by key will be used while indexing the array.
   *
   * @param  $key
   * @return array
   */
  public function toAssocArray($ident)
  {
    $nodes = (func_num_args() > 1) ? $this->toArray(1) : $this->toArray();
    $array = array();

    foreach($nodes as $node)
    {
      if(isset($node[$ident]))
      {
        $array[(is_object($node[$ident])) ? $node[$ident]->toString() : $node[$ident]] = $node;
      }
      else
      {
        throw new TeamSpeak3_Adapter_ServerQuery_Exception("invalid parameter", 0x602);
      }
    }

    return $array;
  }

  /**
   * Returns an array containing the reply splitted in multiple rows and columns.
   *
   * @return array
   */
  public function toList()
  {
    $array = func_num_args() ? $this->toArray(1) : $this->toArray();

    if(count($array) == 1)
    {
      return array_shift($array);
    }

    return $array;
  }

  /**
   * Returns an array containing stdClass objects.
   *
   * @return array
   */
  public function toObjectArray(): array {
    $array = (func_num_args() > 1) ? $this->toArray(1) : $this->toArray();

    foreach ($array as $key => $element) {
        $array[$key] = (object) $element;
    }

    return $array;
  }

  /**
   * Returns the command used to get this reply.
   *
   * @return StringHelper
   */
  public function getCommandString()
  {
    return new StringHelper($this->cmd);
  }

  /**
   * Returns an array of events that occured before or during this reply.
   *
   * @return array
   */
  public function getNotifyEvents()
  {
    return $this->evt;
  }

  /**
   * Returns the value for a specified error property.
   *
   * @param  string $ident
   * @param  mixed  $default
   * @return mixed
   */
  public function getErrorProperty($ident, $default = null)
  {
    return (array_key_exists($ident, $this->err)) ? $this->err[$ident] : $default;
  }

  /**
   * Parses a ServerQuery error and throws a TeamSpeak3_Adapter_ServerQuery_Exception object if
   * there's an error.
   *
   * @param StringHelper|string $err
   * @return void
   *@throws ServerQueryException
   */
  protected function fetchError(StringHelper|string $err): void {
    $cells = $err->section(TeamSpeak3::SEPARATOR_CELL, 1, 3);

    foreach($cells->split(TeamSpeak3::SEPARATOR_CELL) as $pair)
    {
      list($ident, $value) = $pair->split(TeamSpeak3::SEPARATOR_PAIR);

      $this->err[$ident->toString()] = $value->isInt() ? $value->toInt() : $value->unescape();
    }

    SignalHelper::getInstance()->emit("notifyError", $this);

    if($this->getErrorProperty("id", 0x00) != 0x00 && $this->exp)
    {
      if($permid = $this->getErrorProperty("failed_permid"))
      {
        if($permsid = key($this->con->request("permget permid=" . $permid, FALSE)->toAssocArray("permsid")))
        {
          $suffix = " (failed on " . $permsid . ")";
        }
        else
        {
          $suffix = " (failed on " . $this->cmd->section(TeamSpeak3::SEPARATOR_CELL) . " " . $permid . "/0x" . strtoupper(dechex($permid)) . ")";
        }
      }
      elseif($details = $this->getErrorProperty("extra_msg"))
      {
        $suffix = " (" . trim($details) . ")";
      }
      else
      {
        $suffix = "";
      }
      
      throw new ServerQueryException($this->getErrorProperty("msg") . $suffix, $this->getErrorProperty("id"), $this->getErrorProperty("return_code"));
    }
  }

  /**
   * Parses a ServerQuery reply and creates a TeamSpeak3_Helper_String object.
   *
   * @param  array $rpl
   * @return void
   */
  protected function fetchReply(array $rpl): void {
    foreach($rpl as $key => $val)
    {
      if($val->startsWith(TeamSpeak3::TS3_MOTD_PREFIX) || $val->startsWith(TeamSpeak3::TEA_MOTD_PREFIX) || (defined("CUSTOM_MOTD_PREFIX") && $val->startsWith(CUSTOM_MOTD_PREFIX)))
      {
        unset($rpl[$key]);
      }
      elseif($val->startsWith(TeamSpeak3::EVENT))
      {
        $this->evt[] = new Event($rpl[$key], $this->con);
        unset($rpl[$key]);
      }
    }

    $this->rpl = new StringHelper(implode(TeamSpeak3::SEPARATOR_LIST, $rpl));
  }
}
