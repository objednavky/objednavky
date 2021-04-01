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

namespace App\Model;

use Nette;

class ObjednavkyManager
{
	use Nette\SmartObject;

	/** @var Nette\Database\Context */
	private $database;

	public $fmt;

	public function __construct(Nette\Database\Context $database) {
		$this->database = $database;
		$this->fmt = new \NumberFormatter( 'cs_CZ', \NumberFormatter::CURRENCY );
		$this->fmt->setAttribute(\NumberFormatter::FRACTION_DIGITS, 0);
	}

	/**
	 * Načte tabulku pro MojeObjednavkyPresenter
	 */
    public function mapRozpocetMojeObjednavky(int $zadavatel, array $stavy) {
        $source = $this->objednavkyPodleStavu($stavy)
						->where('zakladatel', $zadavatel)
						->order('id DESC');
		return $this->mapRozpocetFromSource($source);
    }
	
	public function mapRozpocetPrehled(array $stavy)
    {
		$source = $this->objednavkyPodleStavu($stavy)
						->order('id DESC');
		return $this->mapRozpocetFromSource($source);
	}
    

	/**
	 * z připraveného datasource naplní pole rozpočtů pro gridy v prezenteru
	 */
    private function mapRozpocetFromSource($source) {
        $fetchedRozpocets = [];
        foreach ($source as $objednavky) {
            $item = [
            	'id' => $objednavky->id,
            	'id_prehled' => $objednavky->id_prehled,
            	'radka' => $objednavky->radka,
	            //$pomoc =  $this->database->table('objednavky')->where('id_prehled',$item->id_prehled )->fetch();
    	        // $pom2 = $this->database->table('prehled')->where('id',$pomoc->id_prehled)->fetch();
        	    // $pom3 = $this->database->table('uzivatel')->where('id',$pom2->id_uzivatel)->fetch();
            	// $item->zadavatel =$pom3->jmeno ;
            	'zadavatel' => $objednavky->ref('zakladatel')->jmeno,
				'zalozil' => $objednavky->zalozil,
            	'schvalovatel' => $objednavky->ref('kdo')->jmeno,
            	'schvalil' => $objednavky->schvalil,
            	'overovatel' => $objednavky->ref('kdo2')->jmeno,
            	'overil' => $objednavky->overil,
            	'nutno_overit' => $objednavky->nutno_overit,
            	'stav' => $objednavky->ref('stav')->popis,
            	'stav_id' => $objednavky->stav,
            	'firma' => $objednavky->firma,
            	'popis' => $objednavky->popis,
            	'cinnost' => $objednavky->ref('cinnost')->cinnost,
            	'cinnostP' => $objednavky->ref('cinnost')->nazev_cinnosti,
            	'zakazka' => $objednavky->ref('zakazka')->zakazka,
            	'stredisko' => $objednavky->ref('stredisko')->stredisko,
				'castka' => $objednavky->castka,
			];
			$fetchedRozpocets[] = $item;
		}
        return $fetchedRozpocets;
	}


	public function mapObjednavka(int $prehledId)
    {
		$source = $this->database->table('objednavky')
					->where('id_prehled', $prehledId);
		return $this->mapRozpocetFromSource($source);
	}


	/**
	 * Smaže objednávky, resp. změní jejich stav v databázi na smazané
	 */
	public function smazObjednavkyDb(array $ids): void
    {
        // TODO: tady je nutne doplnit kod kontrolujici, zda ma uzivatel pravo objednavku smazat

        $this->database->table('objednavky')->where('id',$ids)->update([
            'stav' => 7
        ]);
	}
	
	
    public function mapPrehledObjednavek(bool $smazane) {
        $source = $this->database->table('prehled')
						->order('id DESC');
		return $this->mapPrehledObjednavekFromSource($source, $smazane);
    }

	/**
	 * z připraveného datasource naplní pole rozpočtů pro gridy v prezenteru
	 */
    private function mapPrehledObjednavekFromSource($source, bool $smazane) {
        $fetchedPrehled = [];
        foreach ($source as $prehled) {
			$item = [
            	'id' => $prehled->id,
				'zadavatel' => $prehled->ref('zakladatel') == null ? '' : $prehled->ref('zakladatel')->jmeno,
				'zalozil' => $prehled->zalozil,
				'popis' => $prehled->popis,
			];
			$objednavky = $this->sumaObjednavekPodlePrehleduAStavu($prehled->id);
			if (isset($objednavky)) {
				bdump($objednavky);
				$item = array_merge($item, $objednavky->toArray());
			} else {
				$item['castka_celkem'] = 0;
				$item['pocet_celkem'] = 0;
				$item['cinnosti'] = '';
				$item['castka_neschvalene'] = 0;
				$item['pocet_neschvalene'] = 0;
				$item['castka_schvalene'] = 0;
				$item['pocet_schvalene'] = 0;
				$item['castka_zamitnute'] = 0;
				$item['pocet_zamitnute'] = 0;
				$item['castka_uctarna'] = 0;
				$item['pocet_uctarna'] = 0;
			}
			if ($item['pocet_celkem']>0 || $smazane) {
				//uloz jen pokud je nenulovy pocet polozek (krome stavu 7 = archiv)
				$fetchedPrehled[] = $item;
			}
		}
        return $fetchedPrehled;
	}


	private function objednavkyPodleStavu(array $stavy) {
		return $this->database->table('objednavky')
						->where('stav', $stavy);
	}

	private function sumaObjednavekPodlePrehleduAStavu(int $prehledId) {
		return $this->database->table('objednavky')
				->select("SUM(CASE WHEN stav IN (0,1,2,3,4,5,8,9) THEN castka ELSE 0 END) AS castka_celkem, "
					."COUNT(CASE WHEN stav IN (0,1,2,3,4,5,8,9) THEN objednavky.id ELSE NULL END) AS pocet_celkem, "
					."SUM(CASE WHEN stav IN (0,1) THEN castka ELSE 0 END) AS castka_neschvalene, "
					."COUNT(CASE WHEN stav IN (0,1) THEN objednavky.id ELSE NULL END) AS pocet_neschvalene, "
					."SUM(CASE WHEN stav IN (3,4) THEN castka ELSE 0 END) AS castka_schvalene, "
					."COUNT(CASE WHEN stav IN (3,4) THEN objednavky.id ELSE NULL END) AS pocet_schvalene, "
					."SUM(CASE WHEN stav IN (2,5,8) THEN castka ELSE 0 END) AS castka_zamitnute, "
					."COUNT(CASE WHEN stav IN (2,5,8) THEN objednavky.id ELSE NULL END) AS pocet_zamitnute, "
					."SUM(CASE WHEN stav IN (9) THEN castka ELSE 0 END) AS castka_uctarna, "
					."COUNT(CASE WHEN stav IN (9) THEN objednavky.id ELSE NULL END) AS pocet_uctarna, "
					."GROUP_CONCAT(DISTINCT cinnost.cinnost SEPARATOR ', ') AS cinnosti, "
					."GROUP_CONCAT(DISTINCT objednavky.firma SEPARATOR ', ') AS firma ")
				->group('id_prehled')->where('id_prehled',$prehledId)->fetch();
	}
}