<?php
/*
Plugin Name: Heptagon Post Addenda
Plugin URI: http://heptagoncreative.com/post-addenda-wp-plugin/
Description: Create disclaimers or other bits of text and add them to the end of your posts.
Author: Dan Sweet
Version: 1.0
Author URI: http://heptagoncreative.com
*/


//Register Post Type Addenda
add_action( 'init', 'addenda_cpt_register' );

function addenda_cpt_register() {
	$labels = array(
		'name'               => _x( 'Addenda', 'post type general name', 'post-addenda' ),
		'singular_name'      => _x( 'Addendum', 'post type singular name', 'post-addenda' ),
		'menu_name'          => _x( 'Post Addenda', 'admin menu', 'post-addenda' ),
		'name_admin_bar'     => _x( 'Addendum', 'add new on admin bar', 'post-addenda' ),
		'add_new'            => _x( 'Add New', 'Addendum', 'post-addenda' ),
		'add_new_item'       => __( 'Add New Addendum', 'post-addenda' ),
		'new_item'           => __( 'New Addendum', 'post-addenda' ),
		'edit_item'          => __( 'Edit Addendum', 'post-addenda' ),
		'view_item'          => __( 'View Addendum', 'post-addenda' ),
		'all_items'          => __( 'All Addenda', 'post-addenda' ),
		'search_items'       => __( 'Search Addenda', 'post-addenda' ),
		'parent_item_colon'  => __( 'Parent Addenda:', 'post-addenda' ),
		'not_found'          => __( 'No Addenda found.', 'post-addenda' ),
		'not_found_in_trash' => __( 'No Addenda found in Trash.', 'post-addenda' ),
	);

	$args = array(
		'labels'             => $labels,
		'menu_icon'			 => 'dashicons-plus-alt',
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'query_var'          => true,
		'rewrite'            => array( 'slug' => 'addenda' ),
		'capability_type'    => 'post',
		'has_archive'        => true,
		'hierarchical'       => false,
		'menu_position'      => null,
		'supports'           => array( 'title', 'editor', )
	);

	register_post_type( 'addenda', $args );
}


// Create meta box to select disclaimer

function postaddenda_add_meta_box() {

	//Get all available post types, including CPTs
	$screens = get_post_types();

	foreach ( $screens as $screen ) {

		//Make sure not to add Addenda to itself
		if ( 'addenda' != $screen ) {

			add_meta_box(
				'post_addenda_chooser',	// id
				__( 'Post Addendum', 'post-addenda' ), // title
				'postaddenda_meta_box_callback',  // callback function
				$screen,  // write screen
				'normal',  // context
				'high' // priority
			);

		}
	}
}
add_action( 'add_meta_boxes', 'postaddenda_add_meta_box' );

// Prints the box content

function postaddenda_meta_box_callback( $post ) {

	// Add a nonce field so we can check for it later
	wp_nonce_field( 'postaddenda_save_meta_box_data', 'postaddenda_meta_box_nonce' );

	// Use get_post_meta() to retrieve an existing value from the database and use the value for the form
	$addenda_choice = get_post_meta( $post->ID, '_postaddenda_choice_value_key', true );

	//Set up page selector
	$args = array(
		'post_type' => 'addenda',
		'posts_per_page' => -1,
	);

	$choices = get_posts( $args ); ?>

		<p>Select an Addendum to display below this post. Create new Addenda at <strong>Post Addenda &gt; Add New</strong>.</p>

		<p style="width: 30%; display: inline-block;">
			<label for="postaddenda_choices"><strong>Choose your Addendum</strong></label>
		</p>

		<p style="display: inline-block; width: 60%;">
			<select style="width: 100%;" name="postaddenda_choices" id="postaddenda_choices">
				<option value="">- None -</option>

				<?php foreach ( $choices as $choice ) {
					$title = $choice->post_title;
					$choiceid = $choice->ID; //Get permalink by ID in frontend
					?>
					<option value="<?php echo $choiceid; ?>" <?php selected( $addenda_choice, $choiceid ); ?> ><?php echo $title; ?></option>
				<?php } ?>
			</select><br />

		</p>

	<?php
		$custom = get_post_custom($post->ID);
		$cb_show_title = $custom['cb-show-title'][0]; ?>

		<p>
			<input type="checkbox" id="cb_show_title" name="cb-show-title" <?php if( $cb_show_title == true ) { ?> checked="checked"<?php } ?> /> <label for="cb-show-title">Display Addendum Title</label>
		</p>
<?php
}

/**
 * When post is saved, saves our custom data
 * @param int $post_id The ID of the post being saved.
 */

function postaddenda_save_meta_box_data( $post_id ) {

	// Verify this came from our screen with proper authorization, bc the save_post action can be triggered at other times

	// Check if our nonce is set
	if( ! isset( $_POST['postaddenda_meta_box_nonce'] ) ) {
		return;
	}

	// Verify that the nonce is valid
	if( ! wp_verify_nonce( $_POST['postaddenda_meta_box_nonce'], 'postaddenda_save_meta_box_data' ) ) {
		return;
	}

	// If this is an autosave, our form has not been submitted, so we don't want to do anything
	if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Check the user's permissions
	if( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {

		if( ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}

	} else {

		if( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
	}

	// Safe to save data

	// Make sure that it is set
	if( ! isset( $_POST['postaddenda_choices'] ) ){
		return;
	}

	$addenda_choice = $_POST['postaddenda_choices'];

	// Update the meta field in the database
	update_post_meta( $post_id, '_postaddenda_choice_value_key', $addenda_choice);
	update_post_meta( $post_id, 'cb-show-title', $_POST['cb-show-title']);
}
add_action( 'save_post', 'postaddenda_save_meta_box_data' );



/**
 * Add custom meta values to end of post
 */

function postaddenda_insert_content( $content ) {
	global $post;

	$addenda_id = get_post_meta( $post->ID, '_postaddenda_choice_value_key', true );

	$addenda_post = get_post( $addenda_id );

	$addenda_content = wpautop( $addenda_post->post_content, true); //retain paragraph formatting

	if( is_single() || is_page() && get_post_meta( $post->ID, '_postaddenda_choice_value_key', true ) ) {
		$content .= '<aside class="addendum">';

		if( get_post_meta($post->ID, 'cb-show-title', true) == true ) {
			$content .= '<h3 class="addenda-title">' . $addenda_post->post_title . '</h3>';
		}

		$content .= $addenda_content;
		$content .= '</aside>';
	}

	return $content;

}
add_filter ( 'the_content', 'postaddenda_insert_content' );



?>
