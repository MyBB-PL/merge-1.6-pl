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

class IPB2_Converter_Module_Posts extends Converter_Module_Posts {

	var $settings = array(
		'friendly_name' => 'postów',
		'progress_column' => 'pid',
		'default_per_screen' => 1000,
		'check_table_type' => 'posts',
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("posts", "*", "", array('limit_start' => $this->trackers['start_posts'], 'limit' => $import_session['posts_per_screen']));
		while($post = $this->old_db->fetch_array($query))
		{
			$this->insert($post);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// Invision Power Board 2 values
		$insert_data['import_pid'] = $data['pid'];
		$insert_data['tid'] = $this->get_import->tid($data['topic_id']);
		$thread = $this->get_thread($data['topic_id']);
		$insert_data['fid'] = $this->get_import->fid($thread['forum_id']);
		$insert_data['subject'] = encode_to_utf8($thread['title'], "topics", "posts");
		if($data['queued'] == 0)
		{
			$insert_data['visible'] = 1;
		}
		else
		{
			$insert_data['visible'] = 0;
		}
		$insert_data['uid'] = $this->get_import->uid($data['author_id']);
		$insert_data['import_uid'] = $data['author_id'];
		$insert_data['username'] = $this->get_import->username($insert_data['import_uid']);
		$insert_data['dateline'] = $data['post_date'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert($data['post']), "posts", "posts");
		$insert_data['ipaddress'] = $data['ip_address'];
		$insert_data['includesig'] = $data['use_sig'];
		$insert_data['smilieoff'] = int_to_01($data['allowsmilie']);
		$insert_data['edituid'] = $this->get_import->uid($this->get_uid_from_username($data['edit_name']));
		$insert_data['edittime'] = $data['edit_time'];
		$insert_data['posthash'] = $data['post_key'];

		return $insert_data;
	}

	function after_insert($data, $insert_data, $pid)
	{
		global $db;

		// Restore first post connections
		$db->update_query("threads", array('firstpost' => $pid), "tid = '{$insert_data['tid']}' AND import_firstpost = '{$insert_data['import_pid']}'");
		if($db->affected_rows() == 0)
		{
			$query = $db->simple_select("threads", "firstpost", "tid = '{$insert_data['tid']}'");
			$first_post = $db->fetch_field($query, "firstpost");
			$db->free_result($query);
			$db->update_query("posts", array('replyto' => $first_post), "pid = '{$pid}'");
		}
	}

	/**
	 * Get a thread from the IPB database
	 *
	 * @param int Thread ID
	 * @return array The thread
	 */
	function get_thread($tid)
	{
		$query = $this->old_db->simple_select("topics", "*", "tid='{$tid}'", array('limit' => 1));
		$results = $this->old_db->fetch_array($query);
		$this->old_db->free_result($query);

		return $results;
	}

	/**
	 * Get a user id from a username in the IPB database
	 *
	 * @param int Username
	 * @return int If the username is blank it returns 0. Otherwise returns the user id
	 */
	function get_uid_from_username($username)
	{
		if($username == '')
		{
			return 0;
		}

		$query = $this->old_db->simple_select("members", "id", "name='{$username}'", array('limit' => 1));

		$results = $this->old_db->fetch_field($query, "id");
		$this->old_db->free_result($query);

		return $results;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of posts
		if(!isset($import_session['total_posts']))
		{
			$query = $this->old_db->simple_select("posts", "COUNT(*) as count");
			$import_session['total_posts'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_posts'];
	}
}

?>