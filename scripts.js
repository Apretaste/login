//
// send code via email
//
function code() {
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
		command:'LOGIN CODE', 
		data:{'email':email}, 
		redirect:false
	});

	// ask user to insert code
	$('.mail').html(email);
	$('#wrapperEmail').hide();
	$('#wrapperCode').show();
}

//
// login the user and get back to services
//
function login() {
	// get the email and pin
	var email = $('#email').val();
	var pin = $('#pin').val();

	// validate the pin
	if (pin.length != 4 || isNaN(pin)) {
		M.toast({html: 'Pin inválido'});
		return false;
	}

	// send code via email
	apretaste.send({
		command:'LOGIN START',
		data:{'email':email, 'pin':pin}, 
		redirect:true
	});
}