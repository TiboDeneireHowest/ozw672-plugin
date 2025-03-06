<?php
##########################################################################
# Modules
##########################################################################
require_once "loxberry_web.php";
require_once "loxberry_system.php";

$L = LBSystem::readlanguage("language.ini");
$template_title = "OZW672 Plugin";
$helplink = "http://www.loxwiki.eu:80/x/2wzL";
$helptemplate = "help.html";

##########################################################################
# Navbar
##########################################################################
$navbar[1]['Name'] = 'Status';
$navbar[1]['URL'] = 'index.php';
$navbar[1]['active'] = True;
$navbar[2]['Name'] = 'OZW672 Settings';
$navbar[2]['URL'] = 'ozw672.php';
$navbar[3]['Name'] = 'MQTT Settings';
$navbar[3]['URL'] = 'mqtt.php';
LBWeb::lbheader($template_title, $helplink, $helptemplate);

##########################################################################
# Job & Service Status Functions
##########################################################################
function get_job_status($job_file) {
    return file_exists($job_file) ? 'Active' : 'Inactive';
}

function get_service_pid($service_name) {
    exec("pgrep -f $service_name", $output);
    return count($output) > 0 ? trim($output[0]) : null;
}

function get_process_status($pid) {
    exec("ps -p $pid -o %cpu,%mem,etime", $output);
    return isset($output[1]) ? trim($output[1]) : 'Geen data beschikbaar';
}

function get_mosquitto_status() {
    exec("systemctl status mosquitto", $output);
    return implode("\n", $output);
}

function log_event($message) {
    $log_file = './Log_file.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

function replace_last_line($file_path, $new_line) {
    if (file_exists($file_path)) {
        $file_contents = file($file_path, FILE_IGNORE_NEW_LINES);
        if ($file_contents !== false && count($file_contents) > 0) {
            // Overschrijf de laatste regel
            $file_contents[count($file_contents) - 1] = $new_line;
            $temp_file = tempnam(sys_get_temp_dir(), 'cron');
            if (file_put_contents($temp_file, implode(PHP_EOL, $file_contents) . PHP_EOL) !== false) {
                exec("sudo mv " . escapeshellarg($temp_file) . " " . escapeshellarg($file_path), $output, $return_var);
                if ($return_var === 0) {
                    return true;
                }
            }
        }
    }
    return false;
}

##########################################################################
# Script & MQTT Status
##########################################################################
$cron_job = "/opt/loxberry/system/cron/cron.d/ozw672_cron";
$mqtt_job = "/etc/cron.d/mqtt_script";
$script_status = get_job_status($cron_job);
$mqtt_status = get_job_status($mqtt_job);
$script_pid = get_service_pid('ozw672_script.pl');
$mqtt_pid = get_service_pid('mosquitto');
$script_process_status = $script_pid ? get_process_status($script_pid) : 'N/A';
$mqtt_process_status = $mqtt_pid ? get_process_status($mqtt_pid) : 'N/A';
$mosquitto_status = get_mosquitto_status();

##########################################################################
# Log Viewer
##########################################################################
$log_file = './Log_file.log';
$log_lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$total_lines = count($log_lines);
$visible_lines = array_slice($log_lines, max(0, $total_lines - 100), 100);
$visible_lines = array_reverse($visible_lines);

##########################################################################
# Config File
##########################################################################
$config_file = './ozw672_config.ini';
if (file_exists($config_file)) {
    $config = parse_ini_file($config_file, true);
    $cron_time = trim($config['OZW672']['cron_time']);
} else {
    $cron_time = '*/5 * * * *'; // Default value
}
?>

<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<style>
    .grid { display: flex; flex-wrap: wrap; gap: 20px; }
    .box { flex: 1; min-width: 200px; border: 1px solid #ccc; padding: 20px; border-radius: 5px; text-align: center; }
    .status { padding: 10px; margin-bottom: 10px; border-radius: 5px; }
    .bold { font-weight: bold; }
    .log-error { color: red; }
    .log-warning { color: orange; }
    .log-info { color: green; }
</style>

<div class="grid">
    <div class="box">
        <div class="bold">OZW672 Service</div>
        <div id="script_status" class="status" style="background:<?= $script_status == 'Active' ? '#32DE00' : '#FF0000' ?>; color:black">
            <?= $script_status ?> (PID <?= $script_pid ?: 'N/A' ?>)
        </div>
        <a href="#" onclick="service('start_script');return false;" class="btn ">Start</a>
        <a href="#" onclick="service('stop_script');return false;" class="btn ">Stop</a>
    </div>
    <div class="box">
        <div class="bold">MQTT Service</div>
        <div id="mqtt_status" class="status" style="background:<?= strpos($mosquitto_status, 'active (running)') !== false ? '#32DE00' : '#FF0000' ?>; color:black">
            <?= strpos($mosquitto_status, 'active (running)') !== false ? 'Active' : 'Inactive' ?> (PID <?= $mqtt_pid ?: 'N/A' ?>)
        </div>
        <a href="#" onclick="service('start_mqtt');return false;" class="btn ">Start</a>
        <a href="#" onclick="service('stop_mqtt');return false;" class="btn ">Stop</a>
    </div>
</div>

<h3>Instellingen voor Cronjob</h3>
<form id="cron_form">
    <label for="cron_time">Cron Tijd:</label>
    <input type="text" id="cron_time" name="cron_time" value="<?= htmlspecialchars($cron_time) ?>">
    <button type="submit">Instellen</button>
</form>

<script>
function service(action) {
    fetch('index.php?action=' + action)
    .then(response => response.text())
    .then(data => {
        logEvent(action + ' service');
        setTimeout(() => location.reload(), 2000);
    });
}

function logEvent(message) {
    fetch('index.php?log_event=' + encodeURIComponent(message))
    .then(response => response.text());
}

function updateStatus() {
    fetch('index.php?status')
    .then(response => response.json())
    .then(data => {
        document.getElementById('script_status').innerHTML = data.script_status + ' (PID ' + (data.script_pid || 'N/A') + ')';
        document.getElementById('script_status').style.background = data.script_status == 'Active' ? '#32DE00' : '#FF0000';
        document.getElementById('mqtt_status').innerHTML = data.mqtt_status + ' (PID ' + (data.mqtt_pid || 'N/A') + ')';
        document.getElementById('mqtt_status').style.background = data.mqtt_status == 'Active' ? '#32DE00' : '#FF0000';
    });
}

function updateLogs() {
    fetch('index.php?logs')
    .then(response => response.json())
    .then(data => {
        const logViewer = document.getElementById('log-viewer');
        logViewer.innerHTML = '';
        data.logs.forEach(line => {
            const logClass = line.includes('[error]') ? 'log-error' : (line.includes('[warning]') ? 'log-warning' : 'log-info');
            const logElement = document.createElement('div');
            logElement.className = logClass;
            logElement.textContent = line;
            logViewer.appendChild(logElement);
        });
    });
}

document.getElementById('cron_form').addEventListener('submit', function(event) {
    event.preventDefault();
    const cronTime = document.getElementById('cron_time').value;
    fetch('index.php?action=set_cron&cron_time=' + encodeURIComponent(cronTime))
    .then(response => response.text())
    .then(data => {
        logEvent('Cron job ingesteld: ' + cronTime);
        setTimeout(() => location.reload(), 2000);
    });
});

setInterval(updateStatus, 2000);
setInterval(updateLogs, 2000);
</script>

<h3>Log Viewer</h3>
<div id="log-viewer" style="width: 100%; height: 300px; overflow-y: scroll; border: 1px solid #ccc; padding: 10px;">
    <?php foreach ($visible_lines as $line): ?>
        <div class="<?= strpos($line, '[error]') !== false ? 'log-error' : (strpos($line, '[warning]') !== false ? 'log-warning' : 'log-info') ?>">
            <?= htmlspecialchars($line) ?>
        </div>
    <?php endforeach; ?>
</div>

<?php LBWeb::lbfooter(); ?>

<?php

if (isset($_GET['status'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'script_status' => $script_status,
        'mqtt_status' => $mqtt_status,
        'script_pid' => $script_pid,
        'mqtt_pid' => $mqtt_pid,
        'script_process_status' => $script_process_status,
        'mqtt_process_status' => $mqtt_process_status,
        'mosquitto_status' => $mosquitto_status,
    ]);
    exit;
}

if (isset($_GET['logs'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'logs' => $visible_lines,
    ]);
    exit;
}

// log_event handling
if (isset($_GET['log_event'])) {
    log_event($_GET['log_event']);
    exit;
}

// Handle service actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action == 'start_script') {
        // Nieuwe regel voor de cronjob
        $new_line = '* * * * * loxberry perl /usr/local/bin/httpsrequest.pl';
        if (replace_last_line('/opt/loxberry/system/cron/cron.d/OZW672Plugin', $new_line)) {
            log_event("Successfully replaced the last line with: $new_line");
        } else {
            log_event("Failed to replace the last line");
        }
    } elseif ($action == 'stop_script') {
        // Regel vervangen door bijv. commentaar of lege regel
        $new_line = '# Script disabled';
        if (replace_last_line('/opt/loxberry/system/cron/cron.d/OZW672Plugin', $new_line)) {
            log_event("Successfully replaced the last line with: $new_line");
        } else {
            log_event("Failed to replace the last line");
        }
    } elseif ($action == 'start_mqtt') {
        exec("systemctl start mosquitto");
        log_event('MQTT service gestart');
    } elseif ($action == 'stop_mqtt') {
        exec("systemctl stop mosquitto");
        log_event('MQTT service gestopt');
    } 
    exit;
}
?>