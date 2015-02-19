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

class PHPBB3_Converter_Module_Polls extends Converter_Module_Polls {

	var $settings = array(
		'friendly_name' => 'ankiet',
		'progress_column' => 'topic_id',
		'default_per_screen' => 1000,
	);

	var $cache_poll_choices = array();

	var $cache_get_poll_thread = array();

	function import()
	{
		global $import_session, $db;

		$done_array = array();

		$query = $this->old_db->simple_select("poll_options", "*", "", array('order_by' => 'topic_id', 'group_by' => 'topic_id', 'limit_start' => $this->trackers['start_polls'], 'limit' => $import_session['polls_per_screen']));
		while($poll = $this->old_db->fetch_array($query))
		{
			if(in_array($poll['topic_id'], $done_array))
			{
				continue;
			}

			$pid = $this->insert($poll);

			$done_array[] = $poll['topic_id'];

			// Restore connections
			$db->update_query("threads", array('poll' => $pid), "import_tid = '".$poll['topic_id']."'");
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// phpBB 3 values
		$insert_data['import_tid'] = $data['topic_id'];
		$insert_data['tid'] = $this->get_import->tid($data['topic_id']);

		$poll_details = $this->get_poll_thread($data['topic_id']);
		$insert_data['question'] = $poll_details['poll_title'];
		$insert_data['dateline'] = $poll_details['poll_start'];

		$poll_choices = $this->get_poll_choices($data['topic_id']);
		$insert_data['options'] = $poll_choices['options'];
		$insert_data['votes'] = $poll_choices['votes'];
		$insert_data['numoptions'] = $poll_choices['options_count'];
		$insert_data['numvotes'] = $poll_choices['vote_count'];
		$insert_data['timeout'] = $poll_details['poll_length'];

		return $insert_data;
	}

	function test()
	{
		$this->get_import->cache_tids = array(
			3 => 10,
		);

		$this->cache_get_poll_thread = array(
			3 => array(
				'poll_title' => 'Test, test, fdsfdsf ds dsf  est�fdf fdsfds s��?',
				'poll_start' => 12345678,
				'poll_length' => 12345689,
			),
		);

		$this->cache_poll_choices = array(
			3 => array(
				'options' => 'choice 1||~|~||choice 2||~|~||choice 3',
				'votes' => '5||~|~||12||~|~||32',
				'options_count' => 3,
				'vote_count' => 49,
			),
		);

		$data = array(
			'topic_id' => 3,
			'tid' => 3,
			'expireTime' => 12345689,
		);

		$match_data = array(
			'import_tid' => 3,
			'tid' => 10,
			'dateline' => 12345678,
			'question' => 'Test, test, fdsfdsf ds dsf  est�fdf fdsfds s��?',
			'options' => 'choice 1||~|~||choice 2||~|~||choice 3',
			'votes' => '5||~|~||12||~|~||32',
			'numoptions' => 3,
			'numvotes' => 49,
			'timeout' => 12345689,
		);

		$this->assert($data, $match_data);
	}

	function get_poll_thread($tid)
	{
		global $db;

		if(array_key_exists($tid, $this->cache_get_poll_thread))
		{
			return $this->cache_get_poll_thread[$tid];
		}

		$query = $this->old_db->simple_select("topics", "poll_title,poll_start,poll_length", "topic_id = '{$tid}'");
		$thread = $this->old_db->fetch_array($query);
		$this->old_db->free_result($query);

		$this->cache_get_poll_thread[$tid] = $thread;

		return $thread;
	}

	function get_poll_choices($pid)
	{
		if(array_key_exists($pid, $this->cache_poll_choices))
		{
			return $this->cache_poll_choices[$pid];
		}

		$seperator = '';
		$options = '';
		$votes = '';
		$vote_count = 0;
		$options_count = 0;

		$query = $this->old_db->simple_select("poll_options", "*", "topic_id = '{$pid}'");
		while($vote_result = $this->old_db->fetch_array($query))
		{
			$options .= $seperator.$vote_result['poll_option_text'];
			$votes .= $seperator.$vote_result['poll_option_total'];
			++$options_count;
			$vote_count += $vote_result['poll_option_total'];
			$seperator = '||~|~||';
		}
		$this->old_db->free_result($query);

		$poll_choices = array(
			'options' => $options,
			'votes' => $votes,
			'options_count' => $options_count,
			'vote_count' => $vote_count
		);

		$this->cache_poll_choices[$pid] = $poll_choices;

		return $poll_choices;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of polls
		if(!isset($import_session['total_polls']))
		{
			$query = $this->old_db->simple_select("poll_votes", "COUNT(DISTINCT topic_id) as count");
			$import_session['total_polls'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_polls'];
	}
}

?>