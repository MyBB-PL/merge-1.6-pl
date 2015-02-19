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

class SMF_Converter_Module_Moderators extends Converter_Module_Moderators {

	var $settings = array(
		'friendly_name' => 'moderatorów',
		'progress_column' => 'ID_MEMBER',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("moderators", "*", "", array('limit_start' => $this->trackers['start_moderators'], 'limit' => $import_session['moderators_per_screen']));
		while($moderator = $this->old_db->fetch_array($query))
		{
			$this->insert($moderator);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// SMF values
		$insert_data['fid'] = $this->get_import->fid($data['ID_BOARD']);
		$insert_data['id'] = $this->get_import->uid($data['ID_MEMBER']);

		return $insert_data;
	}

	function test()
	{
		// import_fid => fid
		$this->get_import->cache_fids = array(
			7 => 12
		);

		// import_uid => uid
		$this->get_import->cache_uids = array(
			8 => 13
		);

		$data = array(
			'ID_BOARD' => 7,
			'ID_MEMBER' => 8,
		);

		$match_data = array(
			'fid' => 12,
			'id' => 13,
		);

		$this->assert($data, $match_data);
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of moderators
		if(!isset($import_session['total_moderators']))
		{
			$query = $this->old_db->simple_select("moderators", "COUNT(*) as count");
			$import_session['total_moderators'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_moderators'];
	}
}

?>