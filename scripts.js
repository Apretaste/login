// send code via email

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
	$('.email-section').hide();
	$('.code-section').show();

	// add focus to code
	$('#code').focus();
}

// login the user and get back to services

function code() {
	// get the email and pin
	var email = $('#email').val();
	var pin = $('#code').val();

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
			apretaste.send({command: 'INICIO'});
		} else {
			// else display error
			M.toast({html: 'Pin inválido. Revise su correo.'});
			return false;
		}
	});
}


$(document).ready(function(){
	// add focus to code
	$('#email').focus();

	// submit on Enter
	$('input').keypress(function (e) {
		if (e.which == 13) {
			if(e.currentTarget.id == "email") start();
			if(e.currentTarget.id == "code") code();
			return false;
		}
	});
})
