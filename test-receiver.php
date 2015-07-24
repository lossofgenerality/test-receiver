<?php

/*
  Plugin Name: Test Receiver
  Description: Reveive test results
  Version: 1.3.0
  Author: Wayne Allen
  Author URI: http://compclassnotes.com
 */
/*
 * URL for POSTing test results: http://example.com/?results
 * Response is a JSON object with 1 attribute: status which is either OK or ERR
 */

class TestReport {

    public $name;
    public $first;
    public $last;

    function __construct() {
        
    }

}

global $tr_db_version;
$tr_db_version = 1;

register_activation_hook(__FILE__, 'tr_install');
add_action('plugins_loaded', 'tr_update_db_check');

add_action('admin_menu', 'tr_admin_menu');
add_action('admin_enqueue_scripts', 'tr_admin_enqueue_scripts', 20, 1);
add_action('wp_ajax_trajax-summary', 'trajax_summary');
add_action('wp_ajax_trajax-report', 'trajax_report');
add_action('wp_ajax_trajax-full', 'trajax_full');

add_filter('query_vars', 'trajax_query_vars');
add_action('parse_request', 'trajax_parse_request');

function tr_admin_menu() {
    add_menu_page('Test Results', 'Results', 'manage_options', 'tr-results', 'tr_admin_results');
}

function tr_admin_results() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    include 'admin-results.php';
}

function tr_admin_enqueue_scripts($hook) {
    wp_enqueue_script('jquery');
    wp_enqueue_script('chartjs', plugins_url('/js/Chart.min.js', __FILE__));
    wp_enqueue_script('excellentexport', plugins_url('/js/excellentexport.min.js', __FILE__));
}

function tr_install() {
    global $wpdb;
    global $tr_db_version;

    $installed_ver = get_option("tr_db_version", 0);

    if ($installed_ver != $tr_db_version) {
        $table_name = "test_results";
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
        `id` int(11) NOT NULL,
          `message` varchar(500) NOT NULL,
          `submitted` datetime NOT NULL,
          `ip` varchar(45) DEFAULT NULL
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        dbDelta($sql);
        add_option('tr_db_version', $tr_db_version);
    }
}

function tr_update_db_check() {
    global $tr_db_version;
    if (get_site_option('tr_db_version') != $tr_db_version) {
        tr_install();
    }
}

function trajax_query_vars($vars) {
    $vars[] = 'results';
    return $vars;
}

function trajax_parse_request($wp) {
    global $wpdb;
    if (array_key_exists('results', $wp->query_vars)) {
        header('Content-Type: application/json');
        $message = urldecode(file_get_contents('php://input'));
        error_log("CCN: $message");

        date_default_timezone_set('America/Toronto');
        $when = new DateTime();
        $ip = $_SERVER['REMOTE_ADDR'];
        if ($wpdb->insert('test_results', array('message' => $message, 'submitted' => $when->format("Y-m-d H:i:s"), 'ip' => $ip))) {
            wp_mail(array('symbolicexams@gmail.com', 'anna@lossofgenerality.com'), 'Message from CCN', "$message\n$ip");
            echo json_encode(array('status' => 'OK'));
            die;
        } else {
            error_log("CCN: {$wpdb->last_error}");
            wp_mail('wayne@lossofgenerality.com', 'CCN Error', "{$wpdb->last_error}\n\n$message");
            echo json_encode(array('status' => 'ERR'));
            die();
        }
    }
}

function trajax_summary() {
    date_default_timezone_set('America/Toronto');
    header("Content-Type: application/json");

    global $wpdb;
    $sql = "SELECT date(submitted) as d, count(*) as c FROM `test_results` " .
            "where date(submitted) > date_sub(CURDATE(),INTERVAL 30 day) " .
            "group by date(submitted)";

    $r = array();
    $rows = $wpdb->get_results($sql);
    foreach ($rows as $row) {
        $r[] = array('date' => $row->d,
            'count' => $row->c);
    }

    echo json_encode($r);
    exit();
}

function validateDate($postvar) {
    if (array_key_exists($postvar, $_POST)) {
        $userDate = $_POST[$postvar];
    } else {
        $userDate = date('Y-m-d');
    }

    $checktimestamp = strtotime($userDate); //validate date
    if ($checktimestamp == false) {
        $userDate = date('Y-m-d');
    }

    return new DateTime($userDate);
}

function trajax_report() {
    date_default_timezone_set('America/Toronto');
    header("Content-Type: application/json");

    $startDate = validateDate('date1');
    $endDate = validateDate('date2')->add(new DateInterval('P1D'));

    $out = array('start' => $startDate->format("Y-m-d H:i:s"), 'end' => $endDate->format("Y-m-d H:i:s"));
    $out['results'] = getData($startDate, $endDate);

    echo json_encode($out);
    exit();
}

function trajax_full() {
    date_default_timezone_set('America/Toronto');
    header("Content-Type: application/json");

    $startDate = validateDate('date1');
    $endDate = validateDate('date2')->add(new DateInterval('P1D'));

    $data = getData($startDate, $endDate);
    $students = array();
    foreach ($data as $r) {
        if (!array_key_exists($r['student'], $students)) {
            $students[$r['student']] = array('name' => $r['student']);
        }

        if (!array_key_exists($r['test'], $students[$r['student']])) {
            $tr = new TestReport();
            $tr->name = $r['test'];
            $tr->first = $r['score'];
            $tr->last = '';
            $students[$r['student']][$r['test']] = $tr;
        } else {
            $students[$r['student']][$r['test']]->last = $r['score'];
        }
    }

    $out = array('start' => $startDate->format("Y-m-d H:i:s"), 'end' => $endDate->format("Y-m-d H:i:s"));
    $out['results'] = $students;
    echo json_encode($out);
    exit();
}

function getData($startDate, $endDate) {
    global $wpdb;

    $sql = "SELECT message, submitted FROM test_results ";
    $sql .= "WHERE submitted BETWEEN %s AND %s ";
    $sql .= "ORDER BY SUBSTRING_INDEX(message, ',', 1) , SUBSTRING_INDEX(SUBSTRING_INDEX(message, ',', 2), ',', -1), submitted";

    $results = $wpdb->get_results(
            $wpdb->prepare($sql, $startDate->format("Y-m-d"), $endDate->format("Y-m-d")
            )
    );

    if (!$results) {
        return array();
    }

    $seq = 0;
    $cur = "";
    $r = array();
    foreach ($results as $row) {
        $message = str_replace("\\", '', $row->message);
        $message = str_replace("results=", '', $message);
        $message = str_replace("\n", '', $message);
        $message = str_replace("\r", '', $message);

        $b = strpos($message, '{{');
        $beg = substr($message, 0, $b - 1);

        $e = strpos($message, '}}');
        $endDate = rtrim(substr($message, $e + 2), "\"\n\r");

        $mid = substr($message, $b + 1, $e - $b);

        $p = explode(',', $beg);

        array_walk($p, function(&$val, $key) {
            $val = trim($val);
        });

        $q = explode(',', $endDate);
        array_walk($q, function(&$val, $key) {
            $val = trim($val);
        });

        if ($p[0] == '') {
            $p[0] = 'NOID:tracking_number';
        }

        if (strcasecmp($cur, $p[0] . ',' . $p[1]) != 0) {
            $seq = 1;
            $cur = $p[0] . ',' . $p[1];
        } else {
            $seq++;
        }
        $track = "";
        if (count($q) > 5) {
            $track = $q[5];
        }
        $r[] = array('student' => $p[0],
            'test' => $p[1],
            'score' => $p[2],
            'testnumber' => $seq,
            'tracking' => $track,
            'submitted' => $row->submitted);
    }

    return $r;
}
