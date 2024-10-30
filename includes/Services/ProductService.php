<?php

namespace Printspot\ICP\Services;

class ProductService
{	
	/**
	 * Show the custom meta data on the cart and checkout pages	 *
	 * @param  mixed $formatted_meta
	 * @param  mixed $item
	 * @return void
	 */
	static function translateItemMeta( $formatted_meta, $item )
	{
		foreach( $formatted_meta as $key => $meta )
		{
			if($meta->key == 'proyecto') $meta->display_key = __('Project', 'imaxel');
		}

		return $formatted_meta;
	}
}