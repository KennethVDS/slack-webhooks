<?php
// Buffer all upcoming output...
ob_start();

// Send your response.
echo 'File Added';

// Get the size of the output.
$size = ob_get_length();

// Disable compression (in case content length is compressed).
header("Content-Encoding: none");

// Set the content length of the response.
header("Content-Length: {$size}");

// Close the connection.
header("Connection: close");

// Flush all output.
ob_end_flush();
ob_flush();
flush();

// Close current session (if it exists).
if(session_id()) session_write_close();

$channel = $_POST['channel_id'];
$slacktoken = 'SLACK_COMMAND_TOKEN';
$header = array();
$header[] = 'Content-Type: multipart/form-data';
$file = new CurlFile( $_REQUEST['userfile'], 'image/png');

$postitems =  array(
        'token' => $slacktoken,
        'channels' => $channel,
        'file' =>  $file,
        'text' => $_REQUEST['text'],
        'title' => $_REQUEST['title'],
        'filename' => $_REQUEST['name']
    );

$curl = curl_init();
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
curl_setopt($curl, CURLOPT_URL, "https://slack.com/api/files.upload");
curl_setopt($curl, CURLOPT_POST, 1);
curl_setopt($curl, CURLOPT_POSTFIELDS, $postitems);

//Execute curl and store in variable
$data = curl_exec($curl);
var_dump($data);
die();