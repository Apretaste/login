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
	 * @param Request $request
	 * @param Response $response
	 * @return Response
	 * @throws Alert
	 */
	public function _start(Request $request, Response $response)
	{
		// get params from the request
		$email = $request->input->data->user;

		// validate the person's email
		if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strpos($email, '+') !== false) {
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
			Database::query("
				INSERT INTO person_code (email, pin) VALUES ('$email', $pin) 
				ON DUPLICATE KEY UPDATE pin=$pin, pin_date=CURRENT_TIMESTAMP;");
		}

		// send the email
		$sender = new Email();
		$sender->to = $email;
		$sender->subject = "Su código es $pin";
		$sender->sendFromTemplate(['code' => $pin, 'time_left' => $minutes_left], 'code');

		// return JSON without template
		$response->setContent(['code' => $pin]);
	}

	/**
	 * Login using email/sms and code
	 *
	 * @param Request $request
	 * @param Response $response
	 * @return Response
	 * @throws Alert
	 */
	public function _code(Request $request, Response $response)
	{
		// get params from the request
		$email = $request->input->data->user;
		$pin = (int) $request->input->data->pin;

		// login the person
		$person = Security::loginViaPin($email, $pin);

		// error if pin is not correct
		if (empty($person)) {
			return $response->setContent(['error' => '1', 'token' => '']);
		}

		// get the user's token
		$token = Database::queryFirst("SELECT token FROM person WHERE id = {$person->id}");

		// return the user's token
		return $response->setContent(['error' => '0', 'token' => $token->token]);
	}

	/**
	 * Logout a user
	 *
	 * @param Request $request
	 * @param Response $response
	 *
	 * @throws Alert
	 */
	public function _logout(Request $request, Response $response)
	{
		// delete token
		Database::query("UPDATE person SET token=NULL WHERE id={$request->person->id}");

		// close the session (if open)
		Security::logout();

		// redirect to the list of services
		Core::redirect('INICIO');
	}
}
