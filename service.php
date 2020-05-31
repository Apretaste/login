<?php

use Framework\Database;
use Framework\Security;
use Framework\Core;
use Apretaste\Person;
use Apretaste\Email;
use Apretaste\Request;
use Apretaste\Response;

class Service
{
	/**
	 * Ask for email and code
	 *
	 * @param Request
	 * @param Response
	 */
	public function _main(Request $request, Response $response)
	{
		$response->setCache('year');
		$response->setTemplate('main.ejs');
	}

	/**
	 * Send the code to the user via email
	 *
	 * @param \Apretaste\Request $request
	 * @param \Apretaste\Response $response
	 *
	 * @return \Apretaste\Response
	 * @throws \Framework\Alert
	 * @throws \Exception
	 */
	public function _start(Request $request, Response $response)
	{
		// get params from the request
		$email = $request->input->data->user;

		// validate the person's email
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return $response->setTemplate('message.ejs', [
				'header' => 'Correo inválido',
				'icon' => 'sentiment_very_dissatisfied',
				'text' => "Lo sentimos, pero '$email' no parece ser un correo electrónico válido. Revise el correo e intente nuevamente.",
				'button' => ['href' => 'LOGIN', 'caption' => 'Reintentar']
			]);
		}

		// check valid domain
		Security::checkDomain($email);

		// check if there is a pin active in the last hour or create a new pin for the user
		$pin = Database::query("SELECT pin, TIMESTAMPDIFF(MINUTE, pin_date, NOW()) AS minutes_left FROM person_code WHERE email='$email' AND TIMESTAMPDIFF(HOUR, pin_date, NOW())<1");
		$activePin = !empty($pin);
		$minutes_left = $activePin ? abs($pin[0]->minutes_left - 60) : 60;

		if ($activePin) {
			$pin = $pin[0]->pin;
		} else {
			$pin = random_int(1000, 9999);

			// update the user pin if there's no pin active
			Database::query("INSERT INTO person_code (email,pin) VALUES ('$email', $pin) 
								 ON DUPLICATE KEY UPDATE pin = $pin, pin_date = CURRENT_TIMESTAMP;");
		}

		// prepare message
		$subject = "Su código es $pin";
		$body = "Su código secreto es $pin, y sera válido durante los próximos $minutes_left minutos. Use este código para registrarse. Si usted no esperaba este código, elimine este mensaje. No comparta el código con nadie. Ningun representante nuestro le pedirá este código.";

		// send the email with priority
		$sender = new Email();
		$sender->service = 'login';
		$sender->to = $email;
		$sender->subject = $subject;
		$sender->body = $body;
		$res = $sender->send();

		// return JSON without template
		$response->setContent(['code' => $pin]);
	}

	/**
	 * Login using email/sms and code
	 *
	 * @param \Apretaste\Request $request
	 * @param \Apretaste\Response $response
	 *
	 * @return \Apretaste\Response
	 * @throws \Framework\Alert
	 */
	public function _code(Request $request, Response $response)
	{
		// get params from the request
		$email = $request->input->data->user;

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return $response->setContent(['error' => '1', 'token' => '']);
		}

		$pin = (int) $request->input->data->pin;

		// search for pin
		$check = Database::query("SELECT pin, pin_date, TIMESTAMPDIFF(MINUTE, pin_date, NOW()) AS minutes_left FROM person_code WHERE pin = $pin AND email='$email' AND TIMESTAMPDIFF(HOUR, pin_date, NOW())<1");

		if (empty($check)) {
			return $response->setContent(['error' => '1', 'token' => '']);
		}

		// get the Person object
		$person = Person::find($email);

		// if email do not exist, create a new user
		if (!$person) {
			Person::new($email);
		}

		// update pin on person
		// TODO: maybe it is temporal
		Database::query("UPDATE person SET pin = $pin, pin_date = '{$check[0]->pin_date}' WHERE email = '$email';");

		// login the person
		$person = Security::loginViaPin($email, $pin);

		// error if session can't be started
		if (empty($person)) {
			return $response->setContent(['error' => '1', 'token' => '']);
		}

		// get the user's token
		$token = Database::query("SELECT token FROM person WHERE id='{$person->id}'");

		// return the user's token
		return $response->setContent(['error' => '0', 'token' => $token[0]->token]);
	}

	/**
	 * Logout a user
	 *
	 * @param \Apretaste\Request $request
	 * @param \Apretaste\Response $response
	 *
	 * @throws \Framework\Alert
	 */
	public function _logout(Request $request, Response $response)
	{
		// delete token
		Database::query("UPDATE person SET token=NULL WHERE id={$request->person->id}");

		// close the session (if open)
		Security::logout();

		// redirect to the list of services
		Core::redirect('SERVICIOS');
	}
}
