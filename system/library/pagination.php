<?php
/**
 * @package		OpenCart
 * @author		Daniel Kerr
 * @copyright	Copyright (c) 2005 - 2017, OpenCart, Ltd. (https://www.opencart.com/)
 * @license		https://opensource.org/licenses/GPL-3.0
 * @link		https://www.opencart.com
*/

/**
* Pagination class
*/
class Pagination {
	public $total = 0;
	public $page = 1;
	public $limit = 20;
	public $num_links = 8;
	public $url = '';
	public $text_first = '|&lt;';
	public $text_last = '&gt;|';
	public $text_next = '&gt;';
	public $text_prev = '&lt;';

	/**
     * 
     *
     * @return	text
     */
	public function render() {
		$total = $this->total;

		if ($this->page < 1) {
			$page = 1;
		} else {
			$page = $this->page;
		}

		if (!(int)$this->limit) {
			$limit = 10;
		} else {
			$limit = $this->limit;
		}

		$num_links = $this->num_links;
		$num_pages = ceil($total / $limit);
		$mobile_num_links = 5;
		$mobile_start = 1;
		$mobile_end = $num_pages;

		if ($num_pages > $mobile_num_links) {
			$mobile_start = $page - floor($mobile_num_links / 2);
			$mobile_end = $page + floor($mobile_num_links / 2);

			if ($mobile_start < 1) {
				$mobile_end += abs($mobile_start) + 1;
				$mobile_start = 1;
			}

			if ($mobile_end > $num_pages) {
				$mobile_start -= ($mobile_end - $num_pages);
				$mobile_end = $num_pages;
			}
		}

		$this->url = str_replace('%7Bpage%7D', '{page}', $this->url);

		$output = '<ul class="pagination">';

		if ($page > 1) {
			$output .= '<li class="pagination-first"><a href="' . str_replace(array('&amp;page={page}', '?page={page}', '&page={page}'), '', $this->url) . '">' . $this->text_first . '</a></li>';
			
			if ($page - 1 === 1) {
				$output .= '<li class="pagination-prev"><a href="' . str_replace(array('&amp;page={page}', '?page={page}', '&page={page}'), '', $this->url) . '">' . $this->text_prev . '</a></li>';
			} else {
				$output .= '<li class="pagination-prev"><a href="' . str_replace('{page}', $page - 1, $this->url) . '">' . $this->text_prev . '</a></li>';
			}
		}

		if ($num_pages > 1) {
			if ($num_pages <= $num_links) {
				$start = 1;
				$end = $num_pages;
			} else {
				$start = $page - floor($num_links / 2);
				$end = $page + floor($num_links / 2);

				if ($start < 1) {
					$end += abs($start) + 1;
					$start = 1;
				}

				if ($end > $num_pages) {
					$start -= ($end - $num_pages);
					$end = $num_pages;
				}
			}

			for ($i = $start; $i <= $end; $i++) {
				$classes = array();

				if ($page == $i) {
					$classes[] = 'active';
				}

				if ($i < $mobile_start || $i > $mobile_end) {
					$classes[] = 'hidden-xs';
				}

				$class = $classes ? ' class="' . implode(' ', $classes) . '"' : '';

				if ($page == $i) {
					$output .= '<li' . $class . '><span>' . $i . '</span></li>';
				} else {
					if ($i === 1) {
						$output .= '<li' . $class . '><a href="' . str_replace(array('&amp;page={page}', '?page={page}', '&page={page}'), '', $this->url) . '">' . $i . '</a></li>';
					} else {
						$output .= '<li' . $class . '><a href="' . str_replace('{page}', $i, $this->url) . '">' . $i . '</a></li>';
					}
				}
			}
		}

		if ($page < $num_pages) {
			$output .= '<li class="pagination-next"><a href="' . str_replace('{page}', $page + 1, $this->url) . '">' . $this->text_next . '</a></li>';
			$output .= '<li class="pagination-last"><a href="' . str_replace('{page}', $num_pages, $this->url) . '">' . $this->text_last . '</a></li>';
		}

		$output .= '</ul>';

		if ($num_pages > 1) {
			return $output;
		} else {
			return '';
		}
	}
}
