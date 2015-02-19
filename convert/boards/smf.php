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

class SMF_Converter extends Converter
{

	/**
	 * String of the bulletin board name
	 *
	 * @var string
	 */
	var $bbname = "SMF 1.1";

	/**
	 * String of the plain bulletin board name
	 *
	 * @var string
	 */
	var $plain_bbname = "SMF 1";

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
						 "import_moderators" => array("name" => "Moderatorzy", "dependencies" => "db_configuration,import_forums,import_users"),
						 "import_settings" => array("name" => "Ustawienia", "dependencies" => "db_configuration"),
						 "import_events" => array("name" => "Wydarzenia w kalendarzu", "dependencies" => "db_configuration,import_posts"),
						 "import_attachments" => array("name" => "Załączniki", "dependencies" => "db_configuration,import_posts"),
						);

	var $get_post_cache = array();

	/**
	 * Get a post from the SMF database
	 *
	 * @param int Post ID
	 * @return array The post
	 */
	function get_post($pid)
	{
		if(array_key_exists($pid, $this->get_post_cache))
		{
			return $this->get_post_cache[$pid];
		}

		$pid = intval($pid);

		$query = $this->old_db->simple_select("messages", "*", "ID_MSG = '{$pid}'", array('limit' => 1));
		$results = $this->old_db->fetch_array($query);
		$this->old_db->free_result($query);

		$this->get_post_cache[$pid] = $results;

		return $results;
	}

	/**
	 * Convert a SMF group ID into a MyBB group ID
	 *
	 * @param int Group ID
	 * @param boolean whether or not the Group ID came from ID_GROUP column
	 * @return mixed group id(s)
	 */
	function get_group_id($group_id, $is_group_row=false, $is_activated=1)
	{
		if(empty($group_id))
		{
			return 2; // Return regular registered user.
		}

		if(!is_numeric($group_id))
		{
			$groups = $group_id;
		}
		else
		{
			$groups = array($group_id);
		}


		$comma = $group = '';
		foreach($groups as $key => $smfgroup)
		{
			// Deal with non-activated people
			if($is_activated != 1 && $is_group_row == true)
			{
				return 5;
			}

			$group .= $comma;
			switch($smfgroup)
			{
				case 1: // Administrator
					$group .= 4;
					break;
				case 2: // Super moderator
					$group .= 3;
					break;
				case 3: // Moderator
					$group .= 6;
					break;
				// case 0 group = 2 // Member
				default:
					$gid = $this->get_import->gid($smfgroup);
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

		return $group;
	}
}

?>