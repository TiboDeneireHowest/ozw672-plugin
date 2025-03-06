#!/usr/bin/perl
use strict;
use warnings;
use LWP::UserAgent;
use HTTP::Request;
use JSON;

# Flask server URL (replace with your actual local IP)
my $url = "http://192.168.0.69:5000/";

# Data to send (as a Perl hash)
my %data = ("message" => "Hi!");

# Convert Perl hash to JSON
my $json_data = encode_json(\%data);

# Create a UserAgent object
my $ua = LWP::UserAgent->new;

# Create an HTTP POST request
my $req = HTTP::Request->new(POST => $url);
$req->header("Content-Type" => "application/json");
$req->content($json_data);

# Send request and get response
my $res = $ua->request($req);

# Print response
if ($res->is_success) {
    print "Response from Flask: " . $res->decoded_content . "\n";
} else {
    print "HTTP Request failed: " . $res->status_line . "\n";
}
