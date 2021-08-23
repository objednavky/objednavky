<?php
/******************************************************************************************************************
 * 
 * Tento model slouží pro jednotné místo načítání objednávek z databáze pro presentery.
 * Postupně sem budou přesunuta všechna volání databáze z presenterů, především metody mapRozpocet()
 * 
 * Prvním krokem je přesunutí všech metod mapRozpocet a jejich přejmenování na mapRozpocetNazevPresenteru().
 * Následně přijde na řadu sjednocení funkcionality.
 * 
 */

namespace App\MojeServices;

use Exception;
use Nette;
use Ublaboo\NetteDatabaseDataSource\NetteDatabaseDataSource;

class ParovaniDenikuService
{
	use Nette\SmartObject;

	/** @var Nette\Database\Context */
	private $database;

	public $fmt;

	public function __construct(\Nette\Database\Context $database) {
		$this->database = $database;
		$this->fmt = new \NumberFormatter( 'cs_CZ', \NumberFormatter::CURRENCY );
		$this->fmt->setAttribute(\NumberFormatter::FRACTION_DIGITS, 0);
	}


	public function vratNoveZaznamyDeniku() {
		return new NetteDatabaseDataSource($this->database, 'SELECT dv.*, d.uuid AS duuid FROM denik_view dv LEFT JOIN denik d ON dv.uuid = d.uuid WHERE d.uuid IS NULL AND dv.petky=1 AND dv.deleted=0');
	}

	public function vratSmazaneZaznamyDeniku() {
		return new NetteDatabaseDataSource($this->database, 'SELECT d.*, dv.uuid AS dvuuid FROM denik d LEFT JOIN denik_view dv ON dv.uuid = d.uuid WHERE d.petky=1 AND dv.deleted=1');
	}











	private function objednavkyPodleStavu(array $stavy) {
		$rok = $this->database->table('setup')->get(1)->rok; //TODO brat rok ze session
		return $this->database->table('objednavky')
						->where('stav', $stavy)
						->where('cinnost.rozpocet.rok', $rok);
	}

	private function objednavkyPodleVlastnika(int $user_id) {
		$rok = $this->database->table('setup')->get(1)->rok; //TODO brat rok ze session
		return $this->database->table('objednavky')
						->where('zakladatel', $user_id)
						->where('cinnost.rozpocet.rok', $rok);
	}

	private function objednavkyPodleRozpoctu(int $rozpocet_id) {
		return $this->database->table('objednavky')
						->where('cinnost.id_rozpocet', $rozpocet_id);
	}

	private function sumaObjednavekPodlePrehleduAStavu(int $prehledId) {
		$rok = $this->database->table('setup')->get(1)->rok;
		return $this->database->table('objednavky')
				->select("SUM(CASE WHEN stav IN (0,1,2,3,4,5,8,9) THEN objednavky.castka ELSE 0 END) AS castka_celkem, "
					."COUNT(CASE WHEN stav IN (0,1,2,3,4,5,8,9) THEN objednavky.id ELSE NULL END) AS pocet_celkem, "
					."SUM(CASE WHEN stav IN (0,1) THEN objednavky.castka ELSE 0 END) AS castka_neschvalene, "
					."COUNT(CASE WHEN stav IN (0,1) THEN objednavky.id ELSE NULL END) AS pocet_neschvalene, "
					."SUM(CASE WHEN stav IN (3,4) THEN objednavky.castka ELSE 0 END) AS castka_schvalene, "
					."COUNT(CASE WHEN stav IN (3,4) THEN objednavky.id ELSE NULL END) AS pocet_schvalene, "
					."SUM(CASE WHEN stav IN (2,5,8) THEN objednavky.castka ELSE 0 END) AS castka_zamitnute, "
					."COUNT(CASE WHEN stav IN (2,5,8) THEN objednavky.id ELSE NULL END) AS pocet_zamitnute, "
					."SUM(CASE WHEN stav IN (9) THEN objednavky.castka ELSE 0 END) AS castka_uctarna, "
					."COUNT(CASE WHEN stav IN (9) THEN objednavky.id ELSE NULL END) AS pocet_uctarna, "
					."GROUP_CONCAT(DISTINCT cinnost.cinnost SEPARATOR ', ') AS cinnosti, "
					."GROUP_CONCAT(DISTINCT objednavky.firma SEPARATOR ', ') AS firma ")
				->group('id_prehled')->where('id_prehled',$prehledId)
//				->where('cinnost.rozpocet.rok', $rok)   //TK: neni potreba, navic hazi chybu v testovaci DB kde se obcas objednavka odkazuje na cinnosti z jineho roku
				->fetch();
			}
}