<?php

/**
 * Class for drawing progress bars in a vt100 console.
 * @author Dan Fuhry
 * @license Public domain
 */

class ProgressBar
{
  /**
   * Shell escape character.
   * @const string
   */
  
  const SHELL_ESCAPE = "\x1B";
  
  /**
   * Carriage return (0x0D)
   * @const string
   */
  
  const CARRIAGE_RETURN = "\r";
  
  /**
   * Colors of the foreground, background, foreground text, and background text, respectively
   * @var int
   * @var int
   * @var int
   * @var int
   */
  
  private $color_bar, $color_empty, $color_text, $color_emptytext;
  
  /**
   * Text to the left of the bar.
   * @var string
   */
  
  private $bar_left;
  
  /**
   * Text to the right of the bar.
   * @var string
   */
  
  private $bar_right;
  
  /**
   * Text in the middle of the bar.
   * @var string
   */
  
  private $bar_text;
  
  /**
   * The current location of the bar in %.
   * @var int
   */
  
  private $bar_pos = 0;
  
  /**
   * Position where the text should start.
   * @var int
   */
  
  private $text_pos = 0;
  
  /**
   * Width of the current terminal.
   * @var int
   */
  
  private $term_width = 80;
  
  /**
   * Width of the actual bar.
   * @var int
   */
  
  private $bar_width = 0;
  
  /**
   * State of the bar's color. Used to avoid echoing tons of color codes.
   * @var int
   */
  
  private $color_state = 0;
  
  /**
   * Color state constants
   * @const int
   * @const int
   * @const int
   * @const int
   * @const int
   */
  
  const COLOR_STATE_RESET = 0;
  const COLOR_STATE_FULL_HIDE = 1;
  const COLOR_STATE_FULL_SHOW = 2;
  const COLOR_STATE_EMPTY_HIDE = 3;
  const COLOR_STATE_EMPTY_SHOW = 4;
  
  /**
   * Constructor. All parameters are optional. Color choices are defined in color_to_code.
   * @param string $bar_left
   * @param string $bar_right
   * @param string $bar_text
   * @param string $color_bar
   * @param string $color_empty
   * @param string $color_text
   * @param string $color_emptytext
   */
  
  public function __construct($bar_left = '[', $bar_right = ']', $bar_text = '', $color_bar = 'red', $color_empty = 'black', $color_text = 'white', $color_emptytext = 'cyan')
  {
    $this->bar_left = $bar_left;
    $this->bar_right = $bar_right;
    $this->color_bar = $this->color_to_code($color_bar);
    $this->color_empty = $this->color_to_code($color_empty);
    $this->color_text = $this->color_to_code($color_text);
    $this->color_emptytext = $this->color_to_code($color_emptytext);
    
    if ( isset($_SERVER['COLUMNS']) )
    {
      $this->term_width = intval($_SERVER['COLUMNS']);
    }
    $this->bar_width = $this->term_width - strlen($this->bar_left) - strlen($this->bar_right);
    
    $this->update_text_quiet($bar_text);
  }
  
  /**
   * Updates the text on the progress bar and recalculates the position without redrawing.
   * @param string Text in the bar. If omitted, blanked.
   */
  
  public function update_text_quiet($bar_text = '')
  {
    $this->bar_text = strval($bar_text);
    
    if ( !empty($this->bar_text) )
    {
      $this->text_pos = round(( $this->bar_width / 2 ) - ( strlen($this->bar_text) / 2 ));
    }
  }
  
  /**
   * Updates the text on the progress bar, recalculates the position, and redraws.
   * @param string Text in the bar. If omitted, blanked.
   */
  
  function update_text($bar_text = '')
  {
    $this->update_text_quiet($bar_text);
    $this->set($this->bar_pos);
  }
  
  /**
   * Starts output of the bar.
   */
  
  function start()
  {
    echo self::CARRIAGE_RETURN;
    echo $this->bar_left;
  }
  
  /**
   * Closes the bar.
   */
  
  function end()
  {
    $this->set($this->bar_pos, $this->bar_width);
    echo "\n";
  }
  
  /**
   * Sets the position of the bar.
   * @param int Position in %. If a second parameter is set, this is treated as a numerator with the second parameter being the denominator and that is used to calculate position.
   * @param int Optional. Total number of units to allow fraction usage instead of percentage.
   */
  
  function set($pos, $max = 100)
  {
    // if our pos is higher than 100%, reduce it
    if ( $pos > $max )
      $pos = $max;
    
    // arithmetic one-liner
    // this is where we should stop showing the "full" color and instead use "empty"
    $bar_pos = round($this->bar_width * ( $pos / $max ));
    $this->bar_pos = 100 * ( $pos / $max );
    
    // reset the cursor
    echo self::CARRIAGE_RETURN . $this->bar_left;
    
    // print everything out
    for ( $i = 0; $i < $this->bar_width; $i++ )
    {
      $char = ' ';
      $hide = true;
      if ( !empty($this->bar_text) )
      {
        // we have some text to display in the middle; see where we are.
        $show_text = ( $i >= $this->text_pos && $i < ( $this->text_pos + strlen($this->bar_text) ) );
        if ( $show_text )
        {
          $char = substr($this->bar_text, $i - $this->text_pos, 1);
          if ( strlen($char) < 1 )
            $char = ' ';
          else
            $hide = false;
        }
      }
      // determine color
      if ( $i > $bar_pos )
      {
        $hide ? $this->set_color_empty_hide() : $this->set_color_empty_show();
      }
      else
      {
        $hide ? $this->set_color_full_hide() : $this->set_color_full_show();
      }
      echo $char;
    }
    $this->set_color_reset();
    echo $this->bar_right;
  }
  
  #
  # PRIVATE METHODS
  #
  
  function set_color_full_hide()
  {
    if ( $this->color_state == self::COLOR_STATE_FULL_HIDE )
      return;
    $this->color_state = self::COLOR_STATE_FULL_HIDE;
    
    $fgcolor = 30 + $this->color_bar;
    $bgcolor = $fgcolor + 10;
    echo self::SHELL_ESCAPE . "[0;{$fgcolor};{$bgcolor};8m";
  }
  
  function set_color_full_show()
  {
    if ( $this->color_state == self::COLOR_STATE_FULL_SHOW )
      return;
    $this->color_state = self::COLOR_STATE_FULL_SHOW;
    
    $fgcolor = 30 + $this->color_text;
    $bgcolor = 40 + $this->color_bar;
    echo self::SHELL_ESCAPE . "[0;1;{$fgcolor};{$bgcolor}m";
  }
  
  function set_color_empty_hide()
  {
    if ( $this->color_state == self::COLOR_STATE_EMPTY_HIDE )
      return;
    $this->color_state = self::COLOR_STATE_EMPTY_HIDE;
    
    $fgcolor = 30 + $this->color_empty;
    $bgcolor = $fgcolor + 10;
    echo self::SHELL_ESCAPE . "[0;{$fgcolor};{$bgcolor};8m";
  }
  
  function set_color_empty_show()
  {
    if ( $this->color_state == self::COLOR_STATE_EMPTY_SHOW )
      return;
    $this->color_state = self::COLOR_STATE_EMPTY_SHOW;
    
    $fgcolor = 30 + $this->color_emptytext;
    $bgcolor = 40 + $this->color_empty;
    echo self::SHELL_ESCAPE . "[0;1;{$fgcolor};{$bgcolor}m";
  }
  
  function set_color_reset()
  {
    if ( $this->color_state == self::COLOR_STATE_RESET )
      return;
    $this->color_state = self::COLOR_STATE_RESET;
    
    echo self::SHELL_ESCAPE . "[0m";
  }
  
  /**
   * Converts a color name to an ASCII color code. Valid color names are black, red, green, yellow, blue, magenta, cyan, and white.
   * @param string Color name
   * @return int
   */
  
  private function color_to_code($color)
  {
    static $colors = array(
      'black' => 0,
      'red' => 1,
      'green' => 2,
      'yellow' => 3,
      'blue' => 4,
      'magenta' => 5,
      'cyan' => 6,
      'white' => 7
    );
    return ( isset($colors[$color]) ) ? $colors[$color] : $colors['white'];
  }
}
