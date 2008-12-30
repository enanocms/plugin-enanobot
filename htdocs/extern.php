<?php

require('../stats-fe.php');
require('../timezone.php');

$channel_list = stats_channel_list();
$first_channel = $channel_list[0];
$channel = ( isset($_REQUEST['channel']) && in_array($_REQUEST['channel'], $channel_list) ) ? $_REQUEST['channel'] : $first_channel;

$formats = array('json', 'xml');
$format = ( isset($_GET['format']) ) ? $_GET['format'] : $formats[0];
if ( !in_array($format, $formats) )
{
  $format = $formats[0];
  $formatclass = "format_$format";
  $formatter = new $formatclass();
  $formatter->send_headers();
  die_in_output_format('Invalid output format specified.');
}

$formatclass = "format_$format";
$formatter = new $formatclass();
$formatter->send_headers();

if ( !isset($_GET['action']) )
  die_in_output_format('Please specify action on GET.');

switch($_GET['action'])
{
  case 'get_activity':
    $minutes = isset($_GET['minutes']) ? intval($_GET['minutes']) : 10;
    if ( $minutes < 1 )
      die_in_output_format('minutes < 1');
    $datum = stats_activity_percent($channel, $minutes);
    $count = stats_message_count($channel, $minutes);
    foreach ( $datum as &$pct )
    {
      $pct = $pct * $count;
    }
    $output = array(
        'result' => 'success',
        'minutes' => $minutes,
        'message_count' => $count,
        'active_users' => $datum
      );
    $result = $formatter->encode($output);
    if ( $format == 'xml' )
    {
      $activeusers = '<activeusers>';
      foreach ( $datum as $nick => $count )
      {
        $activeusers .= '<user nick="' . htmlspecialchars($nick) . '" count="' . $count . '" />';
      }
      $activeusers .= '</activeusers>';
      $result = preg_replace('#<activeusers>(.*?)</activeusers>#', $activeusers, $result);
    }
    echo $result;
    break;
}

/** FUNCTIONS **/

function die_in_output_format($message)
{
  global $formatter;
  echo $formatter->encode(array(
      'result' => 'error',
      'error' => $message
    ));
  exit;
}

/** FORMATS **/

class format_json
{
  public function encode($data)
  {
    require_once('../libjson.php');
    return eb_json_encode($data);
  }
  public function send_headers()
  {
    header('Content-type: text/javascript');
  }
}

/**
 * From <http://snipplr.com/view/3491/convert-php-array-to-xml-or-simple-xml-object-if-you-wish/>.
 */

class format_xml
{
	/**
	 * The main function for converting to an XML document.
	 * Pass in a multi dimensional array and this recrusively loops through and builds up an XML document.
	 *
	 * @param array $data
	 * @param string $rootNodeName - what you want the root node to be - defaultsto data.
	 * @param SimpleXMLElement $xml - should only be used recursively
	 * @return string XML
	 */
	public static function toXml($data, $rootNodeName = 'response', $xml = null)
	{
		// turn off compatibility mode as simple xml throws a wobbly if you don't.
		if (ini_get('zend.ze1_compatibility_mode') == 1)
		{
			ini_set ('zend.ze1_compatibility_mode', 0);
		}
		
		if ($xml == null)
		{
			$xml = simplexml_load_string("<?xml version='1.0' encoding='utf-8'?><$rootNodeName />");
		}
		
		// loop through the data passed in.
		foreach($data as $key => $value)
		{
			// no numeric keys in our xml please!
			if (is_numeric($key))
			{
				// make string key...
				$key = "unknownNode_". (string) $key;
			}
			
			// replace anything not alpha numeric
			$key = preg_replace('/[^a-z0-9]/i', '', $key);
			
			// if there is another array found recrusively call this function
			if (is_array($value))
			{
				$node = $xml->addChild($key);
				// recrusive call.
				format_xml::toXml($value, $rootNodeName, $node);
			}
			else 
			{
				// add single node.
        $value = htmlentities($value);
				$xml->addChild($key,$value);
			}
			
		}
		// pass back as string. or simple xml object if you want!
		return $xml->asXML();
	}
  
  public function encode($data)
  {
    return format_xml::toXml($data);
  }
  
  public function send_headers()
  {
    header('Content-type: text/xml');
  }
}
