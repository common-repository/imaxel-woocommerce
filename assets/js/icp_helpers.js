const icpHelpers = {
	showBlockLoading(element, imageBlock = false,onPage = false) {
		var loader = jQuery("<div>")
			.addClass('lds-ellipsis js-block-loading')
			.css({
				'width': '70px',
				'height': '70px',
				'z-index': 10000,
			})
			.append(jQuery("<div>"))
			.append(jQuery("<div>"))
			.append(jQuery("<div>"))
			.append(jQuery("<div>"));
		if (imageBlock) {
			loader.css({
				'position': 'absolute',
				'left': '50%',
				'top': '35%',
				'transform': 'translate(-50%,-50%)',
			})
		} else {
			loader.css({
				'margin-left': '30px',
			})
		}

		if(onPage){
			loader.css({
				'position': 'relative',
			})
		}
		loader.show();

		element.after(loader);
	},

	hideBlockLoading() {
		jQuery(".js-block-loading").hide();
	},


	drawPrice(price) {
		const symbol = icpLocale.currency_symbol;
		const position = icpLocale.currency_position;
		switch (position) {
			case 'left':
				price = ' ' + symbol + price;
				break;
			case 'right':
				price = ' ' + price + symbol;
				break;
			case 'left_space':
				price = ' ' + symbol + ' ' + price;
				break;
			case 'right_space':
				price = ' ' + price + ' ' + symbol;
				break;
			default:
				price = ' ' + price;
		}

		return price;
	}
}
