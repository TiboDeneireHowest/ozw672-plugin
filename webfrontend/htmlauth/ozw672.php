<?php
require_once "loxberry_web.php";
require_once "loxberry_system.php";

// This will read your language files to the array $L
$L = LBSystem::readlanguage("language.ini");
$template_title = "OZW672 Plugin";
$helplink = "http://www.loxwiki.eu:80/x/2wzL";
$helptemplate = "help.html";

// The Navigation Bar
$navbar[1]['Name'] = 'Status';
$navbar[1]['URL'] = 'index.php';

$navbar[2]['Name'] = 'OZW672 Settings';
$navbar[2]['URL'] = 'ozw672.php';
$navbar[2]['active'] = True;

$navbar[3]['Name'] = 'MQTT Settings';
$navbar[3]['URL'] = 'mqtt.php';

// Now output the header, it will include your navigation bar
LBWeb::lbheader($template_title, $helplink, $helptemplate);

// Define variables
$config_file = './ozw672_config.ini';
$parameters_file = './ozw672_parameters.txt';
$log_file = './Log_file.log';

// Define default values
$default_values = [
    'host' => '192.168.1.1',
    'username' => 'admin',
    'password' => 'admin',
    'debug_level' => 1,
    'parameters' => "id, name, type, location\n3859, Actual value room temp, temp, zaal1\n3385, Actual value room temp, temp, zaal2\n1485, Actual value room temp, temp, zaal3",
    'time' => '30'
];

// Logging function
function log_message($level, $message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Check if the config file exists and read the values
if (file_exists($config_file)) {
    $config = parse_ini_file($config_file, true);
    $ozw672_host = $config['OZW672']['host'];
    $ozw672_username = $config['OZW672']['username'];
    $ozw672_password = $config['OZW672']['password'];
    $debug_level = $config['OZW672']['debug_level'];
    $time = $config['OZW672']['time'];
} else {
    // Set default values if the config file does not exist
    $ozw672_host = $default_values['host'];
    $ozw672_username = $default_values['username'];
    $ozw672_password = $default_values['password'];
    $debug_level = $default_values['debug_level'];
    $cron_time = $default_values['time'];

    // Write the default values to the config file
    $config_data = "[OZW672]\nhost=$ozw672_host\nusername=$ozw672_username\npassword=$ozw672_password\ndebug_level=$debug_level\ncron_time=$cron_time\n";
    file_put_contents($config_file, $config_data);
    log_message('info', 'Default configuration file created.');
}

// Check if the parameters file exists and read the values
if (file_exists($parameters_file)) {
    $parameters = file_get_contents($parameters_file);
} else {
    // Set default values if the parameters file does not exist
    $parameters = $default_values['parameters'];

    // Write the default values to the parameters file
    file_put_contents($parameters_file, $parameters);
    log_message('info', 'Default parameters file created.');
}

// Initialize success message
$success_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_parameters'])) {
        $parameters = $_POST['parameters'];
        file_put_contents($parameters_file, $parameters);
        $success_message = 'Parameters have been successfully saved.';
        log_message('info', 'Parameters have been successfully saved.');
    } elseif (isset($_POST['save_settings'])) {
        $ozw672_host = $_POST['ozw672_host'];
        $ozw672_username = $_POST['ozw672_username'];
        $ozw672_password = $_POST['ozw672_password'];
        $debug_level = $_POST['debug_level'];
        $cron_time = $_POST['cron_time'];

        // Log the received data
        log_message('info', "Received data: OZW672 Host: $ozw672_host, Username: $ozw672_username, Debug Level: $debug_level, Cron Time: $cron_time");

        // Check if values have changed
        $config_changed = false;
        if ($ozw672_host !== $config['OZW672']['host'] ||
            $ozw672_username !== $config['OZW672']['username'] ||
            $ozw672_password !== $config['OZW672']['password'] ||
            $debug_level !== $config['OZW672']['debug_level'] ||
            $cron_time !== $config['OZW672']['cron_time']) {
            $config_changed = true;
        }

        if ($config_changed) {
            // Update the configuration values
            $config['OZW672']['host'] = $ozw672_host;
            $config['OZW672']['username'] = $ozw672_username;
            $config['OZW672']['password'] = $ozw672_password;
            $config['OZW672']['debug_level'] = $debug_level;
            $config['OZW672']['cron_time'] = $cron_time;

            // Write the updated configuration back to the INI file
            $new_content = '';
            foreach ($config as $section => $values) {
                $new_content .= "[$section]\n";
                foreach ($values as $key => $value) {
                    $new_content .= "$key=$value\n";
                }
            }
            file_put_contents($config_file, $new_content);

            // Set success message
            $success_message = 'Settings have been successfully saved.';
            log_message('info', 'Settings have been successfully saved.');
        }
    }

    // Update the form values with the saved values
    $config = parse_ini_file($config_file, true);
    $ozw672_host = $config['OZW672']['host'];
    $ozw672_username = $config['OZW672']['username'];
    $ozw672_password = $config['OZW672']['password'];
    $debug_level = $config['OZW672']['debug_level'];
    $cron_time = $config['OZW672']['cron_time'];
    $parameters = file_get_contents($parameters_file);
}

?>

<form method="post">
    <h3>Settings</h3>
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
    <div class="form-group">
        <label for="ozw672_host">OZW672 Host:</label>
        <input type="text" id="ozw672_host" name="ozw672_host" value="<?= htmlspecialchars($ozw672_host) ?>" class="form-control" required>
    </div>

    <div class="form-group">
        <label for="ozw672_username">OZW672 Username:</label>
        <input type="text" id="ozw672_username" name="ozw672_username" value="<?= htmlspecialchars($ozw672_username) ?>" class="form-control" required>
    </div>

    <div class="form-group">
        <label for="ozw672_password">OZW672 Password:</label>
        <input type="password" id="ozw672_password" name="ozw672_password" value="<?= htmlspecialchars($ozw672_password) ?>" class="form-control" required>
    </div>

    <div class="form-group">
        <label for="debug_level">Debug Level:</label>
        <input type="number" id="debug_level" name="debug_level" value="<?= htmlspecialchars($debug_level) ?>" class="form-control" required>
    </div>

    <div class="form-group">
        <label for="cron_time">Cron Time:</label>
        <input type="text" id="cron_time" name="cron_time" value="<?= htmlspecialchars($cron_time) ?>" class="form-control" required>
    </div>

    <button type="submit" name="save_settings" class="btn btn-primary">Save Settings</button>

    <h3>Parameters</h3>
    <div class="form-group">
        <textarea id="parameters" name="parameters" class="form-control" rows="10"><?= htmlspecialchars($parameters) ?></textarea>
    </div>

    <button type="submit" name="save_parameters" class="btn btn-primary">Save Parameters</button>
</form>

<?php  
// Finally print the footer  
LBWeb::lbfooter();
?> 