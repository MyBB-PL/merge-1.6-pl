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

class BBPRESS_Converter extends Converter
{

	/**
	 * String of the bulletin board name
	 *
	 * @var string
	 */
	var $bbname = "BBPress 1.0";

	/**
	 * String of the plain bulletin board name
	 *
	 * @var string
	 */
	var $plain_bbname = "BBPress 1.0";

	/**
	 * Whether or not this module requires the loginconvert.php plugin
	 *
	 * @var boolean
	 */
	var $requires_loginconvert = true;

	/**
	 * Array of all of the modules
	 *
	 * @var array
	 */
	var $modules = array("db_configuration" => array("name" => "Konfiguracja bazy danych", "dependencies" => ""),
						 "import_users" => array("name" => "Użytkownicy", "dependencies" => "db_configuration"),
						 "import_forums" => array("name" => "Działy", "dependencies" => "db_configuration,import_users"),
						 "import_threads" => array("name" => "Wątki", "dependencies" => "db_configuration,import_forums"),
						 "import_posts" => array("name" => "Posty", "dependencies" => "db_configuration,import_threads"),
						);

	/**
	 * Convert a bbPress group ID into a MyBB group ID
	 *
	 * @param int Group ID
	 * @param array Options for retreiving the group ids
	 * @return mixed group id(s)
	 */
	function get_group_id($uid, $options=array())
	{
		global $old_table_prefix;
		$settings = array();
		if($options['not_multiple'] == false)
		{
			$query = $this->old_db->simple_select("usermeta", "COUNT(*) as rows", "user_id = '{$uid}' AND meta_key = '".$this->old_db->table_prefix."capabilities'");
			$settings = array('limit_start' => '1', 'limit' => $this->old_db->fetch_field($query, 'rows'));
			$this->old_db->free_result($query);
		}

		$query = $this->old_db->simple_select("usermeta", "*", "user_id = '{$uid}' AND meta_key = '".$this->old_db->table_prefix."capabilities'", $settings);

		$comma = $group = '';
		while($bbpress = $this->old_db->fetch_array($query))
		{
			$bbpress['group_id'] = preg_replace('#\w+:\d+:{\w+:\d+:\"(.*?)\";\w+:\d+;}#', '$1', $bbpress['meta_value']);
			$group .= $comma;
			switch($bbpress['group_id'])
			{
				case "member": // Register
					$group .= 1;
					break;
				case "moderator": // Super Moderator
					$group .= 3;
					break;
				case "keymaster": // Administrator
				case "administrator":
					$group .= 4;
					break;
				case "blocked": // Banned...
					$group .= 7;
					break;
				default:
					$gid = $this->get_import->gid($bbpress['group_id']);
					if($gid > 0)
					{
						// If there is an associated custom group...
						$group .= $gid;
					}
					else
					{
						// The lot
						$group .= 2;
					}
			}
			$comma = ',';
		}
		if(!$query)
		{
			return 2; // Return regular registered user.
		}

		$this->old_db->free_result($query);
		return $group;
	}
}

?>