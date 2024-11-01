<?php

if ( !class_exists('wsPagination') ):

class wsPagination {
	private $page;
	private $perPage;
	private $totalPages = null;
	private $totalItems = null;
	
	/**
	 * Class constructor.
	 * 
	 * @param int $perPage Items per page.
	 * @param int $page The current page. If not specified, $_GET['paged'] will be used.
	 * @param int $totalItems Total items. Can also be set later using {@link wsPagination::setTotalItems()}.
	 * @param int $totalPages Total pages. If omitted, the number of pages will be calculated automatically.
	 */
	public function __construct($perPage, $page = null, $totalItems = null, $totalPages = null){
		$this->page = $page;
		$this->perPage = $perPage;
		$this->totalItems = $totalItems;
		$this->totalPages = $totalPages;
		
		if ( $this->page === null ){
			$this->page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
			if ( $this->page < 1 ) {
				$this->page = 1;
			}
		}
	}
	
	/**
	 * Get the LIMIT value (for SQL queries) based on the current pagination settings.
	 * 
	 * @return int
	 */
	public function limit(){
		return $this->perPage;
	}
	
	/**
	 * Get the OFFSET value (for SQL queries) based on the current pagination settings.
	 * 
	 * @return int
	 */
	public function offset(){
		return max(($this->page - 1) * $this->perPage, 0);
	}
	
	/**
	 * Set the total number of items that need to be paginated.
	 * 
	 * The number of items can only be set once - either in the constructor, 
	 * or via this method. Calling setTotalItems() a second time will result 
	 * in an exception. 
	 *   
	 * @param int $count
	 * @return void
	 */
	public function setTotalItems($count){
		if ( is_numeric($this->totalItems) && ($count != $this->totalItems) ){
			throw new Exception("Can't modify total items once set. This value is write-once.");
		}
		$this->totalItems = intval($count);
	}
	
	/**
	 * Display pagination links.
	 * 
	 * @param string $which
	 * @return void
	 */
	public function display($which = 'bottom'){
		echo $this->getHtml($which);
	}
	
	/**
	 * Magic method. Generates a string representation of this object - the pagination HTML. 
	 * @uses wsPagination::getHtml();
	 * 
	 * @return string
	 */
	public function __toString(){
		return $this->getHtml();
	}
	
	/**
	 * Generate the page navigation HTML.
	 *  
	 * @param string $which Generate nav. for a specific location - 'top' = above the table, 'bottom' = below it.
	 * @return string
	 */
	public function getHtml($which = 'bottom'){
		$output = '<span class="displaying-num">' . sprintf( _n( '1 item', '%s items', $this->totalItems ), number_format_i18n( $this->totalItems ) ) . '</span>';

		$current = $this->page;
		$total_pages = $this->getTotalPages();
		
		$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$current_url = remove_query_arg( array( 'added', 'deleted', 'hotkeys_highlight_last', 'hotkeys_highlight_first' ), $current_url );

		$page_links = array();

		$disable_first = $disable_last = '';
		if ( $current == 1 )
			$disable_first = ' disabled';
		if ( $current == $total_pages )
			$disable_last = ' disabled';

		$page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
			'first-page' . $disable_first,
			esc_attr__( 'Go to the first page' ),
			esc_url( remove_query_arg( 'paged', $current_url ) ),
			'&laquo;'
		);

		$page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
			'prev-page' . $disable_first,
			esc_attr__( 'Go to the previous page' ),
			esc_url( add_query_arg( 'paged', max( 1, $current-1 ), $current_url ) ),
			'&lsaquo;'
		);

		if ( 'bottom' == $which )
			$html_current_page = $current;
		else
			$html_current_page = sprintf( "<input class='current-page' title='%s' type='text' name='%s' value='%s' size='%d' />",
				esc_attr__( 'Current page' ),
				esc_attr( 'paged' ),
				$current,
				strlen( $total_pages )
			);

		$html_total_pages = sprintf( "<span class='total-pages'>%s</span>", number_format_i18n( $total_pages ) );
		$page_links[] = '<span class="paging-input">' . sprintf( _x( '%1$s of %2$s', 'paging' ), $html_current_page, $html_total_pages ) . '</span>';

		$page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
			'next-page' . $disable_last,
			esc_attr__( 'Go to the next page' ),
			esc_url( add_query_arg( 'paged', min( $total_pages, $current+1 ), $current_url ) ),
			'&rsaquo;'
		);

		$page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
			'last-page' . $disable_last,
			esc_attr__( 'Go to the last page' ),
			esc_url( add_query_arg( 'paged', $total_pages, $current_url ) ),
			'&raquo;'
		);

		$output .= "\n<span class='pagination-links'>" . join( "\n", $page_links ) . '</span>';

		if ( $total_pages )
			$page_class = $total_pages < 2 ? ' one-page' : '';
		else
			$page_class = ' no-pages';

		$pagination = "<div class='tablenav-pages{$page_class}'>$output</div>";
		return $pagination;
	}	
	
	/**
	 * Get the total number of pages.
	 * 
	 * @return int
	 */
	private function getTotalPages(){
		if ( is_numeric($this->totalPages) ){
			return $this->totalPages;
		} else {
			if ( is_numeric($this->totalItems) ){
				$this->totalPages = intval(ceil($this->totalItems / $this->perPage));
				return $this->totalPages;
			} else {
				return null;
			}
		}
	}	
}

endif;