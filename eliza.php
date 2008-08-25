<?php

/**
 * Implementation of ELIZA in PHP. Ported from Javascript by Dan Fuhry
 * Chat Bot by George Dunlop, www.peccavi.com
 * May be used/modified if credit line is retained
 * @author George Dunlop <http://www.peccavi.com/>
 * @author Dan Fuhry <dan@enanocms.org>
 * @copyright (c) 1997-2008 George Dunlop. All rights reserved, portions copyright (C) 2008 Dan Fuhry.
 */

class Psychotherapist
{
  private $maxKey = 36;
  private $keyNotFound = 0;
  private $keyword = array();
  private $maxresponses = 116;
  private $response = array();
  
  private $maxConj = 19;
  private $max2ndConj = 7;
  
  private $conj1 = Array();
  private $conj2 = Array();
  private $conj3 = Array();
  private $conj4 = Array();
  
  private $punct = Array(".", ",", "!", "?", ":", ";", "&", '"', "@", "#", "(", ")" );
  
  /**
   * Constructor.
   */
  
  public function __construct()
  {
    $this->keyNotFound = $this->maxKey - 1;
    $this->keyword = $this->create_array($this->maxKey);
    $this->response = $this->create_array($this->maxresponses);
    $this->conj1 = $this->create_array($this->maxConj);
    $this->conj2 = $this->create_array($this->maxConj);
    $this->conj3 = $this->create_array($this->max2ndConj);
    $this->conj4 = $this->create_array($this->max2ndConj);
    
    $this->table_setup();
  }
  
  /**
   * Replacement for str_replace that provides more options.
   * if type == 0 straight string replacement
   * if type == 1 assumes padded strings and replaces whole words only
   * if type == 2 non case sensitive assumes padded strings to compare whole word only
   * if type == 3 non case sensitive straight string replacement
   * @param string Haystack
   * @param string Needle
   * @param string Replacement
   * @param int Mode - defaults to 0
   */

  private function replaceStr($strng, $substr1, $substr2, $type = 0)
  {
    if ( $type == 0 )
    {  
      return str_replace($substr1, $substr2, $strng);
    }
    else if ( $type == 1 )
    {
      return str_replace(" $substr1 ", " $substr2 ", $strng);
    }
    else if ( $type == 2 || $type == 3 )
    {
      if ( $type == 2 )
      {
        $substr1 = " $substr1 ";
        $substr2 = " $substr2 ";
      }
      return preg_replace('/' . preg_quote($substr1) . '/i', $substr2, $strng);
    }
    else
    {
      throw new Exception("Invalid parameter");
    }
  }
  
  /**
   * Function to pad a string. head, tail & punctuation
   * @param string
   * @return string
   */
  
  private function padString($strng)
  {
    $punct =& $this->punct;
    
    $aString = " " . $strng . " ";
    for ( $i = 0; $i < count($punct); $i++ )
    {
      $aString = $this->replaceStr( $aString, $punct[$i], " " . $punct[$i] . " ", 0 );
    }
    return $aString;
  }
  
  /**
   * Function to strip padding
   */
  
  private function unpadString($strng)
  {
    $punct =& $this->punct;
    
    $aString = $strng;
    $aString = $this->replaceStr( $aString, "  ", " ", 0 );         // compress spaces
    
    $aString = trim($aString, ' ');
    
    for ( $i = 0; $i < count($punct); $i++ )
    {
      $aString = $this->replaceStr( $aString, " " . $punct[$i], $punct[$i], 0 );
    }
    return $aString;
  }
  
  /**
   * Dress Input formatting i.e leading & trailing spaces and tail punctuation
   * @param string
   * @return string
   */

  function strTrim($strng)
  {
    static $ht = 0;
    
    if ( $ht == 0 )
    {
      $loc = 0;
    }                                    // head clip
    else
    {
      $loc = strlen($strng) - 1;
    }                        // tail clip  ht = 1 
    if ( substr($strng, $loc, 1) == " " )
    {
      $aString = substr($strng, - ( $ht - 1 ), strlen($strng) - $ht);
      $aString = $this->strTrim($aString);
    }
    else
    {
      $flg = false;
      for ( $i = 0; $i <= 5; $i++ )
      {
        $flg = $flg || ( substr($strng, $loc, 1) == $this->punct[$i]);
      }
      if ( $flg )
      {    
        $aString = substr($strng, - ( $ht - 1 ), strlen($strng) - $ht);
      }
      else
      {
        $aString = $strng;
      }
      if ( $aString != $strng )
      {
        $aString = $this->strTrim($aString);
      }
    }
    if ( $ht == 0 )
    {
      $ht = 1;
      $aString = $this->strTrim($aString);
    } 
    else
    {
      $ht = 0;
    }        
    return $aString;
  }
  
  /**
   * adjust pronouns and verbs & such
   * @param string
   * @return string
   */
  
  private function conjugate($sStrg)
  {
    $sString = $sStrg;
    for ( $i = 0; $i < $this->maxConj; $i++ )
    {            // decompose
      $sString = $this->replaceStr( $sString, $this->conj1[$i], "#@&" . $i, 2 );
    }
    for( $i = 0; $i < $this->maxConj; $i++ )
    {            // recompose
      $sString = $this->replaceStr( $sString, "#@&" . $i, $this->conj2[$i], 2 );
    }
    // post process the resulting string
    for( $i = 0; $i < $this->max2ndConj; $i++ )
    {            // decompose
      $sString = $this->replaceStr( $sString, $this->conj3[$i], "#@&" . $i, 2 );
    }
    for( $i = 0; $i < $this->max2ndConj; $i++ )
    {            // recompose
      $sString = $this->replaceStr( $sString, "#@&" . $i, $this->conj4[$i], 2 );
    }
    return $sString;
  }
  
  /**
   * Build our response string
   * get a random choice of response based on the key
   * Then structure the response
   * @param string
   * @param int Key index
   * @return string
   */
  
  function phrase( $sString, $keyidx )
  {
    $idxmin  = $this->keyword[$keyidx]->idx;
    $idrange = $this->keyword[$keyidx]->end - $idxmin + 1;
    while ( $pass < 5 )
    {
      $choice = $this->keyword[$keyidx]->idx + mt_rand(0, $idrange);
      if ( $choice == $this->keyword[$keyidx]->last )
      { 
        $pass++;
        continue;
      }
      break;
    }
    $this->keyword[$keyidx]->last = $choice;
    $rTemp = $this->response[$choice];
    $tempt = substr($rTemp, strlen($rTemp) - 1, 1);
    if ( ( $tempt == "*" ) || ( $tempt == "@" ) )
    {
      $sTemp = $this->padString($sString);
      $wTemp = strtoupper($sTemp);
      $strpstr = intval(strpos($wTemp, " {$this->keyword[$keyidx]->key} "));
      
      $strpstr += strlen($this->keyword[$keyidx]->key) + 1;
      $thisstr = $this->conjugate( substr($sTemp, $strpstr, strlen($sTemp)) );
      $thisstr = $this->strTrim( $this->unpadString($thisstr) );
      if( $tempt == "*" )
      {
        $sTemp = $this->replaceStr( $rTemp, "<*", " " . $thisstr . "?", 0 );
      }
      else
      {
        $sTemp = $this->replaceStr( $rTemp, "<@", " " . $thisstr . ".", 0 );
      }
    }
    else
    {
      $sTemp = $rTemp;
    }
    return $sTemp;
  }
  
  /**
   * returns array index of first key found
   * @param string
   */

  private function testkey($wString)
  {
    for ( $keyid = 0; $keyid < count($this->keyword); $keyid++ )
    {
      if ( strpos($wString, " {$this->keyword[$keyid]->key} ") !== false )
      { 
        return $keyid;
      }
    }
    return false;
  }
  
  /**
   * 
   */
  
  private function findkey($wString)
  { 
    $keyid = $this->testkey($wString);
    if( !$keyid )
    {
      $keyid = $this->keyNotFound;
    }
    return $keyid;
  }
  
  /**
   * Process a line from the user.
   * @param string User input
   * @return string AI output
   */
  
  function listen($User)
  {
    static $wTopic = "";                                            // Last worthy responce
    static $sTopic = "";                                            // Last worthy responce
    static $greet = false;
    static $wPrevious = "";                                    // so we can check for repeats
    
    $sInput = $User;
    $sInput = $this->strTrim($sInput);                            // dress input formating
    
    if ( $sInput != "" )
    { 
      $wInput = $this->padString(strtoupper($sInput));    // Work copy
      $foundkey = $this->maxKey;                          // assume it's a repeat input
      if ( $wInput != $wPrevious )
      {                       // check if user repeats himself
        $foundkey = $this->findkey($wInput);               // look for a keyword.
      }
      if( $foundkey == $this->keyNotFound )
      {
        if( !$greet )
        {
          $greet = true;
          return "Don't you ever say Hello?";
        }
        else
        {
          $wPrevious = $wInput;                      // save input to check repeats
          if (( strlen($sInput) < 10 ) && ( $wTopic != "" ) && ( $wTopic != $wPrevious ))
          {
            $lTopic = $this->conjugate( $sTopic );
            $sTopic = "";
            $wTopic = "";
            return 'OK... "' + $lTopic + '". Tell me more.';
          }
          else
          {
            if ( strlen($sInput) < 15 )
            { 
              return "Tell me more..."; 
            }
            else
            {
              return $this->phrase( $sInput, $foundkey );
            }
          }
        }
      }
      else
      { 
        if ( strlen($sInput) > 12 )
        {
          $sTopic = $sInput;
          $wTopic = $wInput;
        }
        $greet = true;
        $wPrevious = $wInput;              // save input to check repeats
        return $this->phrase( $sInput, $foundkey );            // Get our response
      }
    }
    else
    {
      return "I can't help if you will not chat with me!";
    }
  }
  
  /**
   * Creates an array of the specified length, and fills it with null values.
   * @param int Array size
   * @return array
   */
  
  function create_array($len)
  {
    $ret = array();
    for ( $i = 0; $i < $len; $i++ )
    {
      $ret[] = null;
    }
    return $ret;
  }
  
  /**
   * Sets up the tables of phrases, etc.
   */
  
  private function table_setup()
  {
    // build our data base here
							 
    $this->conj1[0]  = "are";           $this->conj2[0]  = "am";
    $this->conj1[1]  = "am";            $this->conj2[1]  = "are";
    $this->conj1[2]  = "were";          $this->conj2[2]  = "was";
    $this->conj1[3]  = "was";           $this->conj2[3]  = "were";
    $this->conj1[4]  = "I";             $this->conj2[4]  = "you";    
    $this->conj1[5]  = "me";            $this->conj2[5]  = "you";    
    $this->conj1[6]  = "you";           $this->conj2[6]  = "me";
    $this->conj1[7]  = "my";            $this->conj2[7]  = "your";    
    $this->conj1[8]  = "your";          $this->conj2[8]  = "my";
    $this->conj1[9]  = "mine";          $this->conj2[9]  = "your's";    
    $this->conj1[10] = "your's";        $this->conj2[10] = "mine";    
    $this->conj1[11] = "I'm";           $this->conj2[11] = "you're";
    $this->conj1[12] = "you're";        $this->conj2[12] = "I'm";    
    $this->conj1[13] = "I've";          $this->conj2[13] = "you've";
    $this->conj1[14] = "you've";        $this->conj2[14] = "I've";
    $this->conj1[15] = "I'll";          $this->conj2[15] = "you'll";
    $this->conj1[16] = "you'll";        $this->conj2[16] = "I'll";
    $this->conj1[17] = "myself";        $this->conj2[17] = "yourself";
    $this->conj1[18] = "yourself";      $this->conj2[18] = "myself";
    
    // array to post process correct our tenses of pronouns such as "I/me"
    
    $this->conj3[0]  = "me am";         $this->conj4[0]  = "I am";
    $this->conj3[1]  = "am me";         $this->conj4[1]  = "am I";
    $this->conj3[2]  = "me can";        $this->conj4[2]  = "I can";
    $this->conj3[3]  = "can me";        $this->conj4[3]  = "can I";
    $this->conj3[4]  = "me have";       $this->conj4[4]  = "I have";
    $this->conj3[5]  = "me will";       $this->conj4[5]  = "I will";
    $this->conj3[6]  = "will me";       $this->conj4[6]  = "will I";
    
    
    // Keywords
    
    $this->keyword[ 0]=new Psychotherapist_Key( "CAN YOU",          1,  3);
    $this->keyword[ 1]=new Psychotherapist_Key( "CAN I",            4,  5);
    $this->keyword[ 2]=new Psychotherapist_Key( "YOU ARE",          6,  9);
    $this->keyword[ 3]=new Psychotherapist_Key( "YOU'RE",           6,  9);
    $this->keyword[ 4]=new Psychotherapist_Key( "I DON'T",          10, 13);
    $this->keyword[ 5]=new Psychotherapist_Key( "I FEEL",           14, 16);
    $this->keyword[ 6]=new Psychotherapist_Key( "WHY DON'T YOU", 17, 19);
    $this->keyword[ 7]=new Psychotherapist_Key( "WHY CAN'T I",     20, 21);
    $this->keyword[ 8]=new Psychotherapist_Key( "ARE YOU",          22, 24);
    $this->keyword[ 9]=new Psychotherapist_Key( "I CAN'T",          25, 27);
    $this->keyword[10]=new Psychotherapist_Key( "I AM",             28, 31);
    $this->keyword[11]=new Psychotherapist_Key( "I'M",              28, 31);
    $this->keyword[12]=new Psychotherapist_Key( "YOU",              32, 34);
    $this->keyword[13]=new Psychotherapist_Key( "I WANT",           35, 39);
    $this->keyword[14]=new Psychotherapist_Key( "WHAT",             40, 48);
    $this->keyword[15]=new Psychotherapist_Key( "HOW",              40, 48);
    $this->keyword[16]=new Psychotherapist_Key( "WHO",              40, 48);
    $this->keyword[17]=new Psychotherapist_Key( "WHERE",            40, 48);
    $this->keyword[18]=new Psychotherapist_Key( "WHEN",             40, 48);
    $this->keyword[19]=new Psychotherapist_Key( "WHY",              40, 48);
    $this->keyword[20]=new Psychotherapist_Key( "NAME",             49, 50);
    $this->keyword[21]=new Psychotherapist_Key( "CAUSE",            51, 54);
    $this->keyword[22]=new Psychotherapist_Key( "SORRY",            55, 58);
    $this->keyword[23]=new Psychotherapist_Key( "DREAM",            59, 62);
    $this->keyword[24]=new Psychotherapist_Key( "HELLO",            63, 63);
    $this->keyword[25]=new Psychotherapist_Key( "HI",               63, 63);
    $this->keyword[26]=new Psychotherapist_Key( "MAYBE",            64, 68);
    $this->keyword[27]=new Psychotherapist_Key( "NO",               69, 73);
    $this->keyword[28]=new Psychotherapist_Key( "YOUR",             74, 75);
    $this->keyword[29]=new Psychotherapist_Key( "ALWAYS",           76, 79);
    $this->keyword[30]=new Psychotherapist_Key( "THINK",            80, 82);
    $this->keyword[31]=new Psychotherapist_Key( "ALIKE",            83, 89);
    $this->keyword[32]=new Psychotherapist_Key( "YES",              90, 92);
    $this->keyword[33]=new Psychotherapist_Key( "FRIEND",           93, 98);
    $this->keyword[34]=new Psychotherapist_Key( "COMPUTER",         99, 105);
    $this->keyword[35]=new Psychotherapist_Key( "NO KEY FOUND",     106, 112);
    $this->keyword[36]=new Psychotherapist_Key( "REPEAT INPUT",     113, 116);
    
    
    $this->response[  0]="ELIZA - PHP version ported from Javascript (George Dunlop) code by Dan Fuhry";
    $this->response[  1]="Don't you believe that I can<*";
    $this->response[  2]="Perhaps you would like to be able to<*";
    $this->response[  3]="You want me to be able to<*";
    $this->response[  4]="Perhaps you don't want to<*";
    $this->response[  5]="Do you want to be able to<*";
    $this->response[  6]="What makes you think I am<*";
    $this->response[  7]="Does it please you to believe I am<*";
    $this->response[  8]="Perhaps you would like to be<*";
    $this->response[  9]="Do you sometimes wish you were<*";
    $this->response[ 10]="Don't you really<*";
    $this->response[ 11]="Why don't you<*";
    $this->response[ 12]="Do you wish to be able to<*";
    $this->response[ 13]="Does that trouble you?";
    $this->response[ 14]="Tell me more about such feelings.";
    $this->response[ 15]="Do you often feel<*";
    $this->response[ 16]="Do you enjoy feeling<*";
    $this->response[ 17]="Do you really believe I don't<*";
    $this->response[ 18]="Perhaps in good time I will<@";
    $this->response[ 19]="Do you want me to<*";
    $this->response[ 20]="Do you think you should be able to<*";
    $this->response[ 21]="Why can't you<*";
    $this->response[ 22]="Why are you interested in whether or not I am<*";
    $this->response[ 23]="Would you prefer if I were not<*";
    $this->response[ 24]="Perhaps in your fantasies I am<*";
    $this->response[ 25]="How do you know you can't<*";
    $this->response[ 26]="Have you tried?";
    $this->response[ 27]="Perhaps you can now<*";
    $this->response[ 28]="Did you come to me because you are<*";
    $this->response[ 29]="How long have you been<*";
    $this->response[ 30]="Do you believe it is normal to be<*";
    $this->response[ 31]="Do you enjoy being<*";
    $this->response[ 32]="We were discussing you, not me.";
    $this->response[ 33]="Oh... <*";
    $this->response[ 34]="You're not really talking about me, are you?";
    $this->response[ 35]="What would it mean to you if you got<*";
    $this->response[ 36]="Why do you want<*";
    $this->response[ 37]="Suppose you got<*";
    $this->response[ 38]="What if you never got<*";
    $this->response[ 39]="I sometimes also want<@";
    $this->response[ 40]="Why do you ask?";
    $this->response[ 41]="Does that question interest you?";
    $this->response[ 42]="What answer would please you the most?";
    $this->response[ 43]="What do you think?";
    $this->response[ 44]="Are such questions on your mind often?";
    $this->response[ 45]="What is it that you really want to know?";
    $this->response[ 46]="Have you asked anyone else?";
    $this->response[ 47]="Have you asked such questions before?";
    $this->response[ 48]="What else comes to mind when you ask that?";
    $this->response[ 49]="Names don't interest me.";
    $this->response[ 50]="I don't care about names, please go on.";
    $this->response[ 51]="Is that the real reason?";
    $this->response[ 52]="Don't any other reasons come to mind?";
    $this->response[ 53]="Does that reason explain anything else?";
    $this->response[ 54]="What other reasons might there be?";
    $this->response[ 55]="Please don't apologise!";
    $this->response[ 56]="Apologies are not necessary.";
    $this->response[ 57]="What feelings do you have when you apologise?";
    $this->response[ 58]="Don't be so defensive!";
    $this->response[ 59]="What does that dream suggest to you?";
    $this->response[ 60]="Do you dream often?";
    $this->response[ 61]="What persons appear in your dreams?";
    $this->response[ 62]="Are you disturbed by your dreams?";
    $this->response[ 63]="How are you today.. What would you like to discuss?";
    $this->response[ 64]="You don't seem quite certain.";
    $this->response[ 65]="Why the uncertain tone?";
    $this->response[ 66]="Can't you be more positive?";
    $this->response[ 67]="You aren't sure?";
    $this->response[ 68]="Don't you know?";
    $this->response[ 69]="Are you saying no just to be negative?";
    $this->response[ 70]="You are being a bit negative.";
    $this->response[ 71]="Why not?";
    $this->response[ 72]="Are you sure?";
    $this->response[ 73]="Why no?";
    $this->response[ 74]="Why are you concerned about my<*";
    $this->response[ 75]="What about your own<*";
    $this->response[ 76]="Can you think of a specific example?";
    $this->response[ 77]="When?";
    $this->response[ 78]="What are you thinking of?";
    $this->response[ 79]="Really, always?";
    $this->response[ 80]="Do you really think so?";
    $this->response[ 81]="But you are not sure you<*";
    $this->response[ 82]="Do you doubt you<*";
    $this->response[ 83]="In what way?";
    $this->response[ 84]="What resemblence do you see?";
    $this->response[ 85]="What does the similarity suggest to you?";
    $this->response[ 86]="What other connections do you see?";
    $this->response[ 87]="Could there really be some connection?";
    $this->response[ 88]="How?";
    $this->response[ 89]="You seem quite positive.";
    $this->response[ 90]="Are you Sure?";
    $this->response[ 91]="I see.";
    $this->response[ 92]="I understand.";
    $this->response[ 93]="Why do you bring up the topic of friends?";
    $this->response[ 94]="Do your friends worry you?";
    $this->response[ 95]="Do your friends pick on you?";
    $this->response[ 96]="Are you sure you have any friends?";
    $this->response[ 97]="Do you impose on your friends?";
    $this->response[ 98]="Perhaps your love for friends worries you.";
    $this->response[ 99]="Do computers worry you?";
    $this->response[100]="Are you talking about me in particular?";
    $this->response[101]="Are you frightened by machines?";
    $this->response[102]="Why do you mention computers?";
    $this->response[103]="What do you think machines have to do with your problems?";
    $this->response[104]="Don't you think computers can help people?";
    $this->response[105]="What is it about machines that worries you?";
    $this->response[106]="Say, do you have any psychological problems?";
    $this->response[107]="What does that suggest to you?";
    $this->response[108]="I see.";
    $this->response[109]="I'm not sure I understand you fully.";
    $this->response[110]="Come, come, elucidate your thoughts.";
    $this->response[111]="Can you elaborate on that?";
    $this->response[112]="That is quite interesting.";
    $this->response[113]="Why did you repeat yourself?";
    $this->response[114]="Do you expect a different answer by repeating yourself?";
    $this->response[115]="Come, come, elucidate your thoughts.";
    $this->response[116]="Please don't repeat yourself!";
  }
  
}

/**
 * Keyword class
 */

class Psychotherapist_Key
{
  /**
   * Phrase to match
   * @var string
   */
   
  public $key = '';
  
  /**
   * First response to use
   * @var int
   */
  
  public $idx = 0;
  
  /**
   * Last response to use
   * @var int
   */
  
  public $end = 0;
  
  /**
   * Response last used time
   * @var int
   */
  
  public $last = 0;
  
  /**
   * Constructor.
   * @param string Key
   * @param int Index
   * @param int End
   */
  
  public function __construct($key, $idx, $end)
  {
    $this->key = $key;
    $this->idx = $idx;
    $this->end = $end;
  }
}

 
