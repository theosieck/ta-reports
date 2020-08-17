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

global $ta_report_csv_headers;
$ta_report_csv_headers = array(
	"Username",
	"Name",
	"Last Login",
	"Percent Complete",
	"Final Challenge Score",
	"Final Challenge Attempts",
	"Memory Quiz Score",
	"Memory Quiz Attempts",
	"Technical Reading Quiz Score",
	"Technical Reading Quiz Attempts",
	"Taking Notes Quiz Score",
	"Taking Notes Quiz Attempts",
	"Time Management Quiz Score",
	"Time Management Quiz Attempts",
	"Test Taking Quiz Score",
	"Test Taking Quiz Attempts"
);

/*
 * store last user login time in the format "August 12, 2020, 8:48 pm UTC"
*/
add_action('wp_login','ta_store_last_login',0,2);
function ta_store_last_login($login,$user) {
	update_user_meta($user->ID,'last_login_time',date('F j, Y, g:i a T'));
}

/*
 * generate the user data for a given user
*/
function ta_generate_user_data($user,$user_id,$course_id) {
	$total_course_steps = learndash_get_course_steps_count($course_id);

	// get last login time
	$last_login_time = get_user_meta($user_id,'last_login_time',true);
	// because wp doesn't save last login time, this function won't work for users who do not log in in between plugin activation & reports accessing

	// get percent complete
	$percent_complete = round((learndash_course_get_completed_steps($user_id,$course_id) / $total_course_steps) * 100,0);

	$all_lessons = learndash_get_lesson_list($course_id);
	$quiz_ids = array();
	foreach($all_lessons as $lesson) {
		$quizzes = learndash_get_lesson_quiz_list($lesson);
		if($quizzes) {
			array_push($quiz_ids,$quizzes[1]['post']->ID);
		}
	}
	array_push($quiz_ids,learndash_get_global_quiz_list($course_id)[0]->ID);

	// get list of quizzes
	$quizzes_completed = learndash_get_user_quiz_attempt($user_id,$course_id);
	$quiz_data = array();
	// for each quiz, log the most recent score & number of attempts
	foreach($quizzes_completed as $quiz) {
		$quiz_id = $quiz['quiz'];
		$quiz_data[$quiz_id]['score'] = $quiz['percentage'];
		$quiz_data[$quiz_id]['attempts'] += 1;
	}
	// fill in any incomplete quizzes
	foreach($quiz_ids as $quiz_id) {
		if(!$quiz_data[$quiz_id]) {
			$quiz_data[$quiz_id]['score'] = 'N/A';
			$quiz_data[$quiz_id]['attempts'] = 0;
		}
	}

	// sort the data by quiz id (assumes quizzes were created in order)
	krsort($quiz_data);

	// organize the data for easy access in js
	$user_data = array(
		"username" => $user->user_login,
		"name" => $user->display_name,
		"lastLogin" => $last_login_time ? $last_login_time : 'No recorded login time',
		"percentComplete" => $percent_complete,
		"quizData" => $quiz_data
	);

	return $user_data;
}

/*
 * button to download as csv
*/
function ta_generate_download_button() {
	?>
	<a href="<?php echo esc_url( admin_url( 'admin-ajax.php' ) . '?action=ta_download_report_csv' ); ?>" class="ta-download-button"><?php esc_html_e( 'Download as CSV', 'arc-jquery-ajax' ); ?></a>
	<?php
}

/*
 * generate user data
*/
function ta_reports_get_user_data($user,$user_id,$course_id = NULL) {
	if(!$course_id) {
		$course_id = learndash_get_last_active_course($user_id);
	}
	// check for sponsored members & get list
	$sponsored_members = pmprosm_getChildren($user_id);
	$users_data = array();
	if($sponsored_members) {
		// generate user data for each sponsored member
		foreach($sponsored_members as $student_id_str) {
			$student_id = intval($student_id_str);
			if(learndash_user_get_enrolled_courses($student_id)) {
				$student = get_user_by("id",$student_id);
				if(!$course_id) {
					$course_id = learndash_get_last_active_course($student_id);
				}
				$users_data[$student_id] = ta_generate_user_data($student,$student_id,$course_id);
			}

		}
	} else {
		// generate user data for current user only
		$users_data[$user_id] = ta_generate_user_data($user,$user_id,$course_id);
	}

	return $users_data;
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
			if($course_id==0) {
				$course_id = learndash_get_last_active_course(pmprosm_getChildren($user_id)[1]);
			}
			$course_sections = learndash_30_get_course_sections($course_id);

			$section_titles = array(
				"Username",
				"Name",
				"Last Login",
				"Percent Complete",
				"Final Challenge"
			);
			foreach ($course_sections as $section) {
				array_push($section_titles,$section->post_title);
			}

			// generate user data
			$users_data = ta_reports_get_user_data($user,$user_id,$course_id);

			// prep data
			$report_data = array(
				"sectionHeaders" => $section_titles,
				"usersData" => $users_data,
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

/*
 * function to actually handle the download - called in ta_download_report_csv()
*/
function ta_send_report_data($csv_file,$filename,$headers) {
		// close temp file
		fclose($csv_file);

		if (version_compare(phpversion(), '5.3.0', '>')) {
				//make sure we get the right file size
				clearstatcache( true, $filename );
		} else {
				// for any PHP version prior to v5.3.0
				clearstatcache();
		}

		// set download size
		$headers[] = "Content-Length: " . filesize($filename);

		// make sure headers have not been sent already
		if(headers_sent()) {
				$response['type'] = 'error: headers already sent';
				$response = json_encode($response);
				die;
		}

		// send headers
		foreach($headers as $header) {
				header($header . "\r\n");
		}

		// disable compression for the duration of file download
		if(ini_get('zlib.output_compression')){
				ini_set('zlib.output_compression', 'Off');
		}

		// read the file to output - if using on flywheel site, use readfile instead
		if(function_exists('fpassthru')) {
				$fp = fopen($filename, 'rb');
				fpassthru($fp);
				fclose($fp);
		} else {
				readfile($filename);
		}

		// remove temp file
		unlink($filename);

		// exit
		exit;
}

/*
 * generate & download CSV with reports data
*/
add_action('wp_ajax_ta_download_report_csv','ta_download_report_csv');
function ta_download_report_csv() {
	global $ta_report_csv_headers;
	global $current_user;

	// set headers
	$headers = array();
	$headers[] = "Content-Disposition: attachment; filename=\"thinkeracademy_student_report.csv\"";
	$headers[] = "Content-Type: text/csv";
	$headers[] = "Cache-Control: max-age=0, no-cache, no-store";
	$headers[] = "Pragma: no-cache";
	$headers[] = "Connection: close";

	// set default csv headers
	$csv_headers = implode(',',$ta_report_csv_headers) . "\n";

	// generate user data
	$users = ta_reports_get_user_data($current_user,$current_user->ID);
	$csv_rows = "";
	// loop over user data & generate csv row for each
	foreach($users as $user) {
		$user_row = '';

		// loop over each cell
		foreach($user as $cell) {
			if(!is_array($cell)) {
				// escape any commas and append
				$user_row .= str_replace(',','',$cell) . ',';
			} else {
				// reverse scores
				ksort($cell);
				// flatten the scores array and append
				foreach($cell as $score_attempts) {
					$user_row .= implode(',',$score_attempts) . ',';
				}
			}
		}

		$csv_rows .= substr_replace($user_row,"\n",-1);	// replace the last comma with a \n
	}

	// create temp dir/file
	$tmp_dir = sys_get_temp_dir();
	$filename = tempnam( $tmp_dir, 'tarep_data_');

	// open file for appending
	$csv_file = fopen($filename, 'a');

	// write csv header, rows to file
	fprintf($csv_file, '%s', $csv_headers);
	fprintf($csv_file, '%s', $csv_rows);

	// send data
	ta_send_report_data($csv_file,$filename,$headers);

	exit;
}

?>
