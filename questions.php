<?php
/*
Plugin Name: Aviation Questions
Plugin URI: #
Description: Aviation Questions Plugin
Version: 1.0
Author: Daniel
Author URI: #
License: 
*/

class Aviation_Questions {
	
	public function __construct(){
		        
		add_action('admin_menu', array('Aviation_Questions', 'admin_menu'));
		
		add_filter( 'wp_title', 'aviation_questions_title', 10 );
		add_action( 'wp_head', 'aviation_questions_title_output', 0 );
		
		add_action( 'admin_menu', 'aviation_questions_actions' );
	}
	
	public function aviation_questions_actions(){
		add_options_page( 'Option1', 'Option1', 1, 'Option1', 'aviation_questions_admin' );
	}
	public function aviation_questions_title_output(){
		$output = '';
		$output .= "wow\n";
		echo $output;
	}
	
	public function aviation_questions_title(){
		return 'daniel';
	}
	
	public function admin_menu() {
		add_management_page( __( 'Aviation Questions', 'aviation_questions' ), __( 'Aviation Questions', 'aviation_questions' ), 'manage_options', 'aviation-questions', array('Aviation Questions', 'render_admin_action'));
	}
	
	public function render_admin_action() {
		$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'init';
		require_once(plugin_dir_path(__FILE__) . 'wooku-meta-common.php');
		require_once(plugin_dir_path(__FILE__)."wooku-meta-{$action}.php");
	}


}

$Aviation_questions = new Aviation_Questions();

