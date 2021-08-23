<?php

namespace App\MojeServices;

class MyAuthenticator implements \Nette\Security\IAuthenticator
{
	private $database;

	public function __construct(\Nette\Database\Context $database, \Nette\Security\Passwords $passwords)
	{
		$this->database = $database;
		$this->passwords = $passwords;
	}

	public function authenticate(array $credentials): \Nette\Security\IIdentity
	{
		bdump('start authenticate');
		[$identita, $password] = $credentials;

		$roleUuids = [];

		foreach ($identita->getAppRoles() as $appRole) {
			$role = $this->database->table('role')
				->where('uuid', $appRole['id'])
				->fetch();
			if ($role) {
				$roleUuids[$role->id] = $role->role;
			}
		}

		if (!empty($roleUuids)){

			// zkus najit uzivatele podle UUID
			$radkaVTabulceUzivatel = $this->database->table('uzivatel')
				->where('uuid', $identita->getUuid())
				->fetch();

			if (!$radkaVTabulceUzivatel) {
				bdump('Nenasel uzivatele podle UUID '.$identita->getUuid());
				//pokud se nepodarilo, zkus najit uzivatele podle prihlaseni
				$radkaVTabulceUzivatel = $this->database->table('uzivatel')
					->where('prihlaseni', $identita->getUsername())
					->fetch();

				if (!$radkaVTabulceUzivatel) {
					bdump('Nový uživatel '.$identita->getUsername().', zatím bez záznamu v databázi');

					$radkaVTabulceUzivatel = $this->database->table('uzivatel')->insert([
						'uuid' => $identita->getUuid(),
						'prihlaseni' => $identita->getUsername(),
						'jmeno' => $identita->getFullname(),
						'email' => $identita->getEmail(),
						'naposledy' => date('Y-m-d H:i:s'),
					]);
					
					bdump('Nový uživatel vložen do databáze: id='.$identita->getUsername());

				} else {

					bdump('Našel jsem uživatele podle username, aktualizuji: id='.$radkaVTabulceUzivatel->id);

					$identita->setId($radkaVTabulceUzivatel->id);
		
					$this->database->table('uzivatel')->where('id',$identita->id)->update([
						'uuid' => $identita->getUuid(),
						'prihlaseni' => $identita->getUsername(),
						'jmeno' => $identita->getFullname(),
						'email' => $identita->getEmail(),
						'naposledy' => date('Y-m-d H:i:s'),
					]);
		
				}

			} else {
				bdump('Našel jsem uživatele podle UUID, aktualizuji: id='.$radkaVTabulceUzivatel->id);

				$identita->setId($radkaVTabulceUzivatel->id);
	
				$this->database->table('uzivatel')->where('id',$identita->id)->update([
					'uuid' => $identita->getUuid(),
					'prihlaseni' => $identita->getUsername(),
					'jmeno' => $identita->getFullname(),
					'email' => $identita->getEmail(),
					'naposledy' => date('Y-m-d H:i:s'),
				]);
			} 

			$identita->setRoles($roleUuids);

		} else {
			throw new Nette\Security\AuthenticationException('Uživatel nemá přidělenou žádnou roli.');
		}

/* 		if (!$this->passwords->verify($password, $radkaVTabulceUzivatel->heslo)) {
			throw new Nette\Security\AuthenticationException('Chybné heslo.');
		}
 */				
		return $identita;
	}
}