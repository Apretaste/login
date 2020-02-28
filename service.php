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
		$response->setCache("year");
		$response->setTemplate("main.ejs");
	}

	/**
	 * Send the code to the user via email
	 *
	 * @param Request
	 * @param Response
	 */
	public function _start(Request $request, Response $response)
	{
		// get params from the request
		$email = $request->input->data->user;

		// validate the person's email
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return $response->setTemplate('message.ejs', [
				"header" => "Correo inválido",
				"icon" => "sentiment_very_dissatisfied",
				"text" => "Lo sentimos, pero '$email' no parece ser un correo electrónico válido. Revise el correo e intente nuevamente.",
				"button" => ["href" => "LOGIN", "caption" => "Reintentar"]
			]);
		}

		// if person do not exist, create a new user
		$person = Person::find($email);
		if (!$person) {
			$person = Person::new($email);
		}

		// check if there is a pin active in the last hour or create a new pin for the user
		$pin = Database::query("SELECT pin, TIMESTAMPDIFF(MINUTE, pin_date, NOW()) AS minutes_left FROM person WHERE email='$email' AND TIMESTAMPDIFF(HOUR, pin_date, NOW())<1");
		$activePin = !empty($pin);
		$minutes_left = $activePin ? abs($pin[0]->minutes_left - 60) : 60;
		if ($activePin) {
			$pin = $pin[0]->pin;
		} else {
			$pin = mt_rand(1000, 9999);
		}

		// update the user pin if there's no pin active
		if (!$activePin) {
			Database::query("UPDATE person SET pin='$pin', pin_date=CURRENT_TIMESTAMP WHERE email='$email'");
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
		$response->setContent(["code" => $pin]);
	}

	/**
	 * Login using email/sms and code
	 *
	 * @param Request
	 * @param Response
	 */
	public function _code(Request $request, Response $response)
	{
		// get params from the request
		$email = $request->input->data->user;
		$pin = $request->input->data->pin;

		// login the person
		$person = Security::loginViaPin($email, $pin);

		// error if session can't be started
		if (empty($person)) {
			return $response->setContent(["error" => "1","token" => ""]);
		}

		// get the user's token
		$token = Database::query("SELECT token FROM person WHERE id='{$person->id}'");

		// return the user's token
		return $response->setContent(["error" => "0","token" => $token[0]->token]);
	}

	/**
	 * Logout a user
	 *
	 * @param Request
	 * @param Response
	 */
	public function _logout(Request $request, Response $response)
	{
		// delete token
		Database::query("UPDATE person SET token=NULL WHERE id={$request->person->id}");

		// close the session (if open)
		Security::logout();

		// redirect to the list of services
		Core::redirect("SERVICIOS");
	}
}