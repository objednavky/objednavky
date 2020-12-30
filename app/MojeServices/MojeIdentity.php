<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace App\MojeServices;

use Nette;
use Nette\Security;


/**
 * implementace IIdentity pro Objednavky
 *
 * @property   mixed $id
 * @property   mixed $uuid
 * @property   mixed $username
 * @property   mixed $email
 * @property   mixed $fullname
 * @property   array $roles
 * @property   array $appRoles
 */
class MojeIdentity implements Nette\Security\IIdentity
{
	use Nette\SmartObject {
		__get as private parentGet;
		__set as private parentSet;
		__isset as private parentIsSet;
	}

	/** @var mixed */
	private $id;

	/** @var mixed */
	private $uuid;

	/** @var mixed */
	private $username;

	/** @var mixed */
	private $email;

	/** @var mixed */
	private $fullname;

	/** @var array */
	private $roles;

	/** @var array */
	private $appRoles;


	public function __construct($id, $uuid, $username, $email, $fullname, $roles = null, iterable $appRoles = null)
	{
		$this->setId($id);
        $this->setUuid($uuid);
        $this->setUsername($username);
        $this->setEmail($email);
        $this->setFullname($fullname);
        $this->setRoles((array) $roles);
		$this->appRoles = $appRoles instanceof \Traversable
			? iterator_to_array($appRoles)
			: (array) $appRoles;
	}


	/**
	 * Sets the ID of user.
	 * @return static
	 */
	public function setId($id)
	{
		$this->id = is_numeric($id) && !is_float($tmp = $id * 1) ? $tmp : $id;
		return $this;
	}


	/**
	 * Returns the ID of user.
	 * @return mixed
	 */
	public function getId()
	{
		return $this->id;
	}


	/**
	 * Sets the UUID of user.
	 * @return static
	 */
	public function setUuid($uuid)
	{
		$this->uuid = $uuid;
		return $this;
	}


	/**
	 * Returns the UUID of user.
	 * @return mixed
	 */
	public function getUuid()
	{
		return $this->uuid;
	}


	/**
	 * Sets the username of user.
	 * @return static
	 */
	public function setUsername($username)
	{
		$this->username = $username;
		return $this;
	}


	/**
	 * Returns the username of user.
	 * @return mixed
	 */
	public function getUsername()
	{
		return $this->username;
	}


	/**
	 * Sets the email of user.
	 * @return static
	 */
	public function setEmail($email)
	{
		$this->email = $email;
		return $this;
	}


	/**
	 * Returns the email of user.
	 * @return mixed
	 */
	public function getEmail()
	{
		return $this->email;
	}


	/**
	 * Sets the full name of user.
	 * @return static
	 */
	public function setFullname($fullname)
	{
		$this->fullname = $fullname;
		return $this;
	}


	/**
	 * Returns the full name of user.
	 * @return mixed
	 */
	public function getFullname()
	{
		return $this->fullname;
	}


	/**
	 * Sets a list of roles that the user is a member of.
	 * @return static
	 */
	public function setRoles(array $roles)
	{
		$this->roles = $roles;
		return $this;
	}


	/**
	 * Returns a list of roles that the user is a member of.
	 */
	public function getRoles(): array
	{
		return $this->roles;
	}


	/**
	 * Returns a user appRoles.
	 */
	public function getAppRoles(): array
	{
		return $this->appRoles;
	}


	/**
	 * Sets user appRoles value.
	 */
	public function __set(string $key, $value): void
	{
		if ($this->parentIsSet($key)) {
			$this->parentSet($key, $value);

		} else {
			$this->appRoles[$key] = $value;
		}
	}


	/**
	 * Returns user appRoles value.
	 * @return mixed
	 */
	public function &__get(string $key)
	{
		if ($this->parentIsSet($key)) {
			return $this->parentGet($key);

		} else {
			return $this->appRoles[$key];
		}
	}


	public function __isset(string $key): bool
	{
		return isset($this->appRoles[$key]) || $this->parentIsSet($key);
	}
}
