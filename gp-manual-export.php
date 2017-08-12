<?php
/*
Plugin Name: GP Manual Export
*/

class GP_Manual_Export {

	public $export_project = '';

    function __construct() {

				add_action( 'admin_menu', array( $this, 'admin_menu' ) );

				if ( isset( $_GET["gp_export_project"] ) && isset( $_GET["locale"] ) ) {
					$this->export();
				}
    }

    function admin_menu() {
        add_options_page(
						__( 'GP Live Export', 'gp-manual-export' ),
            __( 'GP Live Export', 'gp-manual-export' ),
            'manage_options',
            'gp-manual-export',
            array(
                $this,
                'page_rending'
            )
        );
    }

    function export() {

				$project_path = $_GET["gp_export_project"];
				$locale = $_GET["locale"];

				$this->export_project = $project_path;

				$project_name = explode("/", $project_path);
				$project_name[0] = str_replace( 'wp-', '', $project_name[0]);

				$path = path_join( WP_LANG_DIR, $project_path );

				$path = WP_LANG_DIR .'/'.$project_name[0].'/'. $project_name[1].'-'.$locale;
				exec ( "wp glotpress translation-set export $project_path $locale --format=po > $path.po" );
				exec ( "wp glotpress translation-set export $project_path $locale --format=mo > $path.mo" );
				add_action( 'admin_notices', array( $this, 'admin_notice__success' ) );

    }
		function page_rending () {
			require_once(ABSPATH . 'wp-admin/admin-header.php');
			echo '<div class="wrap">'
					.'<h1 class="wp-heading-inline">';
					_e( 'GP Live Export' );
			echo '</h1>';

			echo '<hr class="wp-header-end">';

				$projects = GP::$project->top_level();

				if ( !empty( $projects ) ) :

					echo '<table class="wp-list-table widefat"><thead><tr>';
					echo '<th>'.__( 'Parent' ).'</th>';
					echo '<th>'.__( 'Name' ).'</th>';
					echo '<th>'.__( 'Export' ).'</th>';
					echo '</tr></thead><tbody>';

					foreach ( $projects as $project ):

						echo '<tr><td>'.__( 'N/A' ).'</td><td>';
						gp_link_project( $project->path, esc_html( $project->name ) );
						echo '</td><td>';
							$this->get_export_project_link( $project );
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
										$this->get_export_project_link ( $sub_project );
								echo '</td>'
									.'</tr>'
									;
							endforeach;
						}

					endforeach;

					echo '</tbody></table>';
				endif;
				echo '</div>';
				include( ABSPATH . 'wp-admin/admin-footer.php' );
		}

		function get_export_project_link ( $project ) {
			if ( $translation_sets = GP::$translation_set->by_project_id( $project->id ) ) :

				foreach ( $translation_sets as $translation_set ):
					$locale    = $translation_set->locale;
					$admin_url = sprintf (
						'options-general.php?page=gp-manual-export&locale=%1$s&gp_export_project=%2$s',
							$locale,
							$project->path
					);

					printf ( '<a href="%1$s" class="button">%2$s</a>',
					esc_url ( admin_url ( $admin_url ) ),
					$locale
				 );

				endforeach;
			else :
				_e( 'No translation set found.', 'gp-manual-export' );
			endif;

		}
		function admin_notice__success() {
		    ?>
		    <div class="notice notice-success is-dismissible">
		        <p><?php printf( __( "%s has been exported!", 'gp-manual-export' ), $this->export_project ); ?></p>
		    </div>
		    <?php
		}
}

new GP_Manual_Export;
