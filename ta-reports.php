<?php
/*
 * Plugin Name: TA Reports (New)
 * Version: 0.1
 * Description: Generates and displays a reports table for downloading
 * Author: Jorie Sieck
 * Author URI: http://www.globalcognition.org
*/

// don't load if accessed directly or gutenberg unavailable
defined('ABSPATH') || exit;
if(!function_exists('register_block_type')) {
	return;
}

/*
 * generate the user data for a given user
*/
function ta_generate_user_data($course_id,$total_course_steps,$user,$user_id,$quiz_data,$enrolled = false) {
	$user_data = $quiz_data;
	$user_data["username"] = $user->user_login;
	$user_data["name"] = $user->display_name;

	// get last login time from pmpro
	$last_login_time = get_user_meta($user_id,'pmpro_logins',true)['last'];

	// for enrolled users only
	if($enrolled) {
		// get percent complete
		$percent_complete = round((learndash_course_get_completed_steps($user_id,$course_id) / $total_course_steps) * 100,0);

		// for each quiz, log the most recent score & number of attempts
		$quizzes_completed = learndash_get_user_quiz_attempt($user_id,$course_id);

		$quiz_keys = array_keys($quiz_data);
		foreach($quizzes_completed as $quiz) {
			$quiz_id = $quiz['quiz'];
			if(in_array($quiz_id,$quiz_keys)) {
				$user_data[$quiz_id]['score'] = $quiz['percentage'];
				$user_data[$quiz_id]['attempts'] += 1;
			}
		}
	}

	$user_data["lastlogin"] = $last_login_time ? $last_login_time : 'None';
	$user_data["percentcomplete"] = $percent_complete ? $percent_complete : 0;

	return $user_data;
}

/*
 * generate user data
*/
function ta_reports_get_user_data($user,$user_id,$course_id,$quiz_data) {
	if(!$quiz_data) {
		$quiz_data = ta_get_quizzes();
	}
	// get total course steps
	$total_course_steps = learndash_get_course_steps_count($course_id);

	// check for sponsored members & get list
	$sponsored_members = pmprosm_getChildren($user_id);
	$sponsored_members[] = $user_id;	// include self in report

	$users_data = array();
	// generate user data for each sponsored member
	foreach($sponsored_members as $student_id_str) {
		$student_id = intval($student_id_str);
		$student = get_user_by("id",$student_id);
		$enrolled = learndash_user_get_enrolled_courses($student_id)!=0;
		$users_data[$student_id] = ta_generate_user_data($course_id,$total_course_steps,$student,$student_id,$quiz_data,$enrolled);
	}

	return $users_data;
}

function ta_get_quizzes() {
	// get list of quizzes
	global $wpdb;
	$post_table_name = $wpdb->prefix . 'posts';
	$sql = "SELECT `ID` FROM `{$post_table_name}` WHERE (`post_title` = 'Section Quiz') AND `post_type` = 'sfwd-quiz' AND  `post_status` =  'publish'";
	$quiz_ids = $wpdb->get_results($sql, 'ARRAY_N');
	// add final challenge at end
	$sql = "SELECT `ID` FROM `{$post_table_name}` WHERE (`post_title` = 'Final Challenge') AND `post_type` = 'sfwd-quiz' AND  `post_status` =  'publish'";
	$quiz_ids = array_merge($quiz_ids,$wpdb->get_results($sql, 'ARRAY_N'));

	// fill in with dummy data
	$quiz_data = array();
	foreach($quiz_ids as $quiz) {
		$quiz_id = $quiz[0];
		$quiz_data[$quiz_id] = array(
			"score" => "N/A",
			"attempts" => 0
		);
	}

	return $quiz_data;
}

/*
 * get the necessary data and send to javascript
*/
add_action('wp_enqueue_scripts','ta_get_report_data');
function ta_get_report_data() {
	if(is_page('study-skills-report')) {
		global $current_user;
		// make sure user is logged in
		if($current_user->ID) {
			// enqueue scripts
			wp_enqueue_script(
        'tarep-main-js',
        plugins_url('/assets/js/main.js', __FILE__),
        ['wp-blocks', 'wp-element', 'wp-components', 'jquery'],
        time(),
        true
      );

			// add button and anchor div
			add_action('genesis_before_entry_content','ta_generate_download_button');

			// get data
			$user = $current_user;
			$user_id = $user->ID;
			$course_id = learndash_get_last_active_course($user_id);
			if(!$course_id) {
				$course_id = 4192;
			}
			$course_sections = learndash_30_get_course_sections($course_id);
			$quiz_data = ta_get_quizzes();
			$quiz_ids = array_keys($quiz_data);

			$section_titles = array(
				"Username" => "username",
				"Name" => "name",
				"Last Login" => "lastlogin",
				"Percent Complete" => "percentcomplete"
			);
			$i = 0;
			foreach ($course_sections as $section) {
				$section_titles[$section->post_title] = $quiz_ids[$i];
				$i += 1;
			}
			$section_titles["Final Challenge"] = $quiz_ids[$i];	// final challenge goes at the end

			// generate user data
			$users_data = ta_reports_get_user_data($user,$user_id,$course_id,$quiz_data);

			// prep data
			$report_data = array(
				"sectionHeaders" => $section_titles,
				"usersData" => $users_data,
				"courseUrl" => learndash_get_course_url($course_id),
			);

			// send data to main.js
			wp_localize_script('tarep-main-js','repObj',$report_data);
		} else {
			echo "Please log in";
		}
	}
}

/*
 * enqueue styles
*/
function ta_report_enqueue_styles() {

  wp_enqueue_style(
    'tarep-main-css',
    plugins_url( '/assets/css/main.css', __FILE__ ),
    [],
    time(),
    'all'
  );

}
add_action( 'wp_enqueue_scripts', 'ta_report_enqueue_styles' );

?>
