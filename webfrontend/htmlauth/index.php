<?php
require_once "loxberry_web.php";
require_once "loxberry_system.php";

$L = LBSystem::readlanguage("language.ini");
$template_title = "OZW672 Plugin";
$helplink = "http://www.loxwiki.eu:80/x/2wzL";
$helptemplate = "help.html";

$navbar[1]['Name'] = 'Status';
$navbar[1]['URL'] = 'index.php';
$navbar[1]['active'] = True;
LBWeb::lbheader($template_title, $helplink, $helptemplate);

$input_text = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['input_text'])) {
    $input_text ='*  * * * * loxberry        perl /usr/local/bin/test.pl';
    if (replace_last_line('/opt/loxberry/system/cron/cron.d/ozw672-plugin', $input_text)) {
        log_message('info', "Successfully replaced the last line in the file with: $input_text");
    } else {
        log_message('error', "Failed to replace the last line in the file with: $input_text");
    }
}

function replace_last_line($file_path, $new_line) {
    if (file_exists($file_path)) {
        $file_contents = file($file_path, FILE_IGNORE_NEW_LINES);
        if ($file_contents !== false && count($file_contents) > 0) {
            $file_contents[count($file_contents) - 1] = $new_line;
            $temp_file = tempnam(sys_get_temp_dir(), 'cron');
            if (file_put_contents($temp_file, implode(PHP_EOL, $file_contents) . PHP_EOL) !== false) {
                exec("sudo mv " . escapeshellarg($temp_file) . " " . escapeshellarg($file_path), $output, $return_var);
                if ($return_var === 0) {
                    return true;
                } else {
                    log_message('error', "Failed to move temp file to target file: $file_path");
                }
            } else {
                log_message('error', "Failed to write to the temp file: $temp_file");
            }
        } else {
            log_message('error', "Failed to read the file contents or the file is empty: $file_path");
        }
    } else {
        log_message('error', "File does not exist: $file_path");
    }
    return false;
}

function log_message($level, $message) {
    $log_file = __DIR__ . '/log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}
?>

<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<style>
    .container { max-width: 600px; margin: 50px auto; }
    .form-group { margin-bottom: 20px; }
    .form-container { background-color: #f8f9fa; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
    .form-container h3 { margin-bottom: 20px; }
    .result-container { margin-top: 20px; }
</style>

<div class="container">
    <div class="form-container">
        <h3>Enter Text</h3>
        <form method="post">
            <div class="form-group">
                <label for="input_text">Input Text:</label>
                <input type="text" id="input_text" name="input_text" class="form-control" value="<?= $input_text ?>">
            </div>
            <button type="submit" class="btn btn-primary">Submit</button>
        </form>
    </div>
    <?php if ($input_text): ?>
        <div class="result-container">
            <h4>Entered Text:</h4>
            <p><?= $input_text ?></p>
        </div>
    <?php endif; ?>
</div>

<?php LBWeb::lbfooter(); ?>