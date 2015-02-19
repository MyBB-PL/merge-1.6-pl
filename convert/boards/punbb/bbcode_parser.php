<?php
/** 
* Polish Language File developed by mybboard.pl for MyBB Merge System
* Tłumaczenie: szulcu, Gigi
* Poprawki: Ekipa Polskiego Supportu MyBB (mybboard.pl)
* Wersja 1.0
**/
/**
 * MyBB 1.6
 * Copyright 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class BBCode_Parser {

	/**
	 * Converts punBB BBCode to MyBB MyCode
	 *
	 * @param string Text to convert
	 * @return string converted text
	 */
	 function convert($text)
	 {
	 	$text = preg_replace("#\[center](.*?)\[/center\]#i", "[align=center]$1[/align]", $text);
		$text = preg_replace("#\[large\](.*?)\[/large\]#i", "[size=large]$1[/size]", $text);

		return $text;
	 }
}
?>