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

class VBULLETIN3_Converter extends Converter
{

	/**
	 * String of the bulletin board name
	 *
	 * @var string
	 */
	var $bbname = "vBulletin 3.6, 3.7 lub 3.8";

	/**
	 * String of the plain bulletin board name
	 *
	 * @var string
	 */
	var $plain_bbname = "vBulletin 3";

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
						 "import_usergroups" => array("name" => "Grupy użytkowników", "dependencies" => "db_configuration,import_users"),
						 "import_forums" => array("name" => "Działy", "dependencies" => "db_configuration,import_users"),
						 "import_forumperms" => array("name" => "Uprawnienia do działów", "dependencies" => "db_configuration,import_forums"),
						 "import_threads" => array("name" => "Wątki", "dependencies" => "db_configuration,import_forums"),
						 "import_polls" => array("name" => "Ankiety", "dependencies" => "db_configuration,import_threads"),
						 "import_pollvotes" => array("name" => "Głosy w ankietach", "dependencies" => "db_configuration,import_polls"),
						 "import_posts" => array("name" => "Posty", "dependencies" => "db_configuration,import_threads"),
						 "import_moderators" => array("name" => "Moderatorzy", "dependencies" => "db_configuration,import_forums,import_users"),
						 "import_privatemessages" => array("name" => "Prywatne wiadomości", "dependencies" => "db_configuration,import_users"),
						 "import_settings" => array("name" => "Ustawienia", "dependencies" => "db_configuration"),
						 "import_events" => array("name" => "Wydarzenia w kalendarzu", "dependencies" => "db_configuration,import_users"),
						 "import_attachments" => array("name" => "Załączniki", "dependencies" => "db_configuration,import_posts"),
						);

	/**
	 * Convert a vB group ID into a MyBB group ID
	 *
	 * @param int Group ID
	 * @param array Options for retreiving the group ids
	 * @return mixed group id(s)
	 */
	function get_group_id($gid, $options=array())
	{
		$settings = array();
		if($options['not_multiple'] == false)
		{
			$query = $this->old_db->simple_select("usergroup", "COUNT(*) as rows", "usergroupid='{$gid}'");
			$settings = array('limit_start' => '1', 'limit' => $this->old_db->fetch_field($query, 'rows'));
			$this->old_db->free_result($query);
		}

		$query = $this->old_db->simple_select("usergroup", "*", "usergroupid='{$gid}'", $settings);

		$comma = $group = '';
		while($vbgroup = $this->old_db->fetch_array($query))
		{
			if($options['original'] == true)
			{
				$group .= $vbgroup['usergroupid'].$comma;
			}
			else
			{
				$group .= $comma;
				switch($vbgroup['usergroupid'])
				{
					case 1: // Guests
						$group .= 1;
						break;
					case 2: // Register
					case 4: // Registered coppa
						$group .= 2;
						break;
					case 3: // Awaiting activation
						$group .= 5;
						break;
					case 5: // Super moderator
						$group .= 3;
						break;
					case 6: // Administrator
						$group .= 4;
						break;
					default:
						$gid = $this->get_import->gid($vbgroup['usergroupid']);
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