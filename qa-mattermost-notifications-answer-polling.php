<?php
/*
  Mattermost Notifications

  File: qa-plugin/mattermost-notifications/qa-mattermost-notifications-answer-polling.php
  Version: 0.5
  Date: 2017-12-10
  Description: Receiver for action button to update the number of answers and views the question currently has.
*/

$data = json_decode(file_get_contents('php://input'), true);

if( is_array( $data ) && is_array($data['context'] ) && isset( $data['context']['question_id'] ) && is_numeric($data['context']['question_id']) )
{
	require_once '../../qa-include/qa-base.php';
	require_once QA_INCLUDE_DIR.'qa-app-posts.php';
	
	$context = $data['context'];
	
	$questionid = $context['question_id'];
	$question = qa_post_get_full($questionid);
	$views = $question['views'];
	$answers = $question['acount'];
	$color = $context['color'];
	if( $answers > 0 )
		$color = '#289E00'; // green = solved

	require_once 'Mattermost' . DIRECTORY_SEPARATOR . 'Mattermost.php';
	
	
	$updated_post = Mattermost\Mattermost::createMattermostMessageAttachment( $context['channel_id'], $context['bot_name'], $context['author_name'], $context['author_icon'], 
																	$context['author_link'], $context['title'], $context['title_link'], $context['message'], $context['tags'], 
																	$context['category'], $color, $context['icon_url'], $context['pretext'], $context['question_id'], 
																	$views, $answers, $context['action_callback_url'] );

	$answersText = 'Currently '.$answers.' answers.';
	if( $answers < 1 )
		$answersText = 'No answers yet.';
	if( $answers == 1 )
		$answersText = 'Currently '.$answers.' answer.';
	$viewsText = '('.$views.' views)';
	$updateMessage = array ( 'update' => array( 'props' => $updated_post ), 'ephemeral_text' => $answersText . '     '.$viewsText . "\n ...updated the post with the new values." );
}
else
{
	$updateMessage = array ( 'ephemeral_text' => 'Error occured. Cannot parse the JSON message' );
}
	header('Content-Type: application/json');
	echo json_encode( $updateMessage );


?>