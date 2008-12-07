<?php

/**
 * PHP IRC Client library
 * Copyright (C) 2008 Dan Fuhry. All rights reserved.
 */

/**
 * Version number
 * @const string
 */

define('REQUEST_IRC_VERSION', '0.1');

/**
 * The base class for an IRC session.
 */

class Request_IRC
{
  
  /**
   * Hostname
   * @var string
   */
  
  private $host = '';
  
  /**
   * Port number
   * @var int
   */
  
  private $port = 6667;
  
  /**
   * The socket for the connection.
   * @var resource
   */
  
  public $sock = false;
  
  /**
   * Channel objects, associative array
   * @var array
   */
  
  public $channels = array();
  
  /**
   * The function called when a private message is received.
   * @var string
   */
  
  private $privmsg_handler = false;
  
  /**
   * The function called when a timeout is suspected.
   * @var string
   */
  
  private $timeout_warning_handler = false;
  
  /**
   * The function called when a timeout is confirmed.
   * @var string
   */
  
  private $timeout_error_handler = false;
  
  /**
   * Switch to track if quitted or not. Helps avoid quitting the connection twice thus causing write errors.
   * @var bool
   * @access private
   */
  
  protected $quitted = false;
  
  /**
   * The nickname we're connected as. Not modified once connected.
   * @var string
   */
  
  public $nick = '';
  
  /**
   * The username we're connected as. Not modified once connected.
   * @var string
   */
  
  public $user = '';
  
  /**
   * Constructor.
   * @param string Hostname
   * @param int Port number, defaults to 6667
   */
  
  public function __construct($host, $port = 6667)
  {
    // Check hostname
    if ( !preg_match('/^(([a-z0-9-]+\.)*?)([a-z0-9-]+)$/', $host) )
      die(__CLASS__ . ': Invalid hostname');
    $this->host = $host;
    
    // Check port
    if ( is_int($port) && $port >= 1 && $port <= 65535 )
      $this->port = $port;
    else
      die(__CLASS__ . ': Invalid port');
  }
  
  /**
   * Sets parameters and opens the connection.
   * @param string Nick
   * @param string User
   * @param string Real name
   * @param string NickServ password
   * @param int Flags, defaults to 0.
   */
  
  public function connect($nick, $username, $realname, $pass = false, $flags = 0)
  {
    // Init connection
    $this->sock = fsockopen($this->host, $this->port);
    if ( !$this->sock )
      throw new Exception('Could not make socket connection to host.');
    
    stream_set_timeout($this->sock, 1);
    
    // Send nick and username
    $this->put("NICK $nick\r\n");
    $this->put("USER $username 0 * :$realname\r\n");
    
    // wait for a mode +i or end of the motd
    while ( true )
    {
      $msg = $this->get();
      if ( empty($msg) )
        continue;
      if ( ( strstr($msg, 'MODE') && strstr($msg, '+i') ) || strstr(strtolower($msg), 'end of /motd') )
      {
        break;
      }
      if ( preg_match('/^PING :(.+?)$/', $msg, $match) )
      {
        $this->put("PONG :{$match[1]}\r\n");
      }
    }
    
    // identify to nickserv
    if ( $pass )
      $this->privmsg('NickServ', "IDENTIFY $pass");
    
    $this->nick = $nick;
    $this->user = $username;
    $this->quitted = false;
  }
  
  /**
   * Writes some data to the socket, abstracted for debugging purposes.
   * @param string Message to send, this should include a CRLF.
   */
  
  public function put($message)
  {
    if ( !$this->sock )
    {
      if ( defined('LIBIRC_DEBUG') )
        echo ">>> WRITE FAILED: $message";
      return false;
    }
    if ( defined('LIBIRC_DEBUG') )
      echo ">>> $message";
    fwrite($this->sock, $message);
  }
  
  /**
   * Reads from the socket...
   * @return string
   */
  
  public function get($timeout = 1)
  {
    if ( !$this->sock )
    {
      if ( defined('LIBIRC_DEBUG') )
        echo "<<< READ FAILED\n";
      return false;
    }
    if ( ($c = stream_select($r = array($this->sock), $w = null, $e = null, $timeout)) !== false )
    {
      if ( $c > 0 )
      {
        $out = fgets($this->sock, 4096);
        if ( defined('LIBIRC_DEBUG') )
          if ( !empty($out) )
            echo "<<< $out";
        return $out;
      }
    }
    return false;
  }
  
  /**
   * Sends a message to a nick or channel.
   * @param string Nick or channel
   * @param string Message
   */
  
  public function privmsg($nick, $message)
  {
    $message = str_replace("\r\n", "\n", $message);
    $message = explode("\n", $message);
    foreach ( $message as $line )
    {
      $line = $this->filter_message($line);
      $this->put("PRIVMSG $nick :$line\r\n");
    }
  }
  
  /**
  * Parse bold (<b>...</b>) tags and color tags in a text into IRC speak, and process /me commands. Colors are <cyan>...</cyan>, specify background with <fg:bg>...</fgcolor:bgcolor>. Valid colors are white, black, navy, green, red, maroon, purple, orange, yellow, lime, teal, aqua, cyan, blue, pink, grey, and silver
   * @param string Text to filter
   * @return string
   */
  
  function filter_message($text)
  {
    $text = preg_replace('#<b>(.*?)</b>#is', "\x02$1\x02", $text);
    if ( preg_match('#^/me #i', $text) )
    {
      $text = "\x01ACTION " . preg_replace('#^/me #i', '', $text) . "\x01";
    }
    $supportedcolors = array('white', 'black', 'navy', 'green', 'red', 'maroon', 'purple', 'orange', 'yellow', 'lime', 'teal', 'cyan', 'blue', 'pink', 'grey', 'silver');
    $colors = implode('|', $supportedcolors);
    $supportedcolors = array_flip($supportedcolors);
    preg_match_all("#<((?:$colors)(?::(?:$colors))?)>(.*?)</\\1>#is", $text, $matches);
    foreach ( $matches[0] as $i => $match )
    {
      preg_match("#<(?:($colors)(?::($colors))?)>#i", $match, $colordata);
      $fgcolor = $supportedcolors[$colordata[1]];
      $bgcolor = $colordata[1] ? $supportedcolors[$colordata[2]] : '';
      $fgcolor = ( $fgcolor < 10 ) ? "0$fgcolor" : "$fgcolor";
      if ( is_int($bgcolor) )
        $bgcolor = ( $bgcolor < 10 ) ? ",0$bgcolor" : ",$bgcolor";
      $text = str_replace($match, "\x03{$fgcolor}{$bgcolor}{$matches[2][$i]}\x03", $text);
    }
    
    return $text;
  }
  
  /**
   * The main event loop.
   */
  
  public function event_loop()
  {
    $timeout = 180;
    $timeout_warn = false;
    while ( true )
    {
      $data = $this->get($timeout);
      $data_trim = $data ? trim($data) : false;
      if ( empty($data_trim) )
      {
        if ( $timeout_warn )
        {
          // timeout confirmed :-/
          if ( $this->timeout_error_handler )
          {
            $result = @call_user_func($this->timeout_error_handler, $this);
            if ( $result == 'BREAK' )
              break;
          }
          $timeout = 180;
          $timeout_warn = false;
          continue;
        }
        // timeout suspected
        if ( $this->timeout_warning_handler )
        {
          $result = @call_user_func($this->timeout_warning_handler, $this);
          if ( $result == 'BREAK' )
            break;
        }
        // ping the server
        $this->put("PING :{$this->nick}\r\n");
        // set timeout lower to make reconnecting work as fast as possible
        $timeout = 10;
        $timeout_warn = true;
        continue;
      }
      $match = self::parse_message($data_trim);
      if ( preg_match('/^PING :(.+?)$/', $data_trim, $pmatch) )
      {
        $this->put("PONG :{$pmatch[1]}\r\n");
        eval(eb_fetch_hook('event_ping'));
      }
      else if ( preg_match('/^:((?:[a-z0-9-]+\.)*[a-z0-9-]+) PONG \\1 :' . preg_quote($this->nick) .'/', $data_trim) )
      {
        $timeout = 180;
        $timeout_warn = false;
      }
      else if ( $match )
      {
        // Received PRIVMSG or other mainstream action
        if ( $match['action'] == 'JOIN' || $match['action'] == 'PART' )
          $channel =& $match['message'];
        else
          $channel =& $match['target'];
          
        if ( !preg_match('/^[#!&\+]/', $channel) )
        {
          // Private message from user
          $result = $this->handle_privmsg($data);
          @stream_set_timeout($this->sock, 0xFFFFFFFE);
        }
        else if ( isset($this->channels[strtolower($channel)]) )
        {
          // Message into channel
          $chan =& $this->channels[strtolower($channel)];
          $func = $chan->get_handler();
          $result = @call_user_func($func, $data, $chan);
          @stream_set_timeout($this->sock, 0xFFFFFFFE);
        }
        if ( $result == 'BREAK' )
        {
          break;
        }
      }
    }
  }
  
  /**
   * Processor for when a private message is received.
   * @access private
   */
  
  private function handle_privmsg($message)
  {
    $message = self::parse_message($message);
    $message['message'] = preg_replace('/^(.+?):/', '', $message['message']);
    $ph = $this->privmsg_handler;
    if ( @function_exists($ph) )
      return @call_user_func($ph, $message);
  }
  
  /**
   * Changes the function called upon receipt of a private message.
   * @param string Function to call, will be passed a parsed message.
   */
  
  function set_privmsg_handler($func)
  {
    if ( !function_exists($func) )
      return false;
    $this->privmsg_handler = $func;
    return true;
  }
  
  /**
   * Changes the functions called when IRC connection timeouts occur.
   * @param string Function to call when a warning (no traffic within 3 minutes) occurs. If false, nothing will be called.
   * @param string Function to call if a ping timeout occurs. If false, nothing will be called.
   */
  
  function set_timeout_handlers($warn_func, $error_func)
  {
    $this->timeout_warning_handler = false;
    $this->timeout_error_handler = false;
    if ( function_exists($warn_func) )
    {
      $this->timeout_warning_handler = $warn_func;
    }
    if ( function_exists($error_func) )
    {
      $this->timeout_error_handler = $error_func;
    }
    return true;
  }
  
  /**
   * Parses a message line.
   * @param string Message text
   * @return array Associative with keys: nick, user, host, action, target, message
   */
   
  public static function parse_message($message)
  {
    // Indices:          12       3       4        5        67                         8
    $mc = preg_match('/^:(([^ ]+)!([^ ]+)@([^ ]+)) ([A-Z]+) (([#!&\+]*[A-z0-9_-]+) )?:?(.*?)$/', $message, $match);
    if ( !$mc )
    {
      return false;
    }
    // Indices: 0 1 2      3      4      5        6 7        8
    list(       , , $nick, $user, $host, $action, , $target, $message) = $match;
    return array(
        'nick' => $nick,
        'user' => $user,
        'host' => $host,
        'action' => $action,
        'target' => $target,
        'message' => trim($message)
      );
  }
  
  /**
   * Joins a channel, and returns a Request_IRC_Channel object.
   * @param string Channel name (remember # prefix)
   * @param string Event handler function, will be called with param 0 = socket output and param 1 = channel object
   * @return object
   */
  
  function join($channel, $handler)
  {
    $chan = new Request_IRC_Channel(strtolower($channel), $handler, $this);
    $this->channels[strtolower($channel)] = $chan;
    return $chan;
  }
  
  /**
   * Changes the current nick.
   * @param string New nick.
   * @param string Password to authenticate to NickServ if needed.
   */
  
  public function change_nick($nick, $pass = false)
  {
    $this->put("NICK $nick\r\n");
    $this->nick = $nick;
    if ( $pass )
    {
      while ( $data = $this->get(3) )
      {
        if ( strstr($data, 'NickServ') )
        {
          $this->privmsg('NickServ', "IDENTIFY $pass");
          break;
        }
      }
    }
  }
  
  /**
   * Closes the connection and quits.
   * @param string Optional part message
   */
  
  public function close($partmsg = false)
  {
    if ( $this->quitted )
      return true;
    
    $this->quitted = true;
    // Part all channels
    if ( !$partmsg )
      $partmsg = 'Closing connection (no reason given)';
    
    foreach ( $this->channels as $channel )
    {
      $channel->part($partmsg);
    }
    
    $this->put("QUIT\r\n");
    
    while ( $msg = $this->get(1) )
    {
      // Do nothing.
    }
    
    fclose($this->sock);
  }
  
}

/**
 * Wrapper for channels.
 */

class Request_IRC_Channel extends Request_IRC
{
  
  /**
   * The name of the channel
   * @var string
   */
  
  private $channel_name = '';
  
  /**
   * The event handler function.
   * @var string
   */
  
  private $handler = '';
  
  /**
   * The parent connection.
   * @var object
   */
  
  public $parent = false;
  
  /**
   * Whether the channel has been parted or not, used to kill the destructor.
   * @var bool
   */
  
  protected $parted = false;
  
  /**
   * Constructor.
   * @param string Channel name
   * @param string Handler function
   * @param object IRC connection (Request_IRC object)
   */
  
  function __construct($channel, $handler, $parent)
  {
    $this->parent = $parent;
    $this->parent->put("JOIN $channel\r\n");
    // stream_set_timeout($this->parent->sock, 3);
    // while ( $msg = $this->parent->get() )
    // {
    //   // Do nothing
    // }
    $this->channel_name = $channel;
    $this->handler = $handler;
    eval(eb_fetch_hook('event_self_join'));
  }
  
  /**
   * Returns the channel name
   * @return string
   */
  
  function get_channel_name()
  {
    return $this->channel_name;
  }
  
  /**
   * Returns the handler function
   * @return string
   */
  
  function get_handler()
  {
    return $this->handler;
  }
  
  /**
   * Sends a message.
   * @param string message
   * @param bool If true, will fire a message event when the message is sent.
   */
  
  function msg($msg, $fire_event = false)
  {
    $this->parent->privmsg($this->channel_name, $msg);
    if ( $fire_event )
    {
      $func = $this->get_handler();
      // format: :nick!user@host PRIVMSG #channel :msg.
      $lines = explode("\n", $msg);
      foreach ( $lines as $line )
      {
        $data = ":{$this->parent->nick}!{$this->parent->user}@localhost PRIVMSG {$this->channel_name} :$line";
        $result = @call_user_func($func, $data, $this);
        stream_set_timeout($this->parent->sock, 0xFFFFFFFE);
      }
    }
  }
  
  /**
   * Destructor, automatically parts the channel.
   */
  
  function __destruct()
  {
    if ( !$this->parted )
      $this->part('IRC bot powered by PHP/' . PHP_VERSION . ' libirc/' . REQUEST_IRC_VERSION);
  }
  
  /**
   * Parts the channel.
   * @param string Optional message
   */
  
  function part($msg = '')
  {
    $this->parent->put("PART {$this->channel_name} :$msg\r\n");
    $this->parted = true;
    unset($this->parent->channels[$this->channel_name]);
  }
  
}

?>
