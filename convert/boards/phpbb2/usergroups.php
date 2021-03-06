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

class PHPBB2_Converter_Module_Usergroups extends Converter_Module_Usergroups {

	var $settings = array(
		'friendly_name' => 'grup użytkowników',
		'progress_column' => 'group_id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session, $db;

		// Get only non-staff groups.
		$query = $this->old_db->simple_select("groups", "*", "group_id > 2 AND group_single_user != '1'", array('limit_start' => $this->trackers['start_usergroups'], 'limit' => $import_session['usergroups_per_screen']));
		while($group = $this->old_db->fetch_array($query))
		{
			$gid = $this->insert($group);

			// Restore connections
			$db->update_query("users", array('usergroup' => $gid), "import_usergroup = '".intval($group['group_id'])."' OR import_displaygroup = '".intval($group['group_id'])."'");
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// phpBB 2 values
		$insert_data['import_gid'] = $data['group_id'];
		$insert_data['title'] = $data['group_name'];
		$insert_data['description'] = $data['group_description'];

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of usergroups
		if(!isset($import_session['total_usergroups']))
		{
			$query = $this->old_db->simple_select("groups", "COUNT(*) as count", "group_id > 2 AND group_single_user != '1'");
			$import_session['total_usergroups'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_usergroups'];
	}
}

?>