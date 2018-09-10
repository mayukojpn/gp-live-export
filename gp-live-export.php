<?php
/*
Plugin Name: GP Live Export
Description: Convert your WordPress site into all-in-one translation platform of editor and sandbox. GlotPress plugin Required.
Author:      Mayo Moriyama
Version:     0.3

*/

class GPLE_Options_Page {

	private $message = [];
	private $project_name = '';

  /**
   * Constructor.
   */
  function __construct() {

		include 'lib/glotpress/locales.php';

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );

  }

  /**
   * Registers a new settings page under Settings.
   */
  function admin_menu() {

      add_options_page(
					esc_html__( 'GP Live Export', 'gp-live-export' ),
          esc_html__( 'GP Live Export', 'gp-live-export' ),
          'manage_options',
          'gp-live-export',
          array(
              $this,
              'page_rending'
          )
      );
  }
	/**
	 * Get a translation set for a project.
	 *
	 * @param string $project Project path
	 * @param string $locale Locale slug
	 * @param string $set Set slug
	 * @return GP_Translation_Set|WP_Error Translation set if available, error otherwise.
	 */
	protected function get_translation_set( $project, $locale, $set = 'default' ) {
		$this->project = GP::$project->by_path( $project );
		if ( ! $this->project ) {
			return new WP_Error( 'gp_set_no_project', __( 'Project not found!', 'glotpress' ) );
		}
		$this->locale = GP_Locales::by_slug( $locale );
		if ( ! $this->locale ) {
			return new WP_Error( 'gp_set_no_locale', __( 'Locale not found!', 'glotpress' ) );
		}
		$this->translation_set = GP::$translation_set->by_project_id_slug_and_locale( $this->project->id, $set, $this->locale->slug );
		if ( ! $this->translation_set ) {
			return new WP_Error( 'gp_set_not_found', __( 'Translation set not found!', 'glotpress' ) );
		}
		return $this->translation_set;
	}

	function admin_init () {
		if ( !is_plugin_active( 'glotpress/glotpress.php' ) ) :

			$this->message[] = array(
				'status'  => 'error',
				'content' => esc_html__( 'GlotPress plugin needs to be installed and activated', 'gp-live-export' ),
				'path'    => 'GP Live Export'
			);
			add_action( 'admin_notices', array( $this, 'admin_notice' ) );
			remove_submenu_page( 'options-general.php', 'gp-live-export' );

		else:

			if ( isset( $_GET["page"]    )
				&& isset( $_GET["project"] )
				&& isset( $_GET["locale"]  )
				&& $_GET["page"] == 'gp-live-export' ) :
				$set = ( isset( $_GET["set"] ) ) ? $_GET["set"] : 'default';
				$this->export( $_GET["project"], $_GET["locale"], $set );
			endif;

		endif;
	}


  /**
   * Export translation file.
   */
  function export( $project, $locale, $set = 'default' ) {

		$translation_set = $this->get_translation_set( $project, $locale, $set );
		if ( is_wp_error( $translation_set ) ) {
			$this->message[] = array(
				'status'  => 'error',
				'content' => $translation_set->get_error_message(),
				'path'    => $project
			);
			add_action( 'admin_notices', array( $this, 'admin_notice' ) );
			return;
		}
		$filters = array( 'status' => 'current_or_waiting_or_fuzzy_or_untranslated' );
		$entries = GP::$translation->for_translation( $this->project, $translation_set, 'no-limit', $filters );

		$types = array( 'po', 'mo' );

		foreach( $types as $type ) :

			$format = gp_array_get( GP::$formats, $type, null );

			if ( ! $format ) :
				$this->message[] = array(
					'status'  => 'error',
					'content' => __( 'No such format', 'glotpress' ),
					'path'    => $project
				);
				add_action( 'admin_notices', array( $this, 'admin_notice' ) );

			else:
				$print = $format->print_exported_file( $this->project, $this->locale, $translation_set, $entries );
				$name  = $this->get_file_name( $project, GP_Locales::by_slug( $locale )->wp_locale, $type );
				$path  = path_join( WP_LANG_DIR, $name );
				$save  = $this->file_save( $print, $path );

				if( is_wp_error( $save ) ) :
					echo $save->get_error_message();
					$this->message[] = array(
						'status'  => 'error',
						'content' => $save->get_error_message(),
						'path'    => $name
					);
					add_action( 'admin_notices', array( $this, 'admin_notice' ) );

				else :
					$this->message[] = array(
						'status' => 'success',
						'path'   => $path
					);
					$this->project_name = $project;
					add_action( 'admin_notices', array( $this, 'admin_notice' ) );

				endif;
			endif;
		endforeach;
  }

	/**
	 * Get a translation set for a project.
	 *
	 * @param string $print File content
	 * @param string $path  Target file path
	 * @return GP_Translation_Set|WP_Error Translation set if available, error otherwise.
	 */
	function file_save ( $print = '', $path = '' ) {
		if ( empty( $print ) || empty( $path ) ) {
			return new WP_Error( 'gp_live_export_no_param', __( 'No content or file path', 'gp-live-export' ) );
		}
		$file_path = path_join( WP_LANG_DIR, $path );

		if ( !$file = fopen( $path, 'w' ) ) {
			return new WP_Error( 'gp_live_export_not_open', __( 'Cannot open file', 'gp-live-export' ) );
		}

		if ( fwrite( $file, $print ) === FALSE ) {
			return new WP_Error( 'gp_live_export_not_rewritable', __( 'Cannot write to file', 'gp-live-export' ) );
    }


		return fclose( $file );

	}

	/**
	 * Generate a file path to save a file.
	 *
	 * @param string $name   File name
	 * @param string $locale File locale
	 * @param string type    File type
	 * @return $path File path to save
	 */
	function get_file_name ( $name = '', $locale = '', $type = 'po' ) {
		if ( empty( $locale ) ) {
			$locale = get_user_locale();
		}
		$name = str_replace( 'wp-', '', $name);
		$name = "$name-$locale.$type";
		return $name;
	}

	/**
	 * Settings page display callback.
	 */
	function page_rending () {
		echo '<div class="wrap">'
				.'<h1 class="wp-heading-inline">';
				esc_html_e( 'GP Live Export', 'gp-live-export' );
		echo '</h1>';

		echo '<hr class="wp-header-end">';

			$projects = GP::$project->top_level();

			if ( empty( $projects ) ) {

				printf(
					/* Translators: %s: GlotPress dashboard link */
					esc_html__( 'Let\'s go to your %s and set up your first project!', 'gp-live-export' ),
					sprintf (
						'<a href="%1$s">%2$s</a>',
						gp_url_public_root(),
						esc_html__( 'GlotPress dashboard', 'gp-live-export' )
						)
				);
				echo '<br>';
				printf(
					/* Translators: %s: page link */
					esc_html__( 'For setting up WordPress core/theme/plugin translation, you can find more instruction on %s.', 'gp-live-export' ),
					sprintf (
						'<a href="%1$s">%2$s</a>',
						esc_html__( 'https://wordpress.org', 'gp-live-export' ) . '/plugins/gp-live-export/#faq',
						esc_html__( 'WordPress.org Plugin page', 'gp-live-export' )
						)
				);

			} else {

				echo '<table class="wp-list-table widefat"><thead><tr>';
				echo '<th>'. esc_html__( 'Parent', 'gp-live-export' ) .'</th>';
				echo '<th>'. esc_html__( 'Name',   'gp-live-export' ) .'</th>';
				echo '<th>'. esc_html__( 'Export', 'gp-live-export' ) .'</th>';
				echo '</tr></thead><tbody>';

				foreach ( $projects as $project ):

					echo '<tr><td>'.__( 'N/A', 'gp-live-export' ).'</td><td>';
					gp_link_project( $project->path, esc_html( $project->name ) );
					echo '</td><td>';
						$this->gp_link_export_project( $project );
					echo '</td></tr>';

					if ( $sub_projects = $project->sub_projects() ) {
						foreach ( $sub_projects as $sub_project ):
							echo
								'<tr>'
									.'<td>'.$project->name.'</td>'
									.'<td>'
									;
									gp_link_project( $sub_project->path, esc_html( $sub_project->name ) );
							echo
									'</td>'
									.'<td>';
									$this->gp_link_export_project ( $sub_project );
							echo '</td>'
								.'</tr>'
								;
						endforeach;
					}

				endforeach;

				echo '</tbody></table>';
			} // endif;

			echo '</div>';
	}

	/**
	 * Generate list of translation project.
	 */
	function gp_link_export_project ( $project ) {
		if ( $translation_sets = GP::$translation_set->by_project_id( $project->id ) ) :

			$project_path = $project->path;

			foreach ( $translation_sets as $translation_set ):

				$admin_url = 'options-general.php?page=gp-live-export';
				$admin_url = ( $project = $project_path           ) ? $admin_url.'&project='.$project : $admin_url;
				$admin_url = ( $locale = $translation_set->locale ) ? $admin_url.'&locale='.$locale   : $admin_url;
				$admin_url = ( $set = $translation_set->slug      ) ? $admin_url.'&set='.$set         : $admin_url;
				printf ( '<a href="%1$s" class="button">%2$s</a>',
				esc_url ( admin_url ( $admin_url ) ),
				GP_Locales::by_slug( $locale )->wp_locale . ' ('.$set.')'
			 );
			endforeach;

		else :
			esc_html_e( 'No translation set found.', 'gp-live-export' );

		endif;

	}

	/**
	 * Display notification on Admin screen.
	 */
	function admin_notice() {
		foreach ( $this->message as $message ) {
			?>
			<div class="notice notice-<?php echo $message['status']; ?> is-dismissible">
					<p><?php
					switch ( $message['status'] ) {
						case 'error':
							printf(
								/* Translators: %1$s: error sentence, %2$s: project name */
								'%1$s: %2$s',
								$message['content'],
								'<b>' . $message['path'] . '</b>'
							);
							break;
						case 'success':
							printf(
								esc_html__( "%s has been exported!", 'gp-live-export' ),
								'<b>' . $message['path'] . '</b>'
							);
							break;
					}
					?></p>
			</div>
			<?php
		}
	}
}

new GPLE_Options_Page;
