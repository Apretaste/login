//
// send code via email
//
function start() {
	// get the email
	var email = $('#email').val();

	// validate the email
	var filter = /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;
	if (!filter.test(email)) {
		M.toast({html: 'Email inválido'});
		return false;
	}

	// send code via email
	apretaste.send({
		command: 'LOGIN START',
		data: {'user':email},
		redirect: false
	});

	// ask user to insert code
	$('.mail').html(email);
	$('#wrapperEmail').hide();
	$('#wrapperCode').show();
}

//
// login the user and get back to services
//
function code() {
	// get the email and pin
	var email = $('#email').val();
	var pin = $('#pin').val();

	// validate the pin
	if (pin.length != 4 || isNaN(pin)) {
		M.toast({html: 'Pin inválido'});
		return false;
	}

	// create information string
	var json = '{"command":"login code", "data":{"user":"'+email+'","pin":"'+pin+'"}}';
	var href = '/web/' + btoa(json).replace(/=/g, '');

	// send data via ajax
	$.getJSON(href, function(data) {
		if(data.error == 0) {
			// if no errors, login and redirect to home
			apretaste.send({command: 'SERVICIOS'});
		} else {
			// else display error
			M.toast({html: 'Pin inválido. Revise su correo.'});
			return false;
		}
	});
}