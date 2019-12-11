<?php

use Framework\Database;
use Framework\Security;
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
	 * Send the code to the user via ajax
	 *
	 * @param Request
	 * @param Response
	 */
	public function _code(Request $request, Response $response)
	{
		// get and validate the person's email
		$email = $request->input->data->email;
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

		//check if there is a pin active in the last hour or create a new pin for the user
		$pin = Database::query("SELECT pin, TIMESTAMPDIFF(MINUTE, pin_date, NOW()) AS minutes_left FROM person WHERE email='$email' AND TIMESTAMPDIFF(HOUR, pin_date, NOW())<1");
		$activePin = !empty($pin);
		$minutes_left = $activePin ? abs($pin[0]->minutes_left-60) : 60;
		if ($activePin) $pin = $pin[0]->pin;
		else $pin = mt_rand(1000, 9999);

		// update the user pin if there's no pin active
		if (!$activePin) Database::query("UPDATE person SET pin='$pin', pin_date=CURRENT_TIMESTAMP WHERE email='$email'");

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
	}

	/**
	 * Login the user and create the session
	 *
	 * @param Request
	 * @param Response
	 */
	public function _start(Request $request, Response $response)
	{
		// get values from request
		$email = $request->input->data->email;
		$pin = $request->input->data->pin;

		// login the person
		$person = Security::loginViaPin($email, $pin);

		// error if session can't be started
		if(empty($person)) {
			$response->setLayout("login.ejs");
			return $response->setTemplate('message.ejs', [
				"header" => "Error iniciando sesión",
				"icon" => "sentiment_very_dissatisfied",
				"text" => "No hemos podido iniciar su sesión. Tal vez insertó el código incorrecto. Por favor intente nuevamente y si no logra iniciar sesión, escribanos a nuestro equipo de soporte.",
				"button" => ["href" => "LOGIN", "caption" => "Reintentar"]
			]);
		}

		// redirect to the list of services
		Core::redirect("SERVICIOS");
	}

	/**
	 * Logout a user
	 *
	 * @param Request
	 * @param Response
	 */
	public function _logout(Request $request, Response $response)
	{
		// logs out the user
		Security::logout();

		// redirect to the list of services
		Core::redirect("SERVICIOS");
	}
}