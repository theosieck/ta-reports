<?php
/*
 * Plugin Name: TA Reports (New)
 * Version: 0.1
 * Description: Generates and displays a reports table for downloading
 * Author: Jorie Sieck
 * Author URI: http://www.globalcognition.org
*/
global $ta_report_section_headers;
$ta_report_section_headers = array(
	"Username",
	"Name",
	"Last Login Time",
	"Completed",
	"Memory",
	"Technical Reading",
	"Taking Notes",
	"Time Management",
	"Test Taking",
	"Final Challenge"
);
global $ta_report_csv_headers;
$ta_report_csv_headers = array(
	"Username",
	"Name",
	"Last Login",
	"Percent Complete",
	"Memory Quiz Score",
	"Memory Quiz Attempts",
	"Technical Reading Quiz Score",
	"Technical Reading Quiz Attempts",
	"Taking Notes Quiz Score",
	"Taking Notes Quiz Attempts",
	"Time Management Quiz Score",
	"Time Management Quiz Attempts",
	"Test Taking Quiz Score",
	"Test Taking Quiz Attempts",
	"Final Challenge Score",
	"Final Challenge Attempts"
);
global $ta_course_id;
$ta_course_id = "4192";	// the id of the course post
global $ta_quiz_slugs;
$ta_quiz_slugs = array("memory-section-quiz","ares-section-quiz","taking-notes-section-quiz","test-taking-section-quiz","test-taking-section-quiz-2","final-challenge");
global $ta_quiz_ids;	// see if can do with slugs on live
$ta_quiz_ids = array(6400,6407,6413,6418,6423,6429);

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
function ta_generate_user_data($user,$user_id) {
	global $ta_course_id;
	global $ta_quiz_slugs;
	global $ta_quiz_ids;
	$total_course_steps = learndash_get_course_steps_count($ta_course_id);

	// get last login time
	$last_login_time = get_user_meta($user_id,'last_login_time',true);
	// because wp doesn't save last login time, this function won't work for users who do not log in inbetween plugin activation & reports accessing

	// get percent complete
	$percent_complete = round((learndash_course_get_completed_steps($user_id,$ta_course_id) / $total_course_steps) * 100,0);

	// get list of quizzes
	$quizzes_completed = learndash_get_user_quiz_attempt($user_id,$ta_course_id);
	// for each quiz, log the most recent score & number of attempts
	$quiz_data = array();
	foreach($quizzes_completed as $quiz) {
		$quiz_id = $quiz['quiz'];
		$quiz_data[$quiz_id]['score'] = $quiz['percentage'];
		$quiz_data[$quiz_id]['attempts'] += 1;
	}
	// fill in any incomplete quizzes
	foreach($ta_quiz_ids as $quiz_id) {
		if(!$quiz_data[$quiz_id]) {
			$quiz_data[$quiz_id]['score'] = 'N/A';
			$quiz_data[$quiz_id]['attempts'] = 0;
		}
	}
	// foreach($quiz_data as $quiz_id) {
	// 	// $quiz = get_page_by_path($quiz_slug);	// throws an error
	// 	// if(!$quiz) {
	// 	// 	echo "Error: {$quiz_slug} does not exist.";
	// 	// 	continue;
	// 	// }
	// 	// $quiz_id = $quiz->ID;
	// 	if(in_array($quiz_id,$quizzes_completed)) {
	// 		$quiz_data[$quiz_id]['score'] = $quizzes_completed[$quiz_id]['percentage'];
	// 		$quiz_data[$quiz_id]['attempts'] += 1;
	// 	}
	// }

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
function ta_reports_get_user_data($user,$user_id) {
	// check for sponsored members & get list
	$sponsored_members = pmprosm_getChildren($user_id);
	$users_data = array();
	if($sponsored_members) {
		// generate user data for each sponsored member
		foreach($sponsored_members as $student_id) {
			$student = get_user_by("id",$student_id);
			$users_data[$student_id] = ta_generate_user_data($student,$student_id);
		}
	} else {
		// generate user data for current user only
		$users_data[$user_id] = ta_generate_user_data($user,$user_id);
	}
	return $users_data;
}

/*
 * get the necessary data and send to javascript
*/
// add_action('wp_enqueue_scripts','ta_get_report_data');
// add_action('genesis_entry_content','ta_get_report_data');
add_shortcode('ta_reports_table','ta_get_report_data');
function ta_get_report_data() {
	// if(is_page(6240)) {
		global $current_user;
		// make sure user is logged in
		if($current_user->ID) {
			// enqueue scripts
			wp_enqueue_script(
        'tarep-main-js',
        plugins_url('/assets/js/main.js', __FILE__),
        ['wp-element', 'wp-components', 'jquery'],
        time(),
        true
      );

			// add button and anchor div
			ta_generate_download_button();
			echo '<div class="ta-rep-anchor"></div>';

			// get data
			global $ta_report_section_headers;
			global $ta_course_id;
			$total_course_steps = learndash_get_course_steps_count($ta_course_id);
			$course_sections = learndash_30_get_course_sections($ta_course_id);
			$user = $current_user;
			$user_id = $user->ID;

			// generate user data
			$users_data = ta_reports_get_user_data($user,$user_id);

			// prep data
			$report_data = array(
				"sectionHeaders" => $ta_report_section_headers,
				"usersData" => $users_data,
				"isSponsor" => count($sponsored_members)
			);

			// send data to main.js
			wp_localize_script('tarep-main-js','repObj',$report_data);
		} else {
			echo "Please log in";
		}
	// }
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
