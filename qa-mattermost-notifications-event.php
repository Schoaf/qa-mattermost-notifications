<?php

/*
  Mattermost Notifications

  File: qa-plugin/mattermost-notifications/qa-mattermost-notifications-event.php
  Version: 0.2
  Date: 2016-08-30
  Description: Event module class for Mattermost notifications plugin
*/

require_once QA_INCLUDE_DIR.'qa-app-posts.php';

class qa_mattermost_notifications_event {

	const MATCH_WILDCARD = '*';
	private $plugindir;

  function load_module($directory, $urltoroot)
  {
    $this->plugindir = $directory;
  }

  public function process_event($event, $userid, $handle, $cookieid, $params)
  {
    switch ($event) {
      case 'q_post':
		  $this->send_mattermost_notification($event, $userid, $handle, $params );
        break;
     /* case 'a_post':
        $parentpost=qa_post_get_full($params['parentid']);

        $this->send_hipchat_notification(
          $this->build_new_answer_message(
            isset($handle) ? $handle : qa_lang('main/anonymous'),
            $parentpost['title'],
            qa_path(qa_q_request($params['parentid'], $parentpost['title']), null, qa_opt('site_url'), null, qa_anchor('A', $params['postid']))
          )
        );
        break;*/
    }
  }
  
	private function send_mattermost_notification($event, $userid, $handle, $params) 
	{
		require_once $this->plugindir . 'Mattermost' . DIRECTORY_SEPARATOR . 'Mattermost.php';
		
		$category_in_question = $params['categoryid'];
		$tags_in_question_string = $params['tags'];
		$tags_in_question = explode( ',', $tags_in_question_string );
		$prefix = 'mattermost_';
		
		$firstField = $prefix.'webhook_url_';
		$index = 0;
		while( qa_opt($firstField.$index) ) 
		{
			$matches_category_filter = true;
			$matches_tags_filter = false;
			
			// check categories:
			// we dont get the category in the post variable so we cannot check it here.
			/*
			$filter_categories_string = qa_opt($prefix.'categories_'.$index);
			if( $filter_categories_string == '*' )
			{
				$matches_category_filter = true;
			}
			else
			{
				$filter_categories = explode( ',', $filter_categories_string );
				
				foreach( $filter_categories as $filter_category )
				{
					if( trim($category_in_question) == $filter_category )
					{
						$matches_category_filter = true;
						break; // we dont need to check any further.
					}
				}
			}
			*/
			
			//only check tags if category did match.
			if( $matches_category_filter == true )
			{
				$filter_tags_string = qa_opt($prefix.'tags_'.$index);
				
				$matches_tags_filter = $this->does_filter_match_post( $filter_tags_string, $tags_in_question );
			}

			// send notification to Mattermost
			if( $matches_category_filter && $matches_tags_filter )
			{
				$webhook_url = qa_opt($prefix.'webhook_url_'.$index);
				$channel_id = qa_opt($prefix.'channel_id_'.$index);
				$bot_name = qa_opt($prefix.'bot_name_'.$index);
				$color = qa_opt($prefix.'color_'.$index);
				$icon_url = qa_opt($prefix.'icon_url_'.$index);
				$pretext = qa_opt($prefix.'pretext_'.$index);
				$username = isset($handle) ? $handle : qa_lang('main/anonymous');
				$user_full_name = $this->get_user_full_name( $handle );
				$user_link = "http://ask.agfahealthcare.com/user/".$username;
				$title = $params['title'];
				$title_link = qa_q_path($params['postid'], $params['title'], true);
				$question_content = $params['text'];
				
				if( $webhook_url )
				{
					$notifier = new Mattermost\Mattermost( $webhook_url );
					try
					{	
						$result = $notifier->message_room($channel_id, $bot_name, $user_full_name, $user_link, $title, $title_link, $question_content, $tags_in_question_string, $color, $icon_url, $pretext );
					}
					catch (Mattermost\HipChat_Exception $e) 
					{
						error_log($e->getMessage());
					}
				}
			}
			
			$index++;
		}
	}

	private function get_user_full_name( $handle )
	{
		$is_user_id = false;
		$userprofiles = qa_db_select_with_pending(qa_db_user_profile_selectspec($handle,$is_user_id));
		$userdisplayhandle = $handle;
		
		if( !isset($handle) )
		{
			return qa_lang('main/anonymous');
		}
		
		if ( isset($userprofiles['name']) && !empty($userprofiles['name']) )
		{
			if(@$userprofiles['name'] != '')
			{
				$userdisplayhandle = @$userprofiles['name'];
			}
		}
		
		return $userdisplayhandle;

		/*
		$emailbody = 'Profile set='.isset($userprofile['name']).' Profile empty='.empty($userprofile['name'])."<br>\n";
		if ( isset($userprofile['name']) && !empty($userprofile['name']) )
		{
			$emailbody.= print_r( $userprofile, true );
		}
		
		$emailbody .= 'ProfileS set='.isset($userprofiles['name']).' ProfileS empty='.empty($userprofiles['name'])."<br>\n";
		if ( isset($userprofiles['name']) && !empty($userprofiles['name']) )
		{
			$emailbody.= print_r( $userprofiles, true );
		}
		$this->category_email_send_email(array(
                        'fromemail' => 'admin@ask.agfahealthcare.com',
                        'fromname'  => 'AskAgfa',
                        'toemail'   => 'andreas.scharf@agfa.com',
                        'toname'    => 'Andreas Scharf',
                        'bcclist'   => '',
                        'subject'   => 'Debug Ask Agfa user name',
                        'body'      => $emailbody,
                        'html'      => false,
            ));
		
		if ( isset($userprofile['name']) && !empty($userprofile['name']) )
		{
			return qa_html($userprofile['name']);
		}
		

		return isset($handle) ? $handle : qa_lang('main/anonymous');;
		*/
	}
	
  private function build_new_question_message($who, $title, $url) {
    return sprintf("%s asked a new question: <a href=\"%s\">\"%s\"</a>. Do you know the answer?", $who, $url, $title);
  }

  private function build_new_answer_message($who, $title, $url) {
    return sprintf("%s answered the question: <a href=\"%s\">\"%s\"</a>.", $who, $url, $title);
  }
  
	private function does_filter_match_post( $filter_tags_string, $tags_in_post )
	{
		if( trim($filter_tags_string) == self::MATCH_WILDCARD )
		{
			return true;
		}
		else
		{
			$filter_tags = explode( ',', $filter_tags_string );
			foreach( $filter_tags as $filter_tag )
			{
				foreach( $tags_in_post as $tag_in_post )
				{
					if( trim($filter_tag) == trim( $tag_in_post) )
					{
						return true; //we have one match, the rest is not important.
					}
				}
			}
		}
	}
	
	// Only for testing. This method can be removed (Andi - 2016-09-07)
	function category_email_send_email($params) {
            if (qa_to_override(__FUNCTION__)) {
                  $args = func_get_args();
                  return qa_call_override(__FUNCTION__, $args);
            }

            require_once QA_INCLUDE_DIR . 'qa-class.phpmailer.php';

            $mailer = new PHPMailer();
            $mailer->CharSet = 'utf-8';

            $mailer->From     = $params['fromemail'];
            $mailer->Sender   = $params['fromemail'];
            $mailer->FromName = $params['fromname'];
            if (isset($params['toemail'])) {
                  $mailer->AddAddress($params['toemail'], $params['toname']);
            }
            $mailer->Subject = $params['subject'];
            $mailer->Body = $params['body'];
            if (isset($params['bcclist'])) {
                  foreach ($params['bcclist'] as $email) {
                        $mailer->AddBCC($email);
                  }
            }

            if ($params['html']) $mailer->IsHTML(true);

            if (qa_opt('smtp_active')) {
                  $mailer->IsSMTP();
                  $mailer->Host = qa_opt('smtp_address');
                  $mailer->Port = qa_opt('smtp_port');

                  if (qa_opt('smtp_secure')) $mailer->SMTPSecure = qa_opt('smtp_secure');

                  if (qa_opt('smtp_authenticate')) {
                        $mailer->SMTPAuth = true;
                        $mailer->Username = qa_opt('smtp_username');
                        $mailer->Password = qa_opt('smtp_password');
                  }
            }
            return $mailer->Send();
      }
}
