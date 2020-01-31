<?php
// For including our Slack interface classes
// and using libraries through Composer
require_once 'vendor/autoload.php';
require_once 'slack-interface/class-slack.php';
require_once 'slack-interface/class-slack-access.php';
require_once 'slack-interface/class-slack-api-exception.php';
require_once './slack-interface/credentials.php';
use Slack_Interface\Slack;
use Slack_Interface\Slack_API_Exception;

//
// HELPER FUNCTIONS
//

/**
 * Initializes the Slack handler object, loading the authentication
 * information from a text file. If the text file is not present,
 * the Slack handler is initialized in a non-authenticated state.
 *
 * @return Slack    The Slack interface object
 */
function initialize_slack_interface() {
	// Read the access data from a text file
	if ( file_exists( 'access.txt' ) ) {
		$access_string = file_get_contents( 'access.txt' );
	} else {
		$access_string = '{}';
	}

	// Decode the access data into a parameter array
	$access_data = json_decode( $access_string, true );

	$slack = new Slack( $access_data );

	// Register slash commands
	$slack->register_slash_command( '/joke', 'slack_command_joke' );

	return $slack;
}

/**
 * Executes an application action (e.g. 'send_notification').
 *
 * @param Slack  $slack     The Slack interface object
 * @param string $action    The id of the action to execute
 *
 * @return string   A result message to show to the user
 */
function do_action( $slack, $action ) {
	$result_message = '';

	switch ( $action ) {

		// Handles the OAuth callback by exchanging the access code to
		// a valid token and saving it in a file
		case 'oauth':
			$code = $_GET['code'];

			// Exchange code to valid access token
			try {
				$access = $slack->do_oauth( $code );
				if ( $access ) {
					file_put_contents( 'access.txt', $access->to_json() );
					$result_message = 'The application was successfully added to your Slack channel';
				}
			} catch ( Slack_API_Exception $e ) {
				$result_message = $e->getMessage();
			}
			break;

		// Sends a notification to Slack
		case 'send_notification':
			$message = isset( $_REQUEST['text'] ) ? $_REQUEST['text'] : 'Hello!';
			$attachments = array(
				array(
					'fallback' => 'Your people need you!',
			 
					'title' => $_REQUEST['title'],
			 
					'text' => $_REQUEST['text'],
			 
					'color' => $_REQUEST['color'],
			 
					'fields' => array(
						array(
							'title' => $_REQUEST['subtitle'],
							'value' => ':bomb:' . $_REQUEST['quantity'],
							'short' => true
						)
					),
					"author_name" => $_REQUEST['author'],
					'image_url' => $_REQUEST['link']
				)
			);

			// Use cURL if a file is attached 
            if (isset($_REQUEST['userfile'])) {
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
			}
			
			try {
				$slack->send_notification( $attachments );
				$result_message = 'Notification sent to Slack channel.';
			} catch ( Slack_API_Exception $e ) {
				$result_message = $e->getMessage();
			}
			break;
			// Responds to a Slack slash command. Notice that commands are registered
			// at Slack initialization.
		case 'command':
			$slack->do_slash_command();
			break;

			default:
				break;
	}
	return $result_message;
}

/**
 * A simple slash command that returns a random joke to the Slack channel.
 *
 * @return array        A data array to return to Slack
 */
function slack_command_joke() {
	$jokes = array(
		"The box said 'Requires Windows Vista or better.' So I installed LINUX.",
		"Bugs come in through open Windows.",
		"Unix is user friendly. It’s just selective about who its friends are.",
		"Computers are like air conditioners: they stop working when you open Windows.",
		"I would love to change the world, but they won’t give me the source code.",
		"Programming today is a race between software engineers striving to build bigger and better idiot-proof programs, 
		and the Universe trying to produce bigger and better idiots. So far, the Universe is winning."
	);

	$joke_number = rand( 0, count( $jokes ) - 1 );

	return array(
		'response_type' => 'in_channel',
		'text' => $jokes[$joke_number],
	);
}

//
// MAIN FUNCTIONALITY
//

// Setup the Slack handler
$slack = initialize_slack_interface();

// If an action was passed, execute it before rendering the page layout
$result_message = '';
if ( isset( $_REQUEST['action'] ) ) {
	$action = $_REQUEST['action'];
	$result_message = do_action( $slack, $action );
}

//
// PAGE LAYOUT
//
?>

<html>
	<head>
		<title>Slackhouse</title>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
		<link rel="stylesheet" href="styles.css">
	</head>

	<body>

		<?php if ( $result_message ) : ?>
			<p class="notification">
				<?php echo $result_message; ?>
			</p>
		<?php endif; ?>

		<?php if ( $slack->is_authenticated() ) : ?>
		<div class="container">
			<form action="" method="post" enctype="multipart/form-data">
				<input type="hidden" name="action" value="send_notification"/>
					<div class="form-row">
						<div class="form-group col-md-6">
							<label for="inputEmail4">Title: </label>
							<input type="title"  name="title" class="form-control" placeholder="Type here">
						</div>
						<div class="form-group col-md-6">
							<label for="inputPassword4">Author: </label>
							<input type="text" name="author" class="form-control" placeholder="Your name">
						</div>
					</div>
					<div class="form-group">
						<label for="inputAddress">Message: </label>
						<input type="text" name="text" class="form-control" placeholder="Type here">
					</div>
					<div class="form-group">
						<label for="inputAddress2">Image URL: </label>
						<input type="link" name="link" class="form-control" placeholder="Paste here">
					</div>
					<div class="form-row">
						<div class="form-group col-md-6">
							<label for="inputCity">Subtitle: </label>
							<input type="text" name="subtitle" class="form-control" placeholder="Type here">
						</div>
						<div class="form-group col-md-4">
							<label>Level: </label>
							<input type="number" name="quantity" min="1" max="5" placeholder="rate 1-5">
						</div>
						<div class="form-group col-md-2">
							<label for="inputZip">Color: </label>
							<input type="color" name="color" value="#ffff66">
						</div>
					</div>
				<div class="form-row">
					<div class="form-group col-md-4">
						<input type="file" name="userfile" class="form-control-file" />
						<button type="reset" name="reset" class="btn btn-warning">Reset</button>
						<button type="submit" name="send_picture" value="send"class="btn btn-primary">Send</button>
					</div>
				</div>
			</form>
		</div>
		<?php else : ?>
			<p>
				<a href="https://slack.com/oauth/authorize?scope=incoming-webhook,commands&client_id=<?php echo $slack->get_client_id(); ?>"><img alt="Add to Slack" height="40" width="139" src="https://platform.slack-edge.com/img/add_to_slack.png" srcset="https://platform.slack-edge.com/img/add_to_slack.png 1x, https://platform.slack-edge.com/img/add_to_slack@2x.png 2x"></a>
			</p>
		<?php endif; ?>
	</body>
</html>