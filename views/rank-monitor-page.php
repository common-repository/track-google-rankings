<?php




$columns = array(

	'cb' => '<input type="checkbox">',

	'keyword' => 'Keyword',

	'rank' => 'Rank',

	'rank-change' => 'Change',

	'last-checked-on' => 'Last check',

	'created-on' => 'Added',

	/*'google-search-link' => ' ',*/

);

$sortable = array(

	'keyword' => array('keyword', false),

	'rank' => array('rank', false),

	'rank-change' => array('rank_change', true),

	'created-on' => array('created_on', true),

);

$hidden = array();



function grmPrintColumnHeaders($columns, $sortable, $hidden, $with_id = true ) {

	$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

	$current_url = remove_query_arg( array('paged', 'added', 'deleted'), $current_url );



	if ( isset( $_GET['orderby'] ) )

		$current_orderby = $_GET['orderby'];

	else

		$current_orderby = 'rank';



	if ( isset( $_GET['order'] ) && 'desc' == $_GET['order'] )

		$current_order = 'desc';

	else

		$current_order = 'asc';



	foreach ( $columns as $column_key => $column_display_name ) {

		$class = array( 'manage-column', "column-$column_key" );



		$style = '';

		if ( in_array( $column_key, $hidden ) )

			$style = 'display:none;';



		$style = ' style="' . $style . '"';



		if ( 'cb' == $column_key )

			$class[] = 'check-column';



		if ( isset( $sortable[$column_key] ) ) {

			list( $orderby, $desc_first ) = $sortable[$column_key];



			if ( $current_orderby == $orderby ) {

				$order = 'asc' == $current_order ? 'desc' : 'asc';

				$class[] = 'sorted';

				$class[] = $current_order;

			} else {

				$order = $desc_first ? 'desc' : 'asc';

				$class[] = 'sortable';

				$class[] = $desc_first ? 'asc' : 'desc';

			}



			$column_display_name = '<a href="' . esc_url( add_query_arg( compact( 'orderby', 'order' ), $current_url ) ) . '"><span>' . $column_display_name . '</span><span class="sorting-indicator"></span></a>';

		}



		$id = $with_id ? "id='$column_key'" : '';



		if ( !empty( $class ) )

			$class = "class='" . join( ' ', $class ) . "'";



		echo "<th scope='col' $id $class $style>$column_display_name</th>";

	}

}



$bulkActions = array('delete-keywords' => 'Delete');



if ( isset($_GET['added']) && is_numeric($_GET['added']) ){

	$addedKeywords = intval($_GET['added']);

	if ( $addedKeywords != 0 ){

		printf(

			'<div class="updated"><p>%s keyword%s added.</p></div>',

			$addedKeywords,

			($addedKeywords == 1) ? '' : 's'

		);

	}

}

if ( isset($_GET['deleted']) ){

	$deleted = intval($_GET['deleted']);

	printf(

		'<div class="updated"><p>%s keyword%s deleted.</p></div>',

		$deleted,

		($deleted == 1) ? '' : 's'

	);

}



$addKeywordsUrl = sprintf('tools.php?page=%s&action=add-keywords', urlencode($pageSlug));



?>

<div class="wrap">

	<?php screen_icon(); ?>
	<h2><?php echo $pageTitle; ?> <a href="<?php	echo esc_attr($addKeywordsUrl); ?>" class="add-new-h2">Add Keywords</a></h2>



<?php if (count($keywords) > 0): ?>



	<form action="<?php echo esc_attr(add_query_arg('noheader', 1)); ?>" method="post">



	<div class="tablenav">

		<?php wp_nonce_field('bulk-keywords'); ?>

		<div class="alignleft actions">

			<?php wsUtil::bulk_actions($bulkActions); ?>

		</div>



		<?php

		/** @var wsPagination $pagination */

		$pagination->display('top');

		?>

	</div>

	

	<table class="widefat fixed" id="grm-keywords">

		<thead><tr><?php grmPrintColumnHeaders($columns, $sortable, $hidden); ?></tr></thead>

		

		<tbody>

		<?php 

		$rowClass = '';

		/**

		 * @var StdClass $keyword

		 */

		foreach($keywords as $keyword):

			$rowClass = ( $rowClass == '' ? ' class="alternate"' : '' );



			printf('<tr%s>', $rowClass);

			foreach($columns as $columnName => $columnHeader){

				$columnClass = array('column-'.$columnName);



				switch($columnName){



					case 'cb':

						$columnClass[] = 'check-column';

						printf('<th scope="row" class="%s">', implode(' ', $columnClass));

						printf(

							'<input type="checkbox" name="selected_keywords[]" value="%s" />',

							esc_attr($keyword->keyword_id)

						);

						echo '</th>';

						break;



					case 'keyword':

						printf('<td class="%s">', implode(' ', $columnClass));



						$detailsUrl = sprintf(

							'tools.php?page=%s&action=details&id=%d',

							urlencode($_GET['page']),

							$keyword->keyword_id

						);

						$detailsUrl = admin_url($detailsUrl);

						printf(

							'<a class="row-title" id="keyword-%d" href="%s" title="View &quot;%s&quot; details">%s</a>',

							intval($keyword->keyword_id),

							$detailsUrl,

							esc_attr($keyword->keyword),

							esc_html($keyword->keyword)

						);

						echo '</td>';

						break;



					case 'rank':

						printf('<td class="%s">', implode(' ', $columnClass));

						$keyword->last_checked_on = intval($keyword->last_checked_on);

						$keyword->rank_change = intval($keyword->rank_change);



						if ( $keyword->last_checked_on == 0 ){

							/* empty cell */

						} else {

							if ( $keyword->rank !== null ){

								$displayRank = intval($keyword->rank);

							} else {

								$displayRank = '&gt; '.$config->maxGoogleResults;

							}

							printf(

								'<span class="rank-value">%s</span>',

								$displayRank

							);

						}

						echo '</td>';

						break;



					case 'rank-change':

						printf('<td class="%s">', implode(' ', $columnClass));

						if ( $keyword->last_checked_on == 0 ){

							/* empty cell */

						} else {

							$classes = array();

							if ( $keyword->rank_change == 0 ){

								$displayChange = '-';

								$classes[] = 'rank-change-none';

							} else {

								$displayChange = sprintf('%+d', $keyword->rank_change);

								if ( $keyword->rank_change > 0 ){

									$classes[] = 'rank-change-up';

								} else {

									$classes[] = 'rank-change-down';

								}

							}

							printf(

								'<span class="rank-change %s">%s</span>',

								esc_attr(implode(' ', $classes)),

								$displayChange

							);

						}

						echo '</td>';

						break;



					case 'google-search-link':

						$googleSearchLink = sprintf(

							'<a id="google-search-%d"

								class="run-google-search"

								title="Google &quot;%s&quot;"

								href="%s"

								target="_blank"

							> </a>',

							intval($keyword->keyword_id),

							esc_attr($keyword->keyword),

							esc_attr($keyword->googleSearchUrl)

						);



						printf('<td class="%s">', implode(' ', $columnClass));

						echo $googleSearchLink;

						echo '</td>';

						break;



					case 'created-on':

						printf('<td class="%s">', implode(' ', $columnClass));

						if ( !empty($keyword->created_on) ){

							$delta = time() - $keyword->created_on;

							if ( $delta < (3*24*3600) ){

								printf( __( '%s ago' ), human_time_diff($keyword->created_on) );

							} else {

								echo date_i18n('Y/m/d', $keyword->created_on);

							}

						} else {

							echo 'N/A';

						}

						echo '</td>';

						break;



					case 'last-checked-on':

						printf('<td class="%s">', implode(' ', $columnClass));

						if ( !empty($keyword->last_checked_on) ){

							printf( __( '%s ago' ), human_time_diff($keyword->last_checked_on) );

						} else {

							echo '-';

						}

						echo '</td>';

						break;



					default:

						printf('<td class="%s">', implode(' ', $columnClass));

						printf('Unknown column [%s]', $columnName);

						echo '</td>';

				}



			}

		endforeach;

		?>

		</tbody>

		

		<tfoot><tr><?php grmPrintColumnHeaders($columns, $sortable, $hidden, false); ?></tr></tfoot>

	</table>

	

	<div class="tablenav bottom">

		<div class="alignleft actions">

			<?php

			wsUtil::bulk_actions($bulkActions, '2');



			$exportKeywordsUrl = add_query_arg(array('action' => 'export-csv', 'noheader' => 1));

			printf(' <a href="%s" class="button-secondary" style="display:inline-block;">Export CSV</a>', esc_attr($exportKeywordsUrl));

			?>

		</div>

		

		<?php $pagination->display('bottom'); ?>

	</div>



	</form>



<?php else: ?>



	<p>

		No keywords have been added yet.

		Click "Add keywords" to add some.

	</p>



	<?php if ( !$config->catchSearchKeywords ): ?>

		<p>

			You can also enable automatic keyword discovery in

			<a href="<?php echo admin_url('options-general.php?page='.$settingsPageSlug); ?>"><em>Settings &gt; Track Google Rankings</em></a>.

		</p>

	<?php endif; ?>



<?php endif; ?>



</div>