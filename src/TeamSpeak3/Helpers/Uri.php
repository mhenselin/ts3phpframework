<?php

namespace TeamSpeak3\Helpers;

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
 * @class TeamSpeak3_Helper_Uri
 * @brief Helpers class for URI handling.
 */
class Uri
{
  /**
   * Stores the URI scheme.
   *
   * @var StringHelper
   */
  protected $scheme = null;

  /**
   * Stores the URI username
   *
   * @var StringHelper
   */
  protected $user = null;

  /**
   * Stores the URI password.
   *
   * @var StringHelper
   */
  protected $pass = null;

  /**
   * Stores the URI host.
   *
   * @var StringHelper
   */
  protected $host = null;

  /**
   * Stores the URI port.
   *
   * @var StringHelper
   */
  protected $port = null;

  /**
   * Stores the URI path.
   *
   * @var StringHelper
   */
  protected $path = null;

  /**
   * Stores the URI query string.
   *
   * @var StringHelper
   */
  protected $query = null;

  /**
   * Stores the URI fragment string.
   *
   * @var StringHelper
   */
  protected $fragment = null;

  /**
   * Stores grammar rules for validation via regex.
   *
   * @var array
   */
  protected $regex = array();

  /**
   * The TeamSpeak3_Helper_Uri constructor.
   *
   * @param  StringHelper $uri
   * @return Uri
   *@throws TeamSpeak3_Helper_Exception
   */
  public function __construct($uri)
  {
    $uri = explode(":", (string) $uri, 2);

    $this->scheme = strtolower($uri[0]);
    $uriString = $uri[1] ?? "";

    if(!ctype_alnum($this->scheme))
    {
      throw new HelpersException("invalid URI scheme '" . $this->scheme . "' supplied");
    }

    /* grammar rules for validation */
    $this->regex["alphanum"] = "[^\W_]";
    $this->regex["escaped"] = "(?:%[\da-fA-F]{2})";
    $this->regex["mark"] = "[-_.!~*'()\[\]]";
    $this->regex["reserved"] = "[;\/?:@&=+$,]";
    $this->regex["unreserved"] = "(?:" . $this->regex["alphanum"] . "|" . $this->regex["mark"] . ")";
    $this->regex["segment"] = "(?:(?:" . $this->regex["unreserved"] . "|" . $this->regex["escaped"] . "|[:@&=+$,;])*)";
    $this->regex["path"] = "(?:\/" . $this->regex["segment"] . "?)+";
    $this->regex["uric"] = "(?:" . $this->regex["reserved"] . "|" . $this->regex["unreserved"] . "|" . $this->regex["escaped"] . ")";

    if(strlen($uriString) > 0)
    {
      $this->parseUri($uriString);
    }

    if(!$this->isValid())
    {
      throw new TeamSpeak3_Helper_Exception("invalid URI supplied");
    }
  }

  /**
   * Parses the scheme-specific portion of the URI and place its parts into instance variables.
   *
   * @throws TeamSpeak3_Helper_Exception
   * @return void
   */
  protected function parseUri($uriString = '')
  {
    $status = @preg_match("~^((//)([^/?#]*))([^?#]*)(\?([^#]*))?(#(.*))?$~", $uriString, $matches);

    if($status === FALSE)
    {
      throw new TeamSpeak3_Helper_Exception("URI scheme-specific decomposition failed");
    }

    if(!$status) return;

    $this->path = (isset($matches[4])) ? $matches[4] : '';
    $this->query = (isset($matches[6])) ? $matches[6] : '';
    $this->fragment = (isset($matches[8])) ? $matches[8] : '';

    $status = @preg_match("~^(([^:@]*)(:([^@]*))?@)?((?(?=[[])[[][^]]+[]]|[^:]+))(:(.*))?$~", (isset($matches[3])) ? $matches[3] : "", $matches);

    if($status === FALSE)
    {
      throw new TeamSpeak3_Helper_Exception("URI scheme-specific authority decomposition failed");
    }

    if(!$status) return;

    $this->user = isset($matches[2]) ? $matches[2] : "";
    $this->pass = isset($matches[4]) ? $matches[4] : "";
    $this->host = isset($matches[5]) === TRUE ? preg_replace('~^\[([^]]+)\]$~', '\1', $matches[5]) : "";
    $this->port = isset($matches[7]) ? $matches[7] : "";
  }

  /**
   * Validate the current URI from the instance variables.
   *
   * @return boolean
   */
  public function isValid()
  {
    return ($this->checkUser() && $this->checkPass() && $this->checkHost() && $this->checkPort() && $this->checkPath() && $this->checkQuery() && $this->checkFragment());
  }

  /**
   * Returns TRUE if a given URI is valid.
   *
   * @param  StringHelper $uri
   * @return boolean
   */
  public static function check($uri)
  {
    try
    {
      $uri = new self(strval($uri));
    }
    catch(HelpersException $e)
    {
      return FALSE;
    }

    return $uri->valid();
  }

  /**
   * Returns TRUE if the URI has a scheme.
   *
   * @return boolean
   */
  public function hasScheme()
  {
    return strlen($this->scheme) ? TRUE : FALSE;
  }

  /**
   * Returns the scheme.
   *
   * @param  mixed default
   * @return TeamSpeak3_Helper_String
   */
  public function getScheme($default = null)
  {
    return ($this->hasScheme()) ? new TeamSpeak3_Helper_String($this->scheme) : $default;
  }

  /**
   * Returns TRUE if the username is valid.
   *
   * @param  StringHelper $username
   * @return boolean
   *@throws TeamSpeak3_Helper_Exception
   */
  public function checkUser($username = null)
  {
    if($username === null)
    {
      $username = $this->user;
    }

    if(strlen($username) == 0)
    {
      return TRUE;
    }

    $pattern = "/^(" . $this->regex["alphanum"]  . "|" . $this->regex["mark"] . "|" . $this->regex["escaped"] . "|[;:&=+$,])+$/";
    $status = @preg_match($pattern, $username);

    if($status === FALSE)
    {
      throw new TeamSpeak3_Helper_Exception("URI username validation failed");
    }

    return ($status == 1);
  }

  /**
   * Returns TRUE if the URI has a username.
   *
   * @return boolean
   */
  public function hasUser()
  {
    return strlen($this->user) ? TRUE : FALSE;
  }

  /**
   * Returns the username.
   *
   * @param  mixed default
   * @return TeamSpeak3_Helper_String
   */
  public function getUser($default = null)
  {
    return ($this->hasUser()) ? new TeamSpeak3_Helper_String(urldecode($this->user)) : $default;
  }

  /**
   * Returns TRUE if the password is valid.
   *
   * @param  StringHelper $password
   * @return boolean
   *@throws TeamSpeak3_Helper_Exception
   */
  public function checkPass($password = null)
  {
    if($password === null) {
      $password = $this->pass;
    }

    if(strlen($password) == 0)
    {
      return TRUE;
    }

    $pattern = "/^(" . $this->regex["alphanum"]  . "|" . $this->regex["mark"] . "|" . $this->regex["escaped"] . "|[;:&=+$,])+$/";
    $status = @preg_match($pattern, $password);

    if($status === FALSE)
    {
      throw new TeamSpeak3_Helper_Exception("URI password validation failed");
    }

    return ($status == 1);
  }

  /**
   * Returns TRUE if the URI has a password.
   *
   * @return boolean
   */
  public function hasPass()
  {
    return strlen($this->pass) ? TRUE : FALSE;
  }

  /**
   * Returns the password.
   *
   * @param  mixed default
   * @return TeamSpeak3_Helper_String
   */
  public function getPass($default = null)
  {
    return ($this->hasPass()) ? new TeamSpeak3_Helper_String(urldecode($this->pass)) : $default;
  }

  /**
   * Returns TRUE if the host is valid.
   *
   * @param StringHelper $host
   *
   * @return boolean
   *@todo: Implement check for host URI segment
   *
   */
  public function checkHost($host = null)
  {
    if($host === null)
    {
      $host = $this->host;
    }

    return TRUE;
  }

  /**
   * Returns TRUE if the URI has a host.
   *
   * @return boolean
   */
  public function hasHost()
  {
    return strlen($this->host) ? TRUE : FALSE;
  }

  /**
   * Returns the host.
   *
   * @param  mixed default
   * @return TeamSpeak3_Helper_String
   */
  public function getHost($default = null)
  {
    return ($this->hasHost()) ? new TeamSpeak3_Helper_String(rawurldecode($this->host)) : $default;
  }

  /**
   * Returns TRUE if the port is valid.
   *
   * @todo: Implement check for port URI segment
   *
   * @param  integer $port
   *
   * @return boolean
   */
  public function checkPort($port = null)
  {
    if($port === null)
    {
      $port = $this->port;
    }

    return TRUE;
  }

  /**
   * Returns TRUE if the URI has a port.
   *
   * @return boolean
   */
  public function hasPort()
  {
    return strlen($this->port) ? TRUE : FALSE;
  }

  /**
   * Returns the port.
   *
   * @param  mixed default
   * @return integer
   */
  public function getPort($default = null)
  {
    return ($this->hasPort()) ? intval($this->port) : $default;
  }

  /**
   * Returns TRUE if the path is valid.
   *
   * @param  StringHelper $path
   * @return boolean
   *@throws TeamSpeak3_Helper_Exception
   */
  public function checkPath($path = null)
  {
    if($path === null)
    {
      $path = $this->path;
    }

    if(strlen($path) == 0)
    {
      return TRUE;
    }

    $pattern = "/^" . $this->regex["path"] . "$/";
    $status = @preg_match($pattern, $path);

    if($status === FALSE)
    {
      throw new TeamSpeak3_Helper_Exception("URI path validation failed");
    }

    return ($status == 1);
  }

  /**
   * Returns TRUE if the URI has a path.
   *
   * @return boolean
   */
  public function hasPath()
  {
    return strlen($this->path) ? TRUE : FALSE;
  }

  /**
   * Returns the path.
   *
   * @param  mixed default
   * @return TeamSpeak3_Helper_String
   */
  public function getPath($default = null)
  {
    return ($this->hasPath()) ? new TeamSpeak3_Helper_String(rawurldecode($this->path)) : $default;
  }

  /**
   * Returns TRUE if the query string is valid.
   *
   * @param  StringHelper $query
   * @return boolean
   *@throws TeamSpeak3_Helper_Exception
   */
  public function checkQuery($query = null)
  {
    if($query === null)
    {
      $query = $this->query;
    }

    if(strlen($query) == 0)
    {
      return TRUE;
    }

    $pattern = "/^" . $this->regex["uric"] . "*$/";
    $status = @preg_match($pattern, $query);

    if($status === FALSE)
    {
      throw new TeamSpeak3_Helper_Exception("URI query string validation failed");
    }

    return ($status == 1);
  }

  /**
   * Returns TRUE if the URI has a query string.
   *
   * @return boolean
   */
  public function hasQuery()
  {
    return strlen($this->query) ? TRUE : FALSE;
  }

  /**
   * Returns an array containing the query string elements.
   *
   * @param  mixed $default
   * @return array
   */
  public function getQuery($default = array())
  {
    if(!$this->hasQuery())
    {
      return $default;
    }

    parse_str(rawurldecode($this->query), $queryArray);

    return $queryArray;
  }

  /**
   * Returns TRUE if the URI has a query variable.
   *
   * @return boolean
   */
  public function hasQueryVar($key)
  {
    if(!$this->hasQuery()) return FALSE;

    parse_str($this->query, $queryArray);

    return array_key_exists($key, $queryArray) ? TRUE : FALSE;
  }

  /**
   * Returns a single variable from the query string.
   *
   * @param  StringHelper $key
   * @param  mixed  $default
   * @return mixed
   */
  public function getQueryVar($key, $default = null)
  {
    if(!$this->hasQuery()) return $default;

    parse_str(rawurldecode($this->query), $queryArray);

    if(array_key_exists($key, $queryArray))
    {
      $val = $queryArray[$key];

      if(ctype_digit($val))
      {
        return intval($val);
      }
      elseif(is_string($val))
      {
        return new TeamSpeak3_Helper_String($val);
      }
      else
      {
        return $val;
      }
    }

    return $default;
  }

  /**
   * Returns TRUE if the fragment string is valid.
   *
   * @param  StringHelper $fragment
   * @return boolean
   *@throws TeamSpeak3_Helper_Exception
   */
  public function checkFragment($fragment = null)
  {
    if($fragment === null)
    {
      $fragment = $this->fragment;
    }

    if(strlen($fragment) == 0)
    {
      return TRUE;
    }

    $pattern = "/^" . $this->regex["uric"] . "*$/";
    $status = @preg_match($pattern, $fragment);

    if($status === FALSE)
    {
      throw new TeamSpeak3_Helper_Exception("URI fragment validation failed");
    }

    return ($status == 1);
  }

  /**
   * Returns TRUE if the URI has a fragment string.
   *
   * @return boolean
   */
  public function hasFragment()
  {
    return strlen($this->fragment) ? TRUE : FALSE;
  }

  /**
   * Returns the fragment.
   *
   * @param  mixed default
   * @return TeamSpeak3_Helper_String
   */
  public function getFragment($default = null)
  {
    return ($this->hasFragment()) ? new TeamSpeak3_Helper_String(rawurldecode($this->fragment)) : $default;
  }

  /**
   * Returns a specified instance parameter from the $_REQUEST array.
   *
   * @param  StringHelper $key
   * @param  mixed  $default
   * @return mixed
   */
  public static function getUserParam($key, $default = null)
  {
    return (array_key_exists($key, $_REQUEST) && !empty($_REQUEST[$key])) ? self::stripslashesRecursive($_REQUEST[$key]) : $default;
  }

  /**
   * Returns a specified environment parameter from the $_SERVER array.
   *
   * @param  StringHelper $key
   * @param  mixed  $default
   * @return mixed
   */
  public static function getHostParam($key, $default = null)
  {
    return (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) ? $_SERVER[$key] : $default;
  }

  /**
   * Returns a specified session parameter from the $_SESSION array.
   *
   * @param  StringHelper $key
   * @param  mixed  $default
   * @return mixed
   */
  public static function getSessParam($key, $default = null)
  {
    return (array_key_exists($key, $_SESSION) && !empty($_SESSION[$key])) ? $_SESSION[$key] : $default;
  }

  /**
   * Returns an array containing the three main parts of a FQDN (Fully Qualified Domain Name), including the
   * top-level domain, the second-level domains or hostname and the third-level domain.
   *
   * @param  StringHelper $hostname
   * @return array
   */
  public static function getFQDNParts($hostname)
  {
    if(!preg_match("/^([a-z0-9][a-z0-9-]{0,62}\.)*([a-z0-9][a-z0-9-]{0,62}\.)+([a-z]{2,6})$/i", $hostname, $matches))
    {
      return array();
    }

    $parts["tld"] = $matches[3];
    $parts["2nd"] = $matches[2];
    $parts["3rd"] = $matches[1];

    return $parts;
  }

  /**
   * Returns the applications host address.
   *
   * @return TeamSpeak3_Helper_String
   */
  public static function getHostUri()
  {
    $sheme = (self::getHostParam("HTTPS") == "on") ? "https" : "http";

    $serverName = new TeamSpeak3_Helper_String(self::getHostParam("HTTP_HOST"));
    $serverPort = self::getHostParam("SERVER_PORT");
    $serverPort = ($serverPort != 80 && $serverPort != 443) ? ":" . $serverPort : "";

    if($serverName->endsWith($serverPort))
    {
      $serverName = $serverName->replace($serverPort, "");
    }

    return new TeamSpeak3_Helper_String($sheme . "://" . $serverName . $serverPort);
  }

  /**
   * Returns the applications base address.
   *
   * @return StringHelper
   */
  public static function getBaseUri()
  {
    $scriptPath = new TeamSpeak3_Helper_String(dirname(self::getHostParam("SCRIPT_NAME")));

    return self::getHostUri()->append(($scriptPath == DIRECTORY_SEPARATOR ? "" : $scriptPath) . "/");
  }

  /**
   * Strips slashes from each element of an array using stripslashes().
   *
   * @param  mixed $var
   * @return mixed
   */
  protected static function stripslashesRecursive($var)
  {
    if(!is_array($var))
    {
      return stripslashes(strval($var));
    }

    foreach($var as $key => $val)
    {
      $var[$key] = (is_array($val)) ? stripslashesRecursive($val) : stripslashes(strval($val));
    }

    return $var;
  }
}
