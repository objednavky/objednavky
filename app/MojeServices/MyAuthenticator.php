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

		$radkaVTabulceUzivatel = $this->database->table('uzivatel')
			->where('prihlaseni', $username)
			->fetch();

		if (!$radkaVTabulceUzivatel) {
			throw new Nette\Security\AuthenticationException('Neznámý uživatel.');
		}

/* 		if (!$this->passwords->verify($password, $radkaVTabulceUzivatel->heslo)) {
			throw new Nette\Security\AuthenticationException('Chybné heslo.');
		}
 */				
		return new Nette\Security\Identity(
			$radkaVTabulceUzivatel->id,
			$radkaVTabulceUzivatel->role, // nebo pole více rolí
			[
				'jmeno' => $radkaVTabulceUzivatel->jmeno,
				'prihlaseni' => $radkaVTabulceUzivatel->prihlaseni,
			]
		);
	}
}