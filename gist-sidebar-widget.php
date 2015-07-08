<?php
/*
Plugin Name: GitHub Gists Sidebar Widget
Plugin URI: http://andrewnorcross.com/plugins
Description: A sidebar widget to display your public gists from GitHub.
Version: 1.2
Author: norcross
Author URI: http://andrewnorcross.com
*/
/*  Copyright 2012 Andrew Norcross

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; version 2 of the License (GPL v2) only.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


// Start up the engine
class Gist_Sidebar_Widget
{

	/**
	 * This is our constructor
	 *
	 * @return FB_Likes_List
	 */
	public function __construct() {
		add_action( 'admin_head',                       array( $this, 'widget_error_css'      )           );
		add_action( 'widgets_init',                     array( $this, 'register_widget'       )           );
	}

	/**
	 * add admin CSS for my error message
	 *
	 * @return [type] [description]
	 */
	public function widget_error_css() {

		// do not load anywhere but admin
		if ( ! is_admin() ) {
			return;
		}

		// echo it out
		echo '<style type="text/css">span.gist_error_message {color:#cd0000;padding-top:5px;display:block;text-align:right;font-weight:bold;}</style>';
	}

	/**
	 * register our custom widgets
	 *
	 * @return void
	 *
	 * @since 1.0
	 */
	public function register_widget() {
		register_widget( 'rkv_ListGistsWidget' );
	}

	/**
	 * fetch the gists
	 *
	 * @param  string $user [description]
	 * @return [type]       [description]
	 */
	public static function fetch_gists( $user = '', $number = 0 ) {

		// check for stored transient. if none present, create one
		if( false === $gists = get_transient( 'public_github_gists_' . $user ) ) {

			// make the API call
			$data   = wp_remote_get ( 'https://api.github.com/users/' . urlencode( $user ) . '/gists?&per_page=' . $number );

			// bail on error
			if ( is_wp_error( $data ) ) {
				// store the blank
				set_transient( 'public_github_gists_' . $user, '', HOUR_IN_SECONDS );

				// and bail
				return false;
			}

			// decode it
			$gists  = json_decode( $data['body'] );

			// bail on empty
			if ( empty( $gists ) ) {
				// store the blank
				set_transient( 'public_github_gists_' . $user, '', HOUR_IN_SECONDS );

				// and bail
				return false;
			}

			// Save a transient to the database
			set_transient( 'public_github_gists_' . $user, $gists, DAY_IN_SECONDS );
		}

		// return the gists
		return $gists;
	}

/// end class
}

// Instantiate our class
$Gist_Sidebar_Widget = new Gist_Sidebar_Widget();


/**
 * construct widget
 */
class rkv_ListGistsWidget extends WP_Widget {

	/**
	 * [__construct description]
	 */
	function __construct() {
		$widget_ops = array( 'classname' => 'list_gists', 'description' => __( 'Displays a list of gists hosted on GitHub' ) );
		parent::__construct( 'list_gists', __( 'Public GitHub Gists' ), $widget_ops );
		$this->alt_option_name = 'list_gists';
	}

	/** @see WP_Widget::widget */
	function widget( $args, $instance ) {

		// bail with no name
		if ( empty( $instance['github_user'] ) ) {
			return;
		}

		// set the username and number as a variable
		$user   = $instance['github_user'];
		$number = ! empty( $instance['gists_num'] ) && $instance['gists_num'] < 101 ? absint( $instance['gists_num'] ) : 10;

		// fetch the gists
		if ( false === $gists = Gist_Sidebar_Widget::fetch_gists( $user, $number ) ) {
			return;
		}

		// set all variable options for plugin display
		$date   = ! empty( $instance['show_date'] ) ? true : false;
		$link   = ! empty( $instance['show_link'] ) ? true : false;
		$text   = ! empty( $instance['link_text'] ) ? $instance['link_text'] : '';

		// start output of actual widget
		echo $args['before_widget'];

		// set the title
		$title  = empty( $instance['title'] ) ? '' : apply_filters( 'widget_title', $instance['title'] );

		// output the title
		if ( ! empty( $title ) ) { echo $args['before_title'] . $title . $args['after_title']; };

		// begin wrap of items
		echo '<ul>';

		// list individual items
		foreach ( $gists as $gist ) {

			// get gist values for display
			$desc   = ! empty( $gist->description ) ? $gist->description : '';
			$gistid = ! empty( $gist->id ) ? $gist->id : '';
			$url    = ! empty( $gist->html_url ) ? $gist->html_url : '';

			// grab date and convert it to a readable format
			$create = ! empty( $gist->created_at ) ? date( 'n/j/Y', strtotime( $gist->created_at ) ) : '';

			// check for missing values and replace them if necessary
			$wtitle = ! empty( $desc ) ? $desc : __( 'Gist ID: ' ) . $gistid;

			// display list of gists
			echo '<li class="gist_item">';

			// show the title linked or not
			if ( ! empty( $url ) ) {
				echo '<a class="gist_title" href="' . esc_url( $url ) . '" target="_blank">' . esc_attr( $wtitle ) . '</a>';
			} else {
				echo esc_attr( $wtitle );
			}

			// include optional date
			if ( ! empty( $date ) && ! empty( $create ) ) {
				echo '<br /><span class="gist_date">' . __( 'Created:' ) . $create . '</span>';
			}

			echo '</li>';
		} // end foreach


		// close the list
		echo '</ul>';

		// display optional github profile link
		if ( ! empty( $link ) ) {

			// figure out the link text
			$wtext  = ! empty( $text ) ? $text : __( 'Github Profile' );

			// and build the link
			$wlink  = 'https://github.com/' . esc_attr( $user );

			// echo it
			echo '<p class="github_link"><a href="' . esc_url( $wlink ) . '" target="_blank">' . esc_attr( $wtext ) . '</a></p>';
		}

		// close the widget
		echo $args['after_widget'];
	}

	/** @see WP_Widget::update */
	function update( $new_instance, $old_instance ) {

		$instance = $old_instance;

		$instance['title']          = sanitize_text_field( $new_instance['title'] );
		$instance['github_user']    = sanitize_text_field( $new_instance['github_user'] );
		$instance['gists_num']      = absint( $new_instance['gists_num'] );
		$instance['link_text']      = sanitize_text_field( $new_instance['link_text'] );
		$instance['show_date']      = ! empty( $new_instance['show_date']) ? 1 : 0;
		$instance['show_link']      = ! empty( $new_instance['show_link']) ? 1 : 0;

		// Remove our saved transient (in case we changed something)
		delete_transient( 'public_github_gists_' . $instance['github_user'] );

		return $instance;
	}

	/** @see WP_Widget::form */
	function form( $instance ) {

		// the items
		$title  = ! empty( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
		$user   = ! empty( $instance['github_user'] ) ? esc_attr( $instance['github_user'] ) : '';
		$number = ! empty( $instance['gists_num'] ) ? absint( $instance['gists_num'] ) : 10;
		$ltext  = ! empty( $instance['link_text'] ) ? esc_attr( $instance['link_text'] ) : __( 'See my GitHub profile' );
		$sdate  = ! empty( $instance['show_date'] ) ? true : false;
		$slink  = ! empty( $instance['show_link'] ) ? true : false;
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Widget Title' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'github_user' ); ?>"><?php _e( 'GitHub username' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'github_user' ); ?>" name="<?php echo $this->get_field_name( 'github_user' ); ?>" type="text" value="<?php echo esc_attr( $user ); ?>" />
			<?php if ( empty ( $user ) ) { echo '<span class="gist_error_message">' . __( 'Username is required!' ) . '</span>'; } ?>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'gists_num' ); ?>"><?php _e( 'Gists to display' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'gists_num' ); ?>" name="<?php echo $this->get_field_name( 'gists_num' ); ?>" type="text" value="<?php echo esc_attr( $number ); ?>" />
		</p>
		<br />
		<p><strong><?php _e( 'Optional Values' ); ?></strong></p>
		<p>
			<input class="checkbox" type="checkbox" <?php checked( $sdate, true ); ?> id="<?php echo $this->get_field_id( 'show_date' ); ?>" name="<?php echo $this->get_field_name( 'show_date' ); ?>" />
			<label for="<?php echo $this->get_field_id( 'show_date' ); ?>"><?php _e( 'Display creation date' ); ?></label>
		</p>

		<p>
			<input class="checkbox" type="checkbox" <?php checked( $slink, true ); ?> id="<?php echo $this->get_field_id( 'show_link' ); ?>" name="<?php echo $this->get_field_name( 'show_link' ); ?>" />
			<label for="<?php echo $this->get_field_id( 'show_link' ); ?>"><?php _e( 'Include link to Github profile' ); ?></label>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'link_text' ); ?>"><?php _e( 'Profile link text' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'link_text' ); ?>" name="<?php echo $this->get_field_name( 'link_text' ); ?>" type="text" value="<?php echo esc_attr( $ltext ); ?>" />
		</p>

	<?php }

} // class