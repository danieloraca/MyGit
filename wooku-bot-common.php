<?php

check_if_bot_tables_exist();

function save_bot($post){
	global $wpdb;
	
	$query = "SELECT * FROM wp_bot_settings WHERE id=1";
	$results = $wpdb->get_results($query);
	
	if ($_POST['ignore_price_sublinks']) $_POST['ignore_price_sublinks'] = 1;
	
	if (!$results){
		$query = "INSERT INTO `wp_bot_settings` 
			(`id`, `delay_between_visits`, `multiply_factor_min`, `multiply_factor_max`, `ignore_price_sublinks`) VALUES 
			(NULL, 
			'{$_POST['delay_between_visits']}', 
			'{$_POST['multiply_factor_min']}', 
			'{$_POST['multiply_factor_max']}',
			'{$_POST['ignore_price_sublinks']}'); ";
	} else {
		$query = "UPDATE `wp_bot_settings` SET 
			`delay_between_visits` = '{$_POST['delay_between_visits']}',
			`multiply_factor_min` = '{$_POST['multiply_factor_min']}',
			`multiply_factor_max` = '{$_POST['multiply_factor_max']}',
			`ignore_price_sublinks` = '{$_POST['ignore_price_sublinks']}'
		 WHERE id=1";
	}
	$results = $wpdb->get_results($query);
}

function start_bot(){
	global $wpdb;
	$query = "UPDATE `wp_bot_settings` SET `is_running` = 1 WHERE id=1";
	$results = $wpdb->get_results($query);
}

function stop_bot(){
	global $wpdb;
	$query = "UPDATE `wp_bot_settings` SET `is_running` = 0 WHERE id=1";
	$results = $wpdb->get_results($query);
}

function get_bot_values(){
	global $wpdb;
	$query = "SELECT * FROM wp_bot_settings WHERE id=1";
	$results = $wpdb->get_results($query);
	if ($results)
		return $results[0];
	else return NULL;
}

function check_if_bot_tables_exist(){
	global $wpdb;
	
	$query = "SHOW tables LIKE 'wp_bot_settings'";
	$results = $wpdb->get_results($query);
	if (!$results){
		$query = "CREATE TABLE IF NOT EXISTS `wp_bot_settings` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `delay_between_visits` int(4) NOT NULL,
		  `multiply_factor_min` int(4) NOT NULL,
		  `multiply_factor_max` int(4) NOT NULL,
		  `ignore_price_sublinks` tinyint(4) NOT NULL,
		  `is_running` tinyint(1) DEFAULT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=InnoDB";
		$results = $wpdb->get_results($query);
		
		$query = "CREATE TABLE IF NOT EXISTS `wp_bot_status` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `working` BOOLEAN,
			  `start_time` datetime,
			  `end_time` datetime,
			  `message` TEXT,
			  `url` varchar(255) NOT NULL,
			  PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
		$results = $wpdb->get_results($query);
	}	
}

function get_stop_reason(){
	global $wpdb;

	$query = "SELECT message, url FROM wp_bot_status WHERE id=(SELECT max(id) FROM wp_bot_status)";
	$results = $wpdb->get_results($query);

	return $results;
}

function get_latest_run(){
	global $wpdb;

	$query = "SELECT COUNT(*) AS n FROM wp_sitemap WHERE 1=1";
	$results = $wpdb->get_results($query);
	$nr = $results[0]->n;

	$query = "SELECT * FROM wp_bot_status ORDER BY ID DESC LIMIT $nr";
	$results = $wpdb->get_results($query);
	return $results;
}