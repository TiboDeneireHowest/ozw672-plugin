#!/usr/bin/perl
# https://forum.fhem.de/index.php/topic,32866.0.html
# https://github.com/NextDom/plugin-ozw672/blob/master/doc/fr_FR/api.asciidoc
use strict;
use warnings;

use LoxBerry::JSON;
use LoxBerry::IO;
use LoxBerry::System;

use LWP::UserAgent;
use JSON;
use HTTP::Request::Common qw(POST GET);
use Encode qw(encode);
use Time::Piece;
use Net::MQTT::Simple;
use File::Slurp;

$LoxBerry::IO::DEBUG = 0;

# Read configuration files
my $mqtt_config_file = '/opt/loxberry/webfrontend/htmlauth/plugins/ozw672-plugin/mqtt_config.ini';
my $ozw672_config_file = '/opt/loxberry/webfrontend/htmlauth/plugins/ozw672-plugin/ozw672_config.ini';
my $par_config_file = '/opt/loxberry/webfrontend/htmlauth/plugins/ozw672-plugin/ozw672_parameters.txt';
my $log_file = '/opt/loxberry/webfrontend/htmlauth/plugins/ozw672-plugin/Log_file.log';
my $status_file = '/opt/loxberry/webfrontend/htmlauth/plugins/ozw672-plugin/ozw672_status.txt';

# Read MQTT configuration
log_message('INFO', "Reading MQTT configuration from $mqtt_config_file");
my %mqtt_config = read_ini($mqtt_config_file);
my $mqtt_host = $mqtt_config{'MQTT'}{'host'};
my $mqtt_port = $mqtt_config{'MQTT'}{'port'};
my $mqtt_username = $mqtt_config{'MQTT'}{'username'};
my $mqtt_password = $mqtt_config{'MQTT'}{'password'};
my $mqtt_topic = $mqtt_config{'MQTT'}{'topic_prefix'};

# Read OZW672 configuration
log_message('INFO', "Reading OZW672 configuration from $ozw672_config_file");
my %ozw672_config = read_ini($ozw672_config_file);
my $ozw672host = $ozw672_config{'OZW672'}{'host'};
my $ozw672username = $ozw672_config{'OZW672'}{'username'};
my $ozw672password = $ozw672_config{'OZW672'}{'password'};
my $debuglevel = $ozw672_config{'OZW672'}{'debug_level'};

# Read parameter configuration
log_message('INFO', "Reading parameter configuration from $par_config_file");
my @parameterlist = read_file($par_config_file, chomp => 1);
my $first_line = shift @parameterlist;

# Log the first line of par_config.txt
log_message('INFO', "First line of parameter configuration: $first_line");

# Allow unencrypted connection with credentials
$ENV{MQTT_SIMPLE_ALLOW_INSECURE_LOGIN} = 1;

# Connect to broker
log_message('INFO', "Connecting to MQTT broker at $mqtt_host");
my $mqtt = Net::MQTT::Simple->new($mqtt_host);

# Depending if authentication is required, login to the broker
if ($mqtt_username and $mqtt_password) {
    log_message('INFO', "Logging in to MQTT broker with username $mqtt_username");
    $mqtt->login($mqtt_username, $mqtt_password);
}

# Beware: we disable the SSL certificate check for this script.
$ENV{PERL_LWP_SSL_VERIFY_HOSTNAME} = 0;

# Debugging: off=0, medium=3, extensive=5
$debuglevel = 0;

# Define texts for logging.
my ($type, $device) = ('ozw672', 'mygascondensingboilername');

# We substitute the text for the burner's status with an integer, so plots are easier.
# Define which parameter holds the burner's status.
my $parameterstatuskessel = 1898;
my @statuskesselmatrix = (
    ["Aus", 0],
    ["Nachlauf aktiv", 5],
    ["Freigegeben voor TWW", 10],
    ["Freigegeben voor HK", 20],
    ["In Teillastbetrieb voor TWW", 40],
    ["In Teillastbetrieb voor HK", 50],
    ["In Betrieb voor Trinkwasser", 90],
    ["In Betrieb voor Heizkreis", 100],
);

sub trim {
    my $str = $_[0];
    $str =~ s/^\s+|\s+$//g;
    return $str;
}

log_message('INFO', "Script starting");

my $ua = LWP::UserAgent->new;
my $request = HTTP::Request->new(GET => 'http://' . $ozw672host . '/api/auth/login.json?user=' . $ozw672username . '&pwd=' . $ozw672password);
log_message('INFO', "Sending login request to OZW672 at $ozw672host");
my $response = $ua->request($request);
my $decoded = decode_json($response->content);
my $success = $decoded->{'Result'}{'Success'};
my $sessionid = $decoded->{'SessionId'};
log_message('INFO', "Login successful, session ID: $sessionid");

my $i = 0;
my $j = 0;
my $parameterid;
my $dataValue;
my $rightnow;
my $DHW;

eval {
    while (defined($parameterlist[$i])) {
        my @params = split(',', $parameterlist[$i]);
        $parameterid = $params[0];
        $request = HTTP::Request->new(GET => 'http://' . $ozw672host . '/api/menutree/read_datapoint.json?SessionId=' . $sessionid . '&Id=' . $parameterid);
        log_message('INFO', "Sending request for parameter ID $parameterid");
        $response = $ua->request($request);
        $decoded = JSON->new->utf8->decode($response->content);
        $success = $decoded->{'Result'}{'Success'};
        $dataValue = encode('UTF-8', $decoded->{'Data'}{'Value'});
        $params[4] = trim($dataValue);
        log_message('INFO', "Received data for parameter ID $parameterid: $dataValue");

        if ($params[0] == $parameterstatuskessel) {
            $j = 0;
            while (defined($statuskesselmatrix[$j][0])) {
                if ($statuskesselmatrix[$j][0] eq $params[3]) {
                    $params[3] = $statuskesselmatrix[$j][1];
                    log_message('INFO', "Substituting text of statusKessel for parameter ID $parameterid");
                }
                $j++;
            }
        }

        $LoxBerry::IO::mem_sendall = 1;
        $mqtt->retain($mqtt_topic. $params[3] . "/" . $params[2], $params[4]);
        log_message('INFO', "Published data to MQTT topic $mqtt_topic$params[3]/$params[2]: $params[4]");

        $i++;
    }

    $mqtt->disconnect();
    log_message('INFO', "Disconnected from MQTT broker");

    log_message('INFO', "Script ended");

    # Write success to the status file
    open my $status_fh, '>', $status_file or die "Could not open '$status_file' $!";
    print $status_fh "success\n";
    close $status_fh;
};

if ($@) {
    log_message('ERROR', "Script failed: $@");

    # Write failed to the status file
    open my $status_fh, '>', $status_file or die "Could not open '$status_file' $!";
    print $status_fh "failed\n";
    close $status_fh;
}

sub read_ini {
    my ($file) = @_;
    my %config;
    my $section;
    open my $fh, '<', $file or die "Could not open '$file' $!";
    while (my $line = <$fh>) {
        chomp $line;
        if ($line =~ /^\s*\[(.+?)\]\s*$/) {
            $section = $1;
        } elsif ($line =~ /^\s*([^=]+?)\s*=\s*(.*?)\s*$/) {
            $config{$section}{$1} = $2;
        }
    }
    close $fh;
    return %config;
}

sub log_message {
    my ($level, $message) = @_;
    my $timestamp = localtime->strftime('%Y-%m-%d %H:%M:%S');
    open my $log_fh, '>>', $log_file or die "Could not open '$log_file' $!";
    print $log_fh "[$timestamp] [$level] $message\n";
    close $log_fh;
}