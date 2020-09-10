<?php

class MyAuthenticator implements Nette\Security\IAuthenticator
{
	private $database;
	private $passwords;

	public function __construct(Nette\Database\Context $database, Nette\Security\Passwords $passwords)
	{
		$this->database = $database;
		$this->passwords = $passwords;
	}

	public function authenticate(array $credentials): Nette\Security\IIdentity
	{
		[$username, $password] = $credentials;

		$radkaVTabulcePokusUser = $this->database->table('pokus_jmeno')
			->where('jmeno', $username)
			->fetch();

		if (!$radkaVTabulcePokusUser) {
			throw new Nette\Security\AuthenticationException('Neznámý uživatel.');
		}

		if (!$this->passwords->verify($password, $radkaVTabulcePokusUser->heslo)) {
			throw new Nette\Security\AuthenticationException('Chybné heslo.');
        }
                
		return new Nette\Security\Identity(
			$radkaVTabulcePokusUser->id,
			$radkaVTabulcePokusUser->mojerole, // nebo pole více rolí
			['jmeno' => $radkaVTabulcePokusUser->jmeno]
		);
	}
}