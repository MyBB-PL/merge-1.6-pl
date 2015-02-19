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

class SMF_Converter_Module_Threads extends Converter_Module_Threads {

	var $settings = array(
		'friendly_name' => 'wątków',
		'progress_column' => 'ID_TOPIC',
		'default_per_screen' => 1000,
	);

	var $get_attachment_count_cache = array();

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("topics", "*", "", array('limit_start' => $this->trackers['start_threads'], 'limit' => $import_session['threads_per_screen']));
		while($thread = $this->old_db->fetch_array($query))
		{
			$this->insert($thread);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// SMF values
		$insert_data['import_tid'] = $data['ID_TOPIC'];
		$insert_data['sticky'] = $data['isSticky'];
		$insert_data['fid'] = $this->get_import->fid($data['ID_BOARD']);

		$first_post = $this->board->get_post($data['ID_FIRST_MSG']);
		$insert_data['dateline'] = $first_post['posterTime'];
		$insert_data['subject'] = encode_to_utf8(utf8_unhtmlentities($first_post['subject']), "messages", "threads");

		$insert_data['import_poll'] = $data['ID_POLL'];
		$insert_data['uid'] = $this->get_import->uid($data['ID_MEMBER_STARTED']);
		$insert_data['import_uid'] = $data['ID_MEMBER_STARTED'];
		$insert_data['import_firstpost'] = $data['ID_FIRST_MSG'];
		$insert_data['views'] = $data['numViews'];
		$insert_data['closed'] = $data['locked'];
		if($insert_data['closed'] == "no")
		{
			$insert_data['closed'] = '';
		}

		$insert_data['attachmentcount'] = $this->get_attachment_count($data['ID_TOPIC']);

		return $insert_data;
	}

	function test()
	{
		// import_fid -> fid
		$this->get_import->cache_fids = array(
			5 => 10,
		);

		// import_uid -> uid
		$this->get_import->cache_uids = array(
			6 => 11,
		);

		$this->get_post_cache = array(
			7 => array(
				'posterTime' => 12345678,
				'subject' => 'Testéfdfs˙˙ subject'
			),
		);

		$this->get_attachment_count_cache = array(
			4 => 53,
		);

		$data = array(
			'ID_TOPIC' => 4,
			'isSticky' => 1,
			'ID_BOARD' => 5,
			'ID_FIRST_MSG' => 7,
			'ID_POLL' => 8,
			'ID_MEMBER_STARTED' => 6,
			'numViews' => 532,
			'locked' => '',
		);

		$match_data = array(
			'import_tid' => 4,
			'sticky' => 1,
			'fid' => 10,
			'subject' => utf8_encode('Testéfdfs˙˙ subject'), // The Merge System should convert the mixed ASCII/Unicode string to proper UTF8
			'import_poll' => 8,
			'uid' => 11,
			'import_uid' => 6,
			'views' => 532,
			'closed' => '',
			'attachmentcount' => 53,
		);

		$this->assert($data, $match_data);
	}

	function get_attachment_count($tid)
	{
		if(array_key_exists($tid, $this->get_attachment_count_cache))
		{
			return $this->get_attachment_count_cache[$tid];
		}

		$pids = '';
		$comma = '';
		$count = 0;

		// TODO: Rewrite this down into cacheable function
		$query = $this->old_db->simple_select("messages", "ID_MSG", "ID_TOPIC='{$tid}'");
		while($post = $this->old_db->fetch_array($query))
		{
			$pids .= $comma.$post['ID_MSG'];
			$comma = ', ';
		}
		$this->old_db->free_result($query);

		if($pids)
		{
			$query = $this->old_db->simple_select("attachments", "COUNT(*) as numattachments", "ID_MSG IN($pids)");
			$count = $this->old_db->fetch_field($query, 'numattachments');
			$this->old_db->free_result($query);
		}

		$this->get_attachment_count_cache[$tid] = $count;

		return $count;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of threads
		if(!isset($import_session['total_threads']))
		{
			$query = $this->old_db->simple_select("topics", "COUNT(*) as count");
			$import_session['total_threads'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_threads'];
	}
}

?>