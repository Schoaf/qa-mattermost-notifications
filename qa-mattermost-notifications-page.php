<?php
    class qa_mattermost_notifications_page {

	const FIELD_ID_PREFIX = 'mattermost_';
	const FORM_SECTIONS_ID = 'qa-mattermost-sections';
	
		function get_webhook_form_fields()
		{
			// field_id - field-description - default-value
			$default_fields = array( 
								array( 'webhook_url', 'Incomming webhook URI:', 'http://your.mattermost.server/hooks/secretToken' ),
								array( 'channel_id', 'Channel to post in:', 'off-topic' ),
								array( 'bot_name', 'Name of the bot:', 'AskAgfa' ),
								array( 'color', 'Color of message indicator (#000000):', '#289E00' ),
								array( 'icon_url', 'Icon URL for bots thumbnail (optional):', 'http://ask.agfahealthcare.com/qa-theme/q2a_logo_3_v12_small.gif' ),
								array( 'pretext', 'Text introducing the new question:', 'A new question has arrived:' ),
								array( 'tags', 'Include questions with these <strong>tags</strong> only: <br/>(comma separated list, * for no filter)', 'image-area' )
								//array( 'categories', 'Include questions in these <strong>categories</strong> only: <br/>(comma separated list, * for no filter)', 'Image Area' ) //currently not working
								);
			return $default_fields;
		}
		
		function allow_template($template)
		{
			return ($template!='admin');
		}

		function option_default($option) {			
			$idx = 0;
			
			if( empty($option) )
			{
				return null;
			}

			$default_fields = $this->get_webhook_form_fields();
			foreach( $default_fields as $field )
			{
				$field_id = $field[0];
				$prefix = self::FIELD_ID_PREFIX;
				$field_html_identifier = $prefix.$field_id.'_0';
				if( $field_html_identifier == $option )
				{
					$default_value = $field[2];
					return $default_value;
				}
			}
			
			return null;
		}

		function admin_form(&$qa_content)
		{
			//	Process form input
			$ok = null;
			$prefix = self::FIELD_ID_PREFIX;
			$defaults = $this->get_webhook_form_fields();
		
			if (qa_clicked($prefix.'save')) {
				
				$idx = 0;
				while($idx < (int)qa_post_text($prefix.'section_number')) {
					foreach( $defaults as $default_field )
					{
						$field_id = $default_field[0];
						qa_opt( $prefix.$field_id.'_'.$idx, qa_post_text($prefix.$field_id.'_'.$idx ) );
					}
					$idx++;
				}
				
				$ok = qa_lang('admin/options_saved');
			}
			else if (qa_clicked($prefix.'reset')) {
				foreach($_POST as $i => $v) {
					$def = $this->option_default($i);
					if($def !== null) qa_opt($i,$def);
				}
					
				$idx = 0;
				while($idx < (int)qa_post_text($prefix.'section_number')) {
					foreach($defaults as $default_field)
					{
						$field_id = $default_field[0];
						qa_opt( $prefix.$field_id.'_'.$idx, $this->option_default( $prefix.$field_id.'_'.$idx ) ? $this->option_default( $prefix.$field_id.'_'.$idx ) : '' );
					}
					$idx++;
				}

				// reset in case removed

				$idx = 0;
				$first_field_name = $defaults[0][0];
				while($this->option_default($prefix.$first_field_name.'_'.$idx)) {
					foreach($defaults as $default_field)
					{
						$field_id = $default_field[0];
						qa_opt($prefix.$field_id.'_'.$idx,$this->option_default($prefix.$field_id.'_'.$idx));
					}
					$idx++;
				}
				$ok = qa_lang('admin/options_reset');
			}

		// Create the form for display

			$fields = array();
			
			$sections = '<div id="'.self::FORM_SECTIONS_ID.'">';

			$idx = 0;
			$first_field_name = $defaults[0][0];
													
			while(qa_opt($prefix.$first_field_name.'_'.$idx)) {
				$default_fields_string = '';
				foreach( $defaults as $default_field )
				{
					$field_id = $default_field[0];
					$field_name = $default_field[1];
					$default_fields_string.= $this->wrap_table_row_label( $field_name );
					$field_value = qa_html(qa_opt($prefix.$field_id.'_'.$idx));
					$default_fields_string.= $this->wrap_table_row_inputfield( $field_id, $idx, $field_value );
				}
				$sections .= $this->wrap_table_content_with_table_tags( $idx, $default_fields_string );
				$idx++;
			}
			$sections .= '</div>';

			$fields[] = array(
				'type' => 'static',
				'value' => $sections
			);

			$prefix = self::FIELD_ID_PREFIX;
			
			$add_webhook_button = $this->create_ajax_add_webhook_button( $idx );
			
			$fields[] = array(
				'type' 	=> 'static',
				'value' =>	$add_webhook_button
			);
			

			$form['hidden'][self::FIELD_ID_PREFIX.'section_number'] = $idx;

			return array(           
				'ok' => ($ok && !isset($error)) ? $ok : null,
					
				'fields' => $fields,
				
				'hidden' => array(
							self::FIELD_ID_PREFIX.'section_number' => $idx
				),
				 
				'buttons' => array(
					array(
					'label' => qa_lang_html('main/save_button'),
					'tags' => 'NAME="mattermost_save"',
					),
					array(
					'label' => qa_lang_html('admin/reset_options_button'),
					'tags' => 'NAME="mattermost_reset"',
					),
				),
			);
		}
		
		function wrap_table_content_with_table_tags( $index, $table_content )
		{
			$table = '<table id="qa-mattermost-section-table-'.$index.'" width="100%" class="qa-form-tall-table">';
			$table .= $this->wrap_table_row_label( '<h3>Webhook</h3>');
			$table .= $table_content;
			$table .= '</table><hr/>';
			return $table;
		}
		
		function wrap_table_row_label( $content )
		{
			$table_row = '<tr>';
			$table_row.= 	'<td class="qa-form-tall-label">';
			$table_row.= 		$content;
			$table_row.= 	'</td>';
			$table_row.= '</tr>';
			return $table_row;
		}
		
		function wrap_table_row_inputfield( $field_id, $index, $field_value )
		{
			$prefix = self::FIELD_ID_PREFIX;
			$table_row = '<tr>';
			$table_row.= 	'<td class="qa-form-tall-data">';
			$table_row.= 		'<input class="qa-form-tall-text form-control" type="text" id="'.$prefix.$field_id.'_'.$index.'" name="'.$prefix.$field_id.'_'.$index.'" value="'.$field_value.'" />';
			$table_row.= 	'</td>';
			$table_row.= '</tr>';
			return $table_row;
		}
		
		function create_ajax_add_webhook_button($index)
		{
			$prefix = self::FIELD_ID_PREFIX;
			$defaults = $this->get_webhook_form_fields();
			
			$default_fields_string = '';
			foreach( $defaults as $default_field )
			{
				$field_id = $default_field[0];
				$field_value = $default_field[1];
				$default_fields_string.= $this->wrap_table_row_label( $field_value );
				$field_indx = '\'+next_'.$prefix.'section+\'';
				$default_fields_string.= $this->wrap_table_row_inputfield( $field_id, $field_indx, '');
			}
			$table = $this->wrap_table_content_with_table_tags( '\'+next_'.$prefix.'section+\'', $default_fields_string );
			$button = '
<script>
	var next_'.$prefix.'section = '.$index.'; 
	function addMattermostSection(){
		jQuery("#qa-mattermost-sections").append(\''.$table.'\');
		next_'.$prefix.'section++;
		jQuery("input[name='.$prefix.'section_number]").val(next_'.$prefix.'section);
	}
</script>
<input type="button" value="Add Webhook" onclick="addMattermostSection()">';
			
			return $button;
		}
    }
