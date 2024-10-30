const dialogs = {

    emitConfirm(title, text, callback = null,callbackObjectParam= null) {
        Swal.fire({
            title,
            text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: modals.btnPrimaryColor,
            cancelButtonColor: modals.btnSecondaryColor,
            confirmButtonText: modals.btnConfirmText,
            cancelButtonText: modals.btnCancelText
        }).then((result) => {
            if (result.isConfirmed) {
                if (callback) {
                    callback(callbackObjectParam);
                }
            }
        });
    },

	emitSuccess(title, text=null, callback = null,callbackObjectParam= null) {
		Swal.fire({
			title,
			text,
			icon: 'success',
			confirmButtonColor: modals.btnPrimaryColor,
			confirmButtonText: modals.btnConfirmText,
		}).then((result) => {
			console.log(callback);
			if (callback) {
				callback(callbackObjectParam);
			}
		});
	},

    emitError(msg, callback = null) {
        Swal.fire({
            title: modals.errorTitleModal,
            text: msg,
            icon: 'error',
            confirmButtonColor: modals.btnPrimaryColor,
            confirmButtonText: modals.btnConfirmText
        }).then((response) => {
            if (response.value && callback) {
                callback();
            }
        });
    },

    emitWpError(error, callback = null) {
        var err = JSON.parse(error.responseText);
        if(typeof err !== 'string') err = err.data ? err.data : err.message;
        dialogs.emitError(err,callback);
    },

    emitForm(view, idForm, callback) {
        Swal.fire({
            title: modals.icpFormNameTitle,
            html: view,
            showCancelButton: true,
            confirmButtonColor: modals.btnPrimaryColor,
            confirmButtonText: modals.btnConfirmText
        }).then((response) => {
            if (response.value) {
                callback(jQuery('#' + idForm).serializeArray());
            }
        });
    }
}
