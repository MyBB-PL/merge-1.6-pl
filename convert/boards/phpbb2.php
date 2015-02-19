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

class PHPBB2_Converter extends Converter
{

	/**
	 * String of the bulletin board name
	 *
	 * @var string
	 */
	var $bbname = "phpBB 2";

	/**
	 * String of the plain bulletin board name
	 *
	 * @var string
	 */
	var $plain_bbname = "phpBB 2";

	/**
	 * Whether or not this module requires the loginconvert.php plugin
	 *
	 * @var boolean
	 */
	var $requires_loginconvert = false;

	/**
	 * Array of all of the modules
	 *
	 * @var array
	 */
	var $modules = array("db_configuration" => array("name" => "Konfiguracja bazy danych", "dependencies" => ""),
						 "import_usergroups" => array("name" => "Grupy użytkowników", "dependencies" => "db_configuration"),
						 "import_users" => array("name" => "Użytkownicy", "dependencies" => "db_configuration,import_usergroups"),
						 "import_categories" => array("name" => "Kategorie", "dependencies" => "db_configuration,import_users"),
						 "import_forums" => array("name" => "Działy", "dependencies" => "db_configuration,import_categories"),
						 "import_forumperms" => array("name" => "Uprawnienia do działów", "dependencies" => "db_configuration,import_forums"),
						 "import_threads" => array("name" => "Wątki", "dependencies" => "db_configuration,import_forums"),
						 "import_polls" => array("name" => "Ankiety", "dependencies" => "db_configuration,import_threads"),
						 "import_pollvotes" => array("name" => "Głosy w ankietach", "dependencies" => "db_configuration,import_polls"),
						 "import_posts" => array("name" => "Posty", "dependencies" => "db_configuration,import_threads"),
						 "import_privatemessages" => array("name" => "Prywatne wiadomości", "dependencies" => "db_configuration,import_users"),
						 "import_settings" => array("name" => "Ustawienia", "dependencies" => "db_configuration"),
						);

	/**
	 * Convert a phpBB group ID into a MyBB group ID
	 *
	 * @param array User Details
	 * @param array Options for retreiving the group ids
	 * @return mixed group id(s)
	 */
	function get_group_id($user, $options=array())
	{
		$settings = array();
		if($options['not_multiple'] == false)
		{
			$query = $this->old_db->simple_select("user_group", "COUNT(*) as rows", "user_id='{$user['user_id']}'");
			$settings = array('limit_start' => '1', 'limit' => $this->old_db->fetch_field($query, 'rows'));
			$this->old_db->free_result($query);
		}

		$comma = $group = '';

		switch($user['user_level'])
		{
			case 0:
				$group .= 2;
				$comma = ',';
				break;
			case 1:
				$group .= 4;
				$comma = ',';
				break;
			case 2:
				$group .= 6;
				$comma = ',';
				break;
		}

		$query = $this->old_db->simple_select("user_group", "*", "user_id='{$user['user_id']}'", $settings);
		while($phpbbgroup = $this->old_db->fetch_array($query))
		{
			if($options['original'] == true)
			{
				$group .= $phpbbgroup['group_id'].$comma;
			}
			else
			{
				// Deal with non-activated people
				if($phpbbgroup['user_pending'] != '0')
				{
					return 5;
				}

				$group .= $comma;
				switch($phpbbgroup['group_id'])
				{
					case 2: // Administrator
						$group .= 4;
						break;
					default:
						$gid = $this->get_import->gid($phpbbgroup['group_id']);
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